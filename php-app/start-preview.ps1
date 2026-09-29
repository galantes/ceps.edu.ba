$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$phpRuntime = Join-Path $projectRoot 'migration/runtime/php'
$dbPasswordPath = Join-Path $projectRoot 'migration/runtime/db-password.txt'
if (Test-Path -LiteralPath $dbPasswordPath) {
    $env:CEPS_DSN = 'mysql:host=127.0.0.1;port=33079;dbname=ceps_preview;charset=utf8mb4'
    $env:CEPS_DB_USER = 'root'
    $env:CEPS_DB_PASSWORD = Get-Content -LiteralPath $dbPasswordPath -Raw
}
$env:CEPS_ENV = 'preview'
$env:CEPS_ORIGIN = 'https://www.ceps.edu.ba'
& (Join-Path $phpRuntime 'php.exe') -n -d ('extension_dir=' + (Join-Path $phpRuntime 'ext')) -d extension=pdo_mysql -d extension=pdo_sqlite -d extension=fileinfo -d display_errors=0 -d log_errors=1 -S 127.0.0.1:8086 -t (Join-Path $PSScriptRoot 'public') (Join-Path $PSScriptRoot 'router.php')
