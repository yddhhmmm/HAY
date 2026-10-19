# Windows PowerShell 5.1 / CIM. Tidak menerima input atau menjalankan perintah dari browser.
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = New-Object System.Text.UTF8Encoding($false)
$result = @{status='healthy'; errors=@(); cpu=$null; ram=$null; disks=@(); battery=$null; processCount=$null; computerName=$env:COMPUTERNAME; os=$null; uptimeSeconds=$null}
try {
    $os = Get-CimInstance Win32_OperatingSystem -OperationTimeoutSec 3
    $total = [double]$os.TotalVisibleMemorySize * 1024
    $used = $total - ([double]$os.FreePhysicalMemory * 1024)
    $result.ram = @{used=$used; total=$total; percent=[math]::Round($used / $total * 100, 2)}
    $result.os = @{caption=$os.Caption; version=$os.Version}
    $result.uptimeSeconds = [math]::Max(0, [math]::Floor(((Get-Date) - $os.LastBootUpTime).TotalSeconds))
} catch { $result.errors += 'Informasi OS/RAM/uptime gagal dibaca.' }
try {
    $values = @(Get-CimInstance Win32_Processor -OperationTimeoutSec 3 | Where-Object { $null -ne $_.LoadPercentage })
    if ($values.Count -gt 0) { $result.cpu = [math]::Round(($values | Measure-Object LoadPercentage -Average).Average, 2) }
    else { $result.errors += 'Nilai CPU belum tersedia.' }
} catch { $result.errors += 'CPU gagal dibaca.' }
try {
    $result.disks = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' -OperationTimeoutSec 3 | ForEach-Object { @{drive=$_.DeviceID; size=[double]$_.Size; free=[double]$_.FreeSpace} })
} catch { $result.errors += 'Disk gagal dibaca.' }
try {
    $b = Get-CimInstance Win32_Battery -OperationTimeoutSec 3 | Select-Object -First 1
    if ($b) { $result.battery = @{percent=$b.EstimatedChargeRemaining; status=$b.BatteryStatus} }
} catch { $result.errors += 'Baterai gagal dibaca.' }
try { $result.processCount = @(Get-Process).Count } catch { $result.errors += 'Jumlah proses gagal dibaca.' }
if ($result.errors.Count -gt 0) { $result.status='partial' }
$result | ConvertTo-Json -Depth 6 -Compress
