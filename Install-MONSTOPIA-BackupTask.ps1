[CmdletBinding(SupportsShouldProcess)]
param()

$ErrorActionPreference = 'Stop'
$taskProjectPath = [IO.Path]::GetFullPath($PSScriptRoot)
$taskRunnerPath = Join-Path $taskProjectPath 'Run-MONSTOPIA-Backup.ps1'
$taskPhpPath = Join-Path $taskProjectPath '.runtime\php\php.exe'
$taskName = 'MONSTOPIA Daily Backup'

if (-not (Test-Path -LiteralPath $taskPhpPath -PathType Leaf)) {
    throw 'PHP runtime not found. Start MONSTOPIA once first.'
}
if ((Get-TimeZone).Id -ne 'SE Asia Standard Time') {
    throw 'Daily 02:00 uses Windows local time. Please set Windows time zone to Bangkok first; this script will not change it.'
}
if (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue) {
    throw 'The task already exists. Inspect it in Task Scheduler before changing it; this installer will not overwrite it.'
}

$taskUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$taskArguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $taskRunnerPath + '" -IfStale'
$taskAction = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $taskArguments -WorkingDirectory $taskProjectPath
$taskDailyTrigger = New-ScheduledTaskTrigger -Daily -At '02:00'
$taskLoginTrigger = New-ScheduledTaskTrigger -AtLogOn -User $taskUser
$taskSettings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 1) -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 15)
$taskPrincipal = New-ScheduledTaskPrincipal -UserId $taskUser -LogonType Interactive -RunLevel Limited

if ($PSCmdlet.ShouldProcess($taskName, 'Register a 02:00 Bangkok backup and login catch-up for the current user without storing a password')) {
    Register-ScheduledTask -TaskName $taskName -Action $taskAction -Trigger @($taskDailyTrigger, $taskLoginTrigger) -Settings $taskSettings -Principal $taskPrincipal -Description 'SQL and private project-file backup. Requires MySQL, Windows login, and a powered-on computer. No stored credentials.' | Out-Null
    Write-Output 'Installed. Check Task Scheduler > MONSTOPIA Daily Backup. Nothing is deployed or uploaded.'
}
