param([string]$Php='C:\xampp\php\php.exe', [string]$Storage='C:\xampp\hay-private')
$ErrorActionPreference='Stop'
$env:HAY_STORAGE=$Storage
& $Php (Join-Path $PSScriptRoot 'collector.php') --loop
exit $LASTEXITCODE
