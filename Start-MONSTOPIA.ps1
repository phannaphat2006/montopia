$ErrorActionPreference = 'Stop'
$projectDirectory = $PSScriptRoot
$phpExecutable = Join-Path $projectDirectory '.runtime\php\php.exe'
$mysqlExecutable = Join-Path $projectDirectory '.runtime\mysql\bin\mysqld.exe'
$mysqlConfiguration = Join-Path $projectDirectory '.runtime\mysql\my.ini'

function Test-LocalPort([int] $Port) {
    $connection = [System.Net.Sockets.TcpClient]::new()
    try {
        $connection.Connect('127.0.0.1', $Port)
        return $true
    } catch {
        return $false
    } finally {
        $connection.Dispose()
    }
}

try {
    foreach ($requiredFile in @($phpExecutable, $mysqlExecutable, $mysqlConfiguration)) {
        if (-not (Test-Path -LiteralPath $requiredFile -PathType Leaf)) {
            throw "Required local runtime is missing: $requiredFile"
        }
    }
    if (-not (Test-LocalPort 3306)) {
        Start-Process -FilePath $mysqlExecutable -ArgumentList "--defaults-file=`"$mysqlConfiguration`"" -WorkingDirectory $projectDirectory -WindowStyle Hidden
        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            if (Test-LocalPort 3306) { break }
            Start-Sleep -Milliseconds 500
        }
    }
    if (-not (Test-LocalPort 3306)) {
        throw 'MySQL did not start. Check .runtime\mysql\data for its error log.'
    }
    Push-Location -LiteralPath $projectDirectory
    try {
        & $phpExecutable artisan migrate:status --no-ansi
        if ($LASTEXITCODE -ne 0) { throw 'Laravel cannot connect to the configured database. Check the local .env.' }
    } finally {
        Pop-Location
    }
    if (-not (Test-LocalPort 8000)) {
        Start-Process -FilePath $phpExecutable -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8000' -WorkingDirectory $projectDirectory -WindowStyle Hidden
        for ($attempt = 0; $attempt -lt 20; $attempt++) {
            if (Test-LocalPort 8000) { break }
            Start-Sleep -Milliseconds 500
        }
    }
    if (-not (Test-LocalPort 8000)) { throw 'The web server did not start.' }
    $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/auth/me' -UseBasicParsing -TimeoutSec 10
    if ($response.StatusCode -ne 200 -or -not ($response.Content | ConvertFrom-Json).PSObject.Properties['user']) {
        throw 'Port 8000 is not responding as the MONSTOPIA application.'
    }
    Write-Host "`nMONSTOPIA is running on this computer."
    Write-Host 'Website:   http://127.0.0.1:8000'
    Write-Host 'Workspace: http://127.0.0.1:8000/workspace'
    Write-Host 'These local links cannot be used by friends over the internet.'
} catch {
    Write-Host "Startup failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
