param([string]$Php='C:\xampp\php\php.exe', [string]$Storage='')
$ErrorActionPreference='Stop'
if ($Storage) { $env:HAY_STORAGE=$Storage }
& $Php (Join-Path $PSScriptRoot 'collector.php') --loop
exit $LASTEXITCODE
