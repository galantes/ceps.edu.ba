$ErrorActionPreference = 'Stop'
$accountFile = Join-Path $PSScriptRoot 'var/admin.json'
if (Test-Path -LiteralPath $accountFile) { throw 'Administratorski račun već postoji.' }
$phpExe = Join-Path (Split-Path $PSScriptRoot -Parent) 'migration/runtime/php/php.exe'
if (!(Test-Path -LiteralPath $phpExe)) { $phpExe = 'php' }
$adminName = Read-Host 'Korisničko ime (3-60 znakova)'
$securePassword = Read-Host 'Lozinka (najmanje 14 znakova)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $previousOutputEncoding = $OutputEncoding
    $OutputEncoding = New-Object System.Text.UTF8Encoding($false)
    "$adminName`n$plainPassword" | & $phpExe (Join-Path $PSScriptRoot 'bin/create-admin.php')
    if ($LASTEXITCODE -ne 0) { throw 'Račun nije izrađen. Provjerite korisničko ime i dužinu lozinke.' }
} finally {
    if ($null -ne $previousOutputEncoding) { $OutputEncoding = $previousOutputEncoding }
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    $plainPassword = $null
    $securePassword.Dispose()
}
Write-Host 'Otvorite http://127.0.0.1:8086/admin i prijavite se.'
