# PowertechCenter - New PC Setup Script
# Run as Administrator

$ErrorActionPreference = "SilentlyContinue"

function Write-Step($msg) { Write-Host "`n[*] $msg" -ForegroundColor Cyan }
function Write-OK($msg)   { Write-Host "    OK  $msg" -ForegroundColor Green }
function Write-Skip($msg) { Write-Host "    --  $msg (skip)" -ForegroundColor Yellow }

# Check Admin
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]"Administrator")) {
    Write-Host "Please run as Administrator" -ForegroundColor Red
    pause; exit
}

Write-Host "============================================" -ForegroundColor White
Write-Host "  PowertechCenter - New PC Setup" -ForegroundColor White
Write-Host "============================================" -ForegroundColor White

# 1. Timezone
Write-Step "Set Timezone -> Bangkok (UTC+7)"
Set-TimeZone -Id "SE Asia Standard Time"
Write-OK "Timezone set"

# 2. Sync Time
Write-Step "Sync Time"
w32tm /resync /force | Out-Null
Write-OK "Time synced"

# 3. Add Thai Language
Write-Step "Add Thai Language (th-TH)"
$langList = Get-WinUserLanguageList
$hasTH = $langList | Where-Object { $_.LanguageTag -eq "th-TH" }
if (-not $hasTH) {
    $langList.Add("th-TH")
    Set-WinUserLanguageList $langList -Force
    Write-OK "Thai language added"
} else {
    Write-Skip "Thai already exists"
}

# 4. Install via Ninite
Write-Step "Install apps via Ninite (Chrome, Zoom, 7-Zip, Notepad++, Foxit, Everything)"
$share = "\\192.168.0.11\datacenter\Installers"
$ninite = "$share\Ninite_Setup.exe"
if (Test-Path $ninite) {
    Write-Host "    Running Ninite_Setup.exe..." -NoNewline
    Start-Process $ninite -Wait
    Write-Host " OK" -ForegroundColor Green
} else {
    Write-Skip "Ninite_Setup.exe not found in $share"
}

# 5. FortiClient VPN
Write-Step "Install FortiClient VPN"
$forti = "$share\FortiClientVPN.exe"
if (Test-Path $forti) {
    Write-Host "    Installing FortiClient VPN..." -NoNewline
    Start-Process $forti -ArgumentList "/silent" -Wait
    Write-Host " OK" -ForegroundColor Green
} else {
    Write-Skip "FortiClientVPN.exe not found in $share"
}

# 6. LINE
Write-Step "Install LINE"
Write-Host "    Installing LINE..." -NoNewline
winget install --id LINE.LINE -e --silent --accept-package-agreements --accept-source-agreements | Out-Null
if ($LASTEXITCODE -eq 0 -or $LASTEXITCODE -eq -1978335189) {
    Write-Host " OK" -ForegroundColor Green
} else {
    Write-Host " skip (already installed or error)" -ForegroundColor Yellow
}

# 7. Map Network Drive Z:
Write-Step "Map Network Drive Z: -> \\192.168.0.11\datacenter"
$driveExists = Get-PSDrive -Name "Z" -ErrorAction SilentlyContinue
if (-not $driveExists) {
    cmd /c "net use Z: \\192.168.0.11\datacenter /persistent:yes" | Out-Null
    Write-OK "Drive Z: mapped"
} else {
    Write-Skip "Z: already exists"
}

# 8. Desktop Shortcut PowertechCenter
Write-Step "Create PowertechCenter Shortcut on Desktop"
$desktopPath = [Environment]::GetFolderPath("CommonDesktopDirectory")
$shortcutPath = "$desktopPath\PowertechCenter.lnk"
if (-not (Test-Path $shortcutPath)) {
    $wsh = New-Object -ComObject WScript.Shell
    $sc = $wsh.CreateShortcut($shortcutPath)
    $sc.TargetPath = "http://192.168.0.11/PowertechCenter"
    $sc.IconLocation = "C:\Windows\System32\url.dll,0"
    $sc.Description = "PowertechCenter"
    $sc.Save()
    Write-OK "Shortcut created"
} else {
    Write-Skip "Shortcut already exists"
}

# 9. Restore Point
Write-Step "Create Restore Point"
Enable-ComputerRestore -Drive "C:\" | Out-Null
Checkpoint-Computer -Description "PowertechCenter Setup Complete" -RestorePointType "MODIFY_SETTINGS" | Out-Null
Write-OK "Restore Point created"

# Done
Write-Host "`n============================================" -ForegroundColor White
Write-Host "  Setup Complete!" -ForegroundColor Green
Write-Host "  Remaining manual steps:" -ForegroundColor Yellow
Write-Host "  - Install VNC Server + set password" -ForegroundColor Yellow
Write-Host "  - Join Domain (if needed)" -ForegroundColor Yellow
Write-Host "  - Install Printer" -ForegroundColor Yellow
Write-Host "  - Install Antivirus" -ForegroundColor Yellow
Write-Host "  - Notify user Account / Password" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor White
pause
