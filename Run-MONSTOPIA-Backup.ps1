param([switch]$IfStale)

$ErrorActionPreference = 'Stop'
$taskProjectPath = $PSScriptRoot
$taskPhpPath = Join-Path $taskProjectPath '.runtime\php\php.exe'
$taskArtisanPath = Join-Path $taskProjectPath 'artisan'
$taskLogDirectory = Join-Path $taskProjectPath 'storage\logs'
$taskStatePath = Join-Path $taskProjectPath 'storage\app\private\backup-last-success.json'
$taskRuntimeInfoPath = Join-Path $taskProjectPath 'scripts\backup-runtime-info.php'

function Test-BackupLocalPort([int] $Port) {
    $taskConnection = [Net.Sockets.TcpClient]::new()
    try {
        $taskConnection.Connect('127.0.0.1', $Port)
        return $true
    } catch {
        return $false
    } finally {
        $taskConnection.Dispose()
    }
}

if (-not (Test-Path -LiteralPath $taskPhpPath -PathType Leaf)) {
    throw 'PHP runtime not found. Start MONSTOPIA once before installing backups.'
}
if ($IfStale -and (Test-Path -LiteralPath $taskStatePath)) {
    try {
        $taskState = Get-Content -LiteralPath $taskStatePath -Raw | ConvertFrom-Json
        $taskBackupAge = ([DateTimeOffset]::UtcNow - [DateTimeOffset]::Parse($taskState.finished_at)).TotalHours
        if ($taskBackupAge -ge 0 -and $taskBackupAge -lt 22) {
            exit 0
        }
    } catch {
        Write-Warning 'Cannot read the last-success marker; create a fresh backup rather than skip it.'
    }
}
New-Item -ItemType Directory -Path $taskLogDirectory -Force | Out-Null
$taskLogPath = Join-Path $taskLogDirectory ('backup-' + (Get-Date -Format 'yyyy-MM-dd') + '.log')
Push-Location -LiteralPath $taskProjectPath
try {
    # Read only driver/host/port through Laravel, never print .env or a password.
    $taskDatabase = (& $taskPhpPath $taskRuntimeInfoPath) | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw 'Cannot read Laravel backup runtime configuration.' }
    if ($taskDatabase.driver -eq 'mysql' -and $taskDatabase.host -in @('127.0.0.1', 'localhost', '::1') -and [int]$taskDatabase.port -eq 3306 -and -not (Test-BackupLocalPort 3306)) {
        $taskMysqlPath = Join-Path $taskProjectPath '.runtime\mysql\bin\mysqld.exe'
        $taskMysqlConfig = Join-Path $taskProjectPath '.runtime\mysql\my.ini'
        if (-not (Test-Path -LiteralPath $taskMysqlPath -PathType Leaf) -or -not (Test-Path -LiteralPath $taskMysqlConfig -PathType Leaf)) {
            throw 'Local MySQL is stopped and its bundled runtime is missing. Start MySQL first.'
        }
        Start-Process -FilePath $taskMysqlPath -ArgumentList ('--defaults-file="' + $taskMysqlConfig + '"') -WorkingDirectory $taskProjectPath -WindowStyle Hidden
        for ($taskAttempt = 0; $taskAttempt -lt 30; $taskAttempt++) {
            if (Test-BackupLocalPort 3306) { break }
            Start-Sleep -Milliseconds 500
        }
        if (-not (Test-BackupLocalPort 3306)) { throw 'MySQL did not start. Inspect the MySQL error log in .runtime/mysql/data.' }
    }
    & $taskPhpPath $taskArtisanPath monstopia:backup-all *>> $taskLogPath
    if ($LASTEXITCODE -ne 0) {
        throw 'Backup failed. Check storage/logs/backup-YYYY-MM-DD.log. MySQL must be running.'
    }
    # A marker contains only the success date, never database or email credentials.
    $taskNewStatePath = $taskStatePath + '.' + [Guid]::NewGuid().ToString('N') + '.tmp'
    @{ finished_at = [DateTimeOffset]::UtcNow.ToString('o') } | ConvertTo-Json | Set-Content -LiteralPath $taskNewStatePath -Encoding UTF8
    Move-Item -LiteralPath $taskNewStatePath -Destination $taskStatePath -Force
} finally {
    Pop-Location
}
