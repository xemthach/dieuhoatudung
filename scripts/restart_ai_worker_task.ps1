param(
    [switch]$Restart,
    [string]$TaskName = 'DieuHoaTuDung-AIGovernedWorker'
)

$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$php = (Get-Command php -ErrorAction Stop).Source
$artisan = Join-Path $project 'artisan'
$expectedArguments = "`"$artisan`" ai:managed-worker --queue=ai_governed --sleep=3 --tries=3 --timeout=900"
$task = Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop
$action = $task.Actions | Select-Object -First 1

if ($action.Execute -ne $php -or [string]$action.Arguments -ne $expectedArguments -or $action.WorkingDirectory -ne $project) {
    throw "Task $TaskName does not match the reviewed executable, arguments, or working directory."
}

$healthBefore = (& $php $artisan ai:queue-health --json | ConvertFrom-Json)
$oldSupervisor = if ($null -ne $healthBefore.worker_runtime.supervisor_pid) { [int]$healthBefore.worker_runtime.supervisor_pid } else { 0 }
$oldChild = if ($null -ne $healthBefore.worker_runtime.child_pid) { [int]$healthBefore.worker_runtime.child_pid } else { 0 }

if (-not $Restart) {
    [pscustomobject]@{
        Mode = 'DRY_RUN'
        TaskName = $TaskName
        DesiredState = $healthBefore.worker_desired_state
        OldSupervisor = $oldSupervisor
        OldChild = $oldChild
        Command = "$php $expectedArguments"
    } | ConvertTo-Json
    exit 0
}

$restartStartedAt = [DateTimeOffset]::Now
Stop-ScheduledTask -TaskName $TaskName
$deadline = (Get-Date).AddSeconds(30)
do {
    $supervisorAlive = $oldSupervisor -gt 0 -and [bool](Get-Process -Id $oldSupervisor -ErrorAction SilentlyContinue)
    $childAlive = $oldChild -gt 0 -and [bool](Get-Process -Id $oldChild -ErrorAction SilentlyContinue)
    if (-not $supervisorAlive -and -not $childAlive) { break }
    Start-Sleep -Milliseconds 500
} while ((Get-Date) -lt $deadline)

if ($supervisorAlive -or $childAlive) {
    throw 'Old managed worker processes did not exit within 30 seconds; refusing to start a duplicate.'
}

Start-ScheduledTask -TaskName $TaskName
$deadline = (Get-Date).AddSeconds(30)
$restartVerified = $false
do {
    Start-Sleep -Seconds 1
    $healthAfter = (& $php $artisan ai:queue-health --json | ConvertFrom-Json)
    $newSupervisor = if ($null -ne $healthAfter.worker_runtime.supervisor_pid) { [int]$healthAfter.worker_runtime.supervisor_pid } else { 0 }
    $newChild = if ($null -ne $healthAfter.worker_runtime.child_pid) { [int]$healthAfter.worker_runtime.child_pid } else { 0 }
    $runtimeStartedAt = [DateTimeOffset]::MinValue
    [DateTimeOffset]::TryParse([string]$healthAfter.worker_runtime.started_at, [ref]$runtimeStartedAt) | Out-Null
    $newPids = $newSupervisor -gt 0 -and $newChild -gt 0 -and $newSupervisor -ne $oldSupervisor -and $newChild -ne $oldChild
    # Heartbeat timestamps are rendered without an offset for human display.
    # The managed runtime start is ISO-8601 with an offset and is the reliable
    # post-restart boundary; ONLINE additionally requires a fresh heartbeat.
    $newRuntime = $runtimeStartedAt -ge $restartStartedAt.AddSeconds(-1)
    $versionMatch = $healthAfter.worker_deployment_status -eq 'UP_TO_DATE' -and $healthAfter.application_runtime.app_version -eq $healthAfter.worker_runtime.app_version
    $restartVerified = $healthAfter.worker_heartbeat.health_status -eq 'ONLINE' -and $newPids -and $newRuntime -and $versionMatch
    if ($restartVerified) { break }
} while ((Get-Date) -lt $deadline)

if (-not $restartVerified) {
    throw 'Managed worker restart was not proven within 30 seconds: require new PIDs, post-restart heartbeat/runtime, and matching web/worker release.'
}

[pscustomobject]@{
    Mode = 'RESTARTED'
    TaskName = $TaskName
    DesiredState = $healthAfter.worker_desired_state
    ActualState = $healthAfter.worker_heartbeat.health_status
    ProcessStatus = $healthAfter.worker_heartbeat.process_status
    AcceptingNewJobs = $healthAfter.worker_heartbeat.accepting_new_jobs
    OldSupervisor = $oldSupervisor
    OldChild = $oldChild
    NewSupervisor = $newSupervisor
    NewChild = $newChild
    DeploymentStatus = $healthAfter.worker_deployment_status
    AppVersion = $healthAfter.application_runtime.app_version
    WorkerVersion = $healthAfter.worker_runtime.app_version
    AppBuild = $healthAfter.application_runtime.build_id
    WorkerBuild = $healthAfter.worker_runtime.build_id
} | ConvertTo-Json
