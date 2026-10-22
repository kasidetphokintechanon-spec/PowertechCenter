# sync_to_server.ps1 — ดันโค้ดขึ้นเครื่องจริง (QNAP) · เรียกผ่าน sync_to_server.bat
#
# ส่งเฉพาะไฟล์ที่บันทึกขึ้น git แล้วเท่านั้น — ไฟล์ที่ไม่เคย commit (แบบร่าง ไฟล์ทดสอบ dump log รหัสลับ)
# ถูกตัดออกอัตโนมัติ ไม่ต้องคอยเพิ่มรายการยกเว้น (เคยหลุดขึ้นเว็บจริงมาแล้ว 22 ต.ค. 2026 — TISAX)
# ไม่ลบไฟล์บนเครื่องจริง · ข้าม config.php เสมอ · ข้ามไฟล์ที่บนเครื่องจริงใหม่กว่า (/XO)
param(
    [ValidateSet('dry', 'sync')] [string]$Mode = 'dry',
    [switch]$AllowUncommitted
)
$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$src = $PSScriptRoot
$dst = '\\192.168.0.2\Web\PowertechCenter'
$log = Join-Path $src 'deploy_log.txt'
Set-Location $src

function Say($msg, $color = 'Gray') { Write-Host $msg -ForegroundColor $color }

# ── 1. ต่อเครื่องจริงได้ไหม ──
if (-not (Test-Connection 192.168.0.2 -Count 1 -Quiet)) { Say '[ERROR] ping 192.168.0.2 ไม่ได้ — ต่อ VPN (FortiClient) ก่อน' Red; exit 1 }
if (-not (Test-Path $dst)) { Say "[ERROR] เปิด $dst ไม่ได้" Red; exit 1 }

# ── 2. งานที่แก้แต่ยังไม่ commit จะขึ้นเครื่องจริงไม่ได้ (เครื่องจริงต้องตรงกับโค้ดชุดที่บันทึกไว้) ──
$dirty = @(git status --porcelain --untracked-files=no)
if ($dirty.Count -and -not $AllowUncommitted) {
    Say '[STOP] มีไฟล์ที่แก้แล้วแต่ยังไม่บันทึกขึ้น git:' Yellow
    $dirty | ForEach-Object { Say "        $_" Yellow }
    Say '       บันทึก (commit) ก่อน แล้วค่อย sync' Yellow
    exit 2
}
$commit = (git log -1 --format='%h %s').Trim()

# ── 3. ไฟล์/โฟลเดอร์ที่ไม่ได้อยู่ใน git → ตัดออกทั้งหมด (ใช้ -z กันชื่อภาษาไทยถูก quote) ──
$untracked = @()
foreach ($ign in @($false, $true)) {
    $raw = if ($ign) { git ls-files --others --ignored --exclude-standard --directory -z } else { git ls-files --others --exclude-standard --directory -z }
    if ($raw) { $untracked += ($raw -split "`0") | Where-Object { $_ } }
}
$xd = @(); $xf = @()
foreach ($p in ($untracked | Sort-Object -Unique)) {
    $full = Join-Path $src ($p.TrimEnd('/') -replace '/', '\')
    if ($p.EndsWith('/')) { $xd += $full } else { $xf += $full }
}

# ── 4. อยู่ใน git แต่ไม่ใช่ของหน้าเว็บ (สำรอง แบบร่าง เอกสาร สคริปต์ dump) ──
$xd += @('.git', '.claude', '.cursor', '.qodo', '.vscode', 'DB', 'database', 'docs', 'tools', 'orchestration') | ForEach-Object { Join-Path $src $_ }
$xf += @('config.php', 'config.local.php', 'migrate.php', 'populate_kb.php', 'create_hash.php', 'add_item.php', 'delete_item.php', 'update_item.php') | ForEach-Object { Join-Path $src $_ }
$xf += @('seed_*.php', '*.example', '*.example.php', 'package.json', 'package-lock.json')
$xf += @('*_BK*.html', '*_BK*.js', '*_BK*.css', '*mockup*.html', '*mockup*.css', '*.md', '*.ps1', '*.bat', '*.sql', '*.zip', '*.jsonl', '.gitignore', 'deploy_log.txt', 'deploy_version.txt')

Say ''
Say '============================================================' Cyan
Say " Sync TO server — $(if ($Mode -eq 'dry') { 'DRY RUN (แสดงรายการเท่านั้น)' } else { 'LIVE SYNC' })" Cyan
Say " โค้ดชุด : $commit" Cyan
Say " ปลายทาง: $dst" Cyan
Say " ตัดไฟล์ที่ไม่อยู่ใน git: $($untracked.Count) รายการ" Cyan
Say '============================================================' Cyan

$opts = @('/E', '/XO', '/R:2', '/W:3', '/NP', '/NDL', '/FP', '/XD') + $xd + @('/XF') + $xf
if ($Mode -eq 'dry') {
    $out = & robocopy $src $dst @opts /L /NJH /NJS
    $files = @($out | Where-Object { $_ -match '\S' -and $_ -match [regex]::Escape($src) } | ForEach-Object { ($_ -replace ('^.*?' + [regex]::Escape($src + '\')), '').Trim() })
    $files | ForEach-Object { Say "  $_" }
    Say ''
    Say "[DRY RUN] จะส่ง $($files.Count) ไฟล์ — ยังไม่ได้ copy จริง" Green
    exit 0
}

Add-Content -Path $log -Value "`r`n==== $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') · $env:USERNAME · $commit ====" -Encoding UTF8
& robocopy $src $dst @opts /Z /TEE "/LOG+:$log"
$rc = $LASTEXITCODE
if ($rc -ge 8) { Say "[ERROR] sync ล้มเหลว (robocopy code $rc) — ดู deploy_log.txt" Red; exit $rc }

# บันทึกว่าเครื่องจริงเป็นโค้ดชุดไหน (ไฟล์นี้เปิดผ่านเว็บไม่ได้ — .htaccess กัน *.txt ที่ root)
Set-Content -Path (Join-Path $dst 'deploy_version.txt') -Value "$commit`r`n$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') โดย $env:USERNAME" -Encoding UTF8
Say ''
Say "[OK] Sync สำเร็จ (robocopy code $rc) · เครื่องจริงเป็นโค้ดชุด $commit" Green
Say 'ทดสอบ: https://192.168.0.2:8081/PowertechCenter/' Green
exit 0
