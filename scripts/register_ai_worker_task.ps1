param(
    [switch]$Register,
    [switch]$Unregister,
    [string]$TaskName = 'DieuHoaTuDung-AIGovernedWorker'
)

$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$php = (Get-Command php -ErrorAction Stop).Source
$action = "`"$php`" `"$project\artisan`" ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900"

if ($Unregister) {
    $identity=[Security.Principal.WindowsIdentity]::GetCurrent()
    $principal=New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'WINDOWS_ADMIN_REQUIRED' }
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Output "UNREGISTERED $TaskName"
    exit 0
}

if (-not $Register) {
    Write-Output "DRY-RUN"
    Write-Output "TaskName=$TaskName"
    Write-Output "WorkingDirectory=$project"
    Write-Output "Php=$php"
    Write-Output "Action=$action"
    Write-Output 'No task was changed. Re-run with -Register after reviewing the command.'
    exit 0
}

$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
$principal=New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'WINDOWS_ADMIN_REQUIRED' }

$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existingTask) {
    $existingArguments = [string] $existingTask.Actions.Arguments
    $expectedArguments = "`"$project\artisan`" ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900"
    if ($existingTask.Actions.Execute -ne $php -or $existingArguments -ne $expectedArguments) {
        throw "Existing task $TaskName has unexpected executable or arguments; refusing overwrite."
    }
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit (New-TimeSpan -Days 365)
$trigger = New-ScheduledTaskTrigger -AtLogOn
$taskAction = New-ScheduledTaskAction -Execute $php -Argument "`"$project\artisan`" ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900" -WorkingDirectory $project
Register-ScheduledTask -TaskName $TaskName -Action $taskAction -Trigger $trigger -Principal $principal -Settings $settings -Description 'Governed Laravel AI worker; legacy ai queue is intentionally excluded.' | Out-Null
Write-Output "REGISTERED $TaskName"
