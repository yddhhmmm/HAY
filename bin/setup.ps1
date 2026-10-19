param(
    [string]$Php = 'C:\xampp\php\php.exe',
    [string]$Storage = '',
    [string]$Username = 'admin',
    [switch]$ResetPassword,
    [switch]$Viewer
)
$ErrorActionPreference = 'Stop'
if ($Storage) {
    $env:HAY_STORAGE = $Storage
} else {
    $Storage = & $Php (Join-Path $PSScriptRoot 'admin.php') storage-path
    if ($LASTEXITCODE -ne 0 -or -not $Storage) { throw 'Lokasi penyimpanan tidak dapat ditentukan.' }
    $Storage = ([string]$Storage).Trim()
}
if (-not (Test-Path -LiteralPath $Storage)) {
    New-Item -ItemType Directory -Path $Storage | Out-Null
    # Batasi ACL hanya pada direktori baru; jangan menimpa izin folder pengguna yang sudah ada.
    $acl = [System.Security.AccessControl.DirectorySecurity]::new()
    $acl.SetAccessRuleProtection($true, $false)
    $inherit = [System.Security.AccessControl.InheritanceFlags]::ContainerInherit -bor [System.Security.AccessControl.InheritanceFlags]::ObjectInherit
    $current = [System.Security.Principal.WindowsIdentity]::GetCurrent().User
    $system = [System.Security.Principal.SecurityIdentifier]::new('S-1-5-18')
    $admins = [System.Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    foreach ($identity in @($current, $system, $admins)) {
        $rights = if ($identity -eq $current) { 'Modify' } else { 'FullControl' }
        $rule = [System.Security.AccessControl.FileSystemAccessRule]::new($identity, $rights, $inherit, 'None', 'Allow')
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Storage -AclObject $acl
} else {
    Write-Host 'Folder privat sudah ada; ACL tidak diubah. Pastikan hanya akun berwenang yang memiliki akses.'
}
$secret = Read-Host 'Password admin (minimal 12 karakter)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Php
    $script = Join-Path $PSScriptRoot 'admin.php'
    $mode = if ($Viewer) { 'set-viewer' } elseif ($ResetPassword) { 'reset-password' } else { 'init' }
    if ($Username -notmatch '^[a-zA-Z0-9_.-]{3,40}$') { throw 'Username tidak valid.' }
    $psi.Arguments = '"' + $script + '" ' + $mode + ' ' + $Username
    $psi.UseShellExecute = $false
    $psi.RedirectStandardInput = $true
    $process = [Diagnostics.Process]::Start($psi)
    # BaseStream kompatibel dengan .NET Framework / Windows PowerShell 5.1.
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($password)
    $stdin = $process.StandardInput.BaseStream
    $stdin.Write($bytes, 0, $bytes.Length)
    $stdin.Flush()
    $stdin.Close()
    [Array]::Clear($bytes, 0, $bytes.Length)
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) { throw 'Setup gagal.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
    $password = $null
    $secret.Dispose()
}
