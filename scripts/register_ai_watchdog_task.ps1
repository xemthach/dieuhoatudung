param(
    [switch]$Register,
    [switch]$Unregister,
    [string]$TaskName = 'DieuHoaTuDung-AIGovernedWatchdog'
)
$ErrorActionPreference='Stop'
$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
$principal=New-Object Security.Principal.WindowsPrincipal($identity)
if (($Register -or $Unregister) -and -not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'WINDOWS_ADMIN_REQUIRED' }
$project=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$php=(Get-Command php -ErrorAction Stop).Source
$args="`"$project\artisan`" ai:worker-watchdog"
if ($Unregister) { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue; Write-Output "UNREGISTERED $TaskName"; exit 0 }
if (-not $Register) { Write-Output "DRY-RUN TaskName=$TaskName Php=$php Project=$project Args=$args"; exit 0 }
$action=New-ScheduledTaskAction -Execute $php -Argument $args -WorkingDirectory $project
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 365)
$settings=New-ScheduledTaskSettingsSet -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
$taskPrincipal=New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $taskPrincipal -Settings $settings -Description 'Recovers the governed AI worker only when desired state is ENABLED.' -Force | Out-Null
Write-Output "REGISTERED $TaskName"
