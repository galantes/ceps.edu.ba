$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$runtimeRoot = Join-Path $projectRoot 'migration/runtime'
$dbBase = Join-Path $runtimeRoot 'mariadb-11.4.8-winx64'
$dbData = Join-Path $runtimeRoot 'mariadb-data'
if (!(Test-Path (Join-Path $dbData 'my.ini'))) { throw 'Local database has not been initialized.' }
$connection = [System.Net.Sockets.TcpClient]::new()
try {
    $connection.Connect('127.0.0.1', 33079)
    Write-Output 'Port 33079 is already running; no second database process started.'
    return
} catch {
    # Start only the previously initialized project-local database.
} finally {
    $connection.Dispose()
}
$dbProcess = Start-Process -FilePath (Join-Path $dbBase 'bin/mariadbd.exe') -ArgumentList @('--no-defaults', ('--basedir="' + $dbBase + '"'), ('--datadir="' + $dbData + '"'), '--port=33079', '--bind-address=127.0.0.1', '--console') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeRoot 'mariadb.stdout.log') -RedirectStandardError (Join-Path $runtimeRoot 'mariadb.stderr.log') -PassThru
Write-Output ('Project-local MariaDB started, PID ' + $dbProcess.Id)
