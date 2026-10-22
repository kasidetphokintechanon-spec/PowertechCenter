<?php
require_once __DIR__ . '/config.php';

// TISAX: ข้อมูลเครื่องและผู้ถือครองเป็นข้อมูลภายใน — สแกนแล้วต้องล็อกอินก่อน แล้วพากลับมาหน้านี้เอง
if (empty($_SESSION['user_id'])) {
    $back = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/scan.php?id=' . preg_replace('/[^A-Za-z0-9\-_]/', '', trim($_GET['id'] ?? ''));
    header('Location: login.html?returnUrl=' . urlencode($back));
    exit;
}

$equipmentId = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($_GET['id'] ?? ''));

$asset        = null;
$primaryUrl   = 'http://192.168.0.2/PowertechCenter';
$backupUrl    = 'https://powertecha.myqnapcloud.com:8081/PowertechCenter';
$lineGroupUrl = '';

$conn = null;
foreach (['127.0.0.1', 'localhost'] as $host) {
    $c = mysqli_init();
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}

if ($conn) {
    if ($equipmentId) {
        $stmt = $conn->prepare("
            SELECT a.equipment_id, a.name, a.brand, a.model, a.status,
                   a.location, a.borrower, a.serial_number,
                   a.image_url, a.images, a.hostname, a.ip_address,
                   a.custodian_type, a.department, a.warranty_expires_on,
                   a.company,
                   c.cat_name, d.dept_name AS owning_dept
            FROM it_assets a
            LEFT JOIN asset_categories c ON c.cat_id = a.asset_category
            LEFT JOIN departments d ON d.dept_id = a.owning_department
            WHERE a.equipment_id = ? LIMIT 1
        ");
        $stmt->bind_param('s', $equipmentId);
        $stmt->execute();
        $asset = $stmt->get_result()->fetch_assoc();
    }

    $keys = ['asset_scan_url_primary','asset_scan_url_backup','it_line_group_url'];
    $in   = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
    if ($stmt) {
        $stmt->bind_param(str_repeat('s', count($keys)), ...$keys);
        $stmt->execute();
        $s = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $s[$r['setting_key']] = $r['setting_value'];
        if (!empty($s['asset_scan_url_primary'])) $primaryUrl  = rtrim($s['asset_scan_url_primary'], '/');
        if (!empty($s['asset_scan_url_backup']))  $backupUrl   = rtrim($s['asset_scan_url_backup'],  '/');
        $lineGroupUrl = $s['it_line_group_url'] ?? '';
    }
    $conn->close();
}

function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
function norm($img, $base) {
    $img = trim((string)$img);
    if (!$img) return '';
    $p = strpos($img, 'uploads/');
    if ($p !== false) $img = substr($img, $p);
    return rtrim($base, '/') . '/' . ltrim($img, '/');
}

$imageUrl = '';
if ($asset) {
    $img = $asset['image_url'] ?? '';
    if (!$img) {
        $imgs = json_decode($asset['images'] ?? '[]', true);
        $img  = $imgs[0] ?? '';
    }
    $imageUrl = norm($img, $primaryUrl);
}

$status = $asset['status'] ?? '';
$sMap = [
    'ใช้งานปกติ' => ['#059669', '#d1fae5', 'active'],
    'ถูกยืม'     => ['#d97706', '#fef3c7', 'borrowed'],
    'ชำรุด'      => ['#dc2626', '#fee2e2', 'broken'],
    'รอซ่อม'     => ['#dc2626', '#fee2e2', 'repair'],
    'รออนุมัติ'  => ['#d97706', '#fef3c7', 'pending'],
    'เลิกใช้งาน' => ['#6b7280', '#f3f4f6', 'retired'],
];
[$sc, $sb] = $sMap[$status] ?? ['#475569', '#f1f5f9'];

// Warranty
$warrantyDays = null;
$warrantyExpired = false;
if (!empty($asset['warranty_expires_on'])) {
    $wDate = new DateTime($asset['warranty_expires_on']);
    $today = new DateTime('today');
    $diff  = $today->diff($wDate);
    $warrantyDays = $diff->invert ? -$diff->days : $diff->days;
    $warrantyExpired = $warrantyDays < 0;
}

// ใช้ URL จาก request จริง เพื่อให้ login/ticket ชี้กลับมาที่เซิร์ฟเวอร์เดิมเสมอ
$_scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$selfBase  = $_scheme . '://' . $_host . $_dir;

$ticketUrl = $selfBase . '/request.html?asset_id=' . urlencode($equipmentId);

// Company display
$companyKey = strtoupper(trim($asset['company'] ?? ''));
$companyMap = [
    'PTA' => ['Powertech A',  '#059669', '#d1fae5'],
    'PT4' => ['Powertech 4',  '#dc2626', '#fee2e2'],
    'PTE' => ['Powertech E',  '#2563eb', '#dbeafe'],
];
[$companyName, $companyColor, $companyBg] = $companyMap[$companyKey] ?? [$companyKey ?: 'Powertech', '#475569', '#f1f5f9'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title><?= $asset ? h($asset['name']) . ' — ' . h($companyName) : 'ไม่พบอุปกรณ์' ?></title>
<meta name="theme-color" content="#0f172a">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💻</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Kanit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%}
body{
    font-family:'Kanit','Inter',-apple-system,BlinkMacSystemFont,sans-serif;
    background:#f1f5f9;
    color:#0f172a;
    min-height:100vh;
    -webkit-font-smoothing:antialiased;
    padding-bottom:100px;
}

/* ── Header ── */
.hdr{
    background:#fff;
    border-bottom:1px solid #e2e8f0;
    padding:12px 18px;
    display:flex;align-items:center;justify-content:space-between;
    position:sticky;top:0;z-index:20;
}
.hdr-left{display:flex;align-items:center;gap:10px}
.hdr-dot{
    width:8px;height:8px;border-radius:50%;
    background:<?= $companyColor ?>;
    box-shadow:0 0 0 3px <?= $companyBg ?>;
    flex-shrink:0;
}
.hdr-brand{font-size:13px;font-weight:700;color:#0f172a;letter-spacing:-.01em}
.hdr-sub{font-size:10px;color:#94a3b8;margin-top:0}
.hdr-company{
    font-size:11px;font-weight:700;
    color:<?= $companyColor ?>;
    background:<?= $companyBg ?>;
    padding:3px 10px;border-radius:20px;letter-spacing:.02em;
}

/* ── Hero ── */
.hero{
    position:relative;
    width:100%;aspect-ratio:4/3;max-height:260px;
    background:#1e293b;overflow:hidden;
}
.hero-img{width:100%;height:100%;object-fit:cover;display:block;filter:brightness(.92)}
.hero-placeholder{
    position:absolute;inset:0;
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
    color:rgba(255,255,255,.3);
}
.hero-placeholder-icon{font-size:56px;opacity:.5}
.hero-placeholder-text{font-size:11px;font-weight:600;letter-spacing:.12em;text-transform:uppercase}
.hero-overlay{
    position:absolute;inset:0;
    background:linear-gradient(to top, rgba(0,0,0,.72) 0%, rgba(0,0,0,.1) 55%, transparent 100%);
}
.hero-bottom{
    position:absolute;bottom:0;left:0;right:0;
    padding:14px 18px;
    display:flex;align-items:flex-end;justify-content:space-between;gap:10px;
}
.hero-id{
    font-family:'Inter',monospace;
    font-size:11px;font-weight:800;
    color:rgba(255,255,255,.7);
    letter-spacing:.12em;text-transform:uppercase;
    background:rgba(0,0,0,.35);
    padding:3px 8px;border-radius:5px;
    backdrop-filter:blur(4px);
}
.hero-status{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 10px;border-radius:20px;
    font-size:11px;font-weight:700;
    background:<?= $sb ?>;color:<?= $sc ?>;
    box-shadow:0 2px 8px rgba(0,0,0,.15);
}
.hero-status-dot{
    width:6px;height:6px;border-radius:50%;
    background:<?= $sc ?>;flex-shrink:0;
}

/* ── Page body ── */
.page{max-width:480px;margin:0 auto;padding:0 0 20px}

/* ── Name block ── */
.name-block{
    background:#fff;
    padding:20px 20px 16px;
    border-bottom:1px solid #f1f5f9;
}
.asset-name{
    font-size:20px;font-weight:800;
    color:#0f172a;line-height:1.2;
    letter-spacing:-.025em;margin-bottom:4px;
}
.asset-model{
    font-size:13px;color:#64748b;font-weight:500;
}

/* ── Info rows ── */
.info-card{background:#fff;margin-top:1px}
.info-section-title{
    padding:14px 20px 6px;
    font-size:9.5px;font-weight:700;
    color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;
}
.info-row{
    display:flex;align-items:center;
    padding:11px 20px;
    border-top:1px solid #f8fafc;
    gap:14px;
}
.info-row:first-of-type{border-top:none}
.info-icon{
    width:32px;height:32px;border-radius:9px;
    display:flex;align-items:center;justify-content:center;
    font-size:15px;flex-shrink:0;
}
.info-text{flex:1;min-width:0}
.info-label{font-size:10px;color:#94a3b8;font-weight:600;letter-spacing:.04em;text-transform:uppercase;margin-bottom:1px}
.info-value{font-size:13px;font-weight:600;color:#0f172a;line-height:1.3;word-break:break-word}
.info-value.mono{font-family:'Inter',monospace;font-size:12px;color:#334155;letter-spacing:.02em}

/* ── Warranty ── */
.warranty-card{
    background:#fff;margin-top:1px;
    padding:14px 20px;
    display:flex;align-items:center;gap:14px;
}
.warranty-icon{font-size:22px;flex-shrink:0}
.warranty-text{flex:1}
.warranty-title{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
.warranty-val{font-size:13px;font-weight:700}
.warranty-bar-wrap{margin-top:6px;height:4px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.warranty-bar{height:100%;border-radius:4px;transition:width .6s ease}

/* ── Sticky actions ── */
.sticky-actions{
    position:fixed;bottom:0;left:0;right:0;z-index:30;
    background:rgba(255,255,255,.95);
    backdrop-filter:blur(12px);
    border-top:1px solid #e2e8f0;
    padding:12px 16px max(12px, env(safe-area-inset-bottom));
    display:flex;flex-direction:column;gap:8px;
    max-width:480px;margin:0 auto;
}
.btn-repair{
    display:flex;align-items:center;justify-content:center;gap:10px;
    padding:15px;border-radius:13px;
    background:#ef4444;color:#fff;
    font-size:15px;font-weight:800;letter-spacing:-.01em;
    text-decoration:none;
    box-shadow:0 6px 20px rgba(239,68,68,.35);
    -webkit-tap-highlight-color:transparent;
    transition:transform .1s,opacity .15s;
}
.btn-repair:active{transform:scale(.98);opacity:.9}
.action-row{display:flex;gap:8px}
.btn-sm{
    flex:1;display:flex;align-items:center;justify-content:center;gap:7px;
    padding:10px 12px;border-radius:10px;
    font-size:12.5px;font-weight:700;
    text-decoration:none;-webkit-tap-highlight-color:transparent;
    transition:opacity .15s;
}
.btn-sm:active{opacity:.8}
.btn-line{background:#06c755;color:#fff}
.btn-login{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0}

/* ── Empty state ── */
.empty{text-align:center;padding:80px 24px;background:#fff}
.empty-icon{font-size:56px;opacity:.3;margin-bottom:14px}
.empty-title{font-size:16px;font-weight:700;color:#334155;margin-bottom:6px}
.empty-sub{font-size:13px;color:#94a3b8}

/* ── Footer ── */
.footer{text-align:center;padding:16px;font-size:10px;color:#cbd5e1;letter-spacing:.06em;text-transform:uppercase}

/* ── Fade-in ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.page{animation:fadeUp .35s ease both}
</style>
</head>
<body>

<!-- Header -->
<div class="hdr">
    <div class="hdr-left">
        <div class="hdr-dot"></div>
        <div>
            <div class="hdr-brand">Powertech IT</div>
            <div class="hdr-sub">Asset Management</div>
        </div>
    </div>
    <?php if ($companyKey): ?>
    <span class="hdr-company"><?= h($companyName) ?></span>
    <?php endif; ?>
</div>

<div class="page">

<?php if ($asset): ?>

    <!-- Hero photo -->
    <div class="hero">
        <?php if ($imageUrl): ?>
        <img class="hero-img" id="hero-img" src="<?= h($imageUrl) ?>" alt=""
             onerror="this.style.display='none';document.getElementById('hero-ph').style.display='flex'">
        <?php endif; ?>
        <div class="hero-placeholder" id="hero-ph" style="display:<?= $imageUrl ? 'none' : 'flex' ?>">
            <div class="hero-placeholder-icon">🖥</div>
            <div class="hero-placeholder-text">No Image</div>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-bottom">
            <span class="hero-id"><?= h($asset['equipment_id']) ?></span>
            <span class="hero-status">
                <span class="hero-status-dot"></span>
                <?= h($status ?: 'ไม่ระบุ') ?>
            </span>
        </div>
    </div>

    <!-- Name block -->
    <div class="name-block">
        <div class="asset-name"><?= h($asset['name']) ?></div>
        <?php $bm = trim(($asset['brand'] ?? '') . ' ' . ($asset['model'] ?? '')); ?>
        <?php if ($bm): ?><div class="asset-model"><?= h($bm) ?></div><?php endif; ?>
    </div>

    <!-- Info rows -->
    <?php
    $rows = [];
    if ($asset['location'])      $rows[] = ['bg'=>'#eff6ff','icon'=>'📍','label'=>'สถานที่','val'=>$asset['location'],'mono'=>false];
    if ($asset['borrower'])      $rows[] = ['bg'=>'#f0fdf4','icon'=>'👤','label'=>'ผู้ถือครอง','val'=>$asset['borrower'],'mono'=>false];
    if ($asset['owning_dept'])   $rows[] = ['bg'=>'#fdf4ff','icon'=>'🏢','label'=>'แผนก','val'=>$asset['owning_dept'],'mono'=>false];
    if ($asset['cat_name'])      $rows[] = ['bg'=>'#fff7ed','icon'=>'🗂','label'=>'ประเภท','val'=>$asset['cat_name'],'mono'=>false];
    if ($asset['serial_number']) $rows[] = ['bg'=>'#f8fafc','icon'=>'🔑','label'=>'Serial Number','val'=>$asset['serial_number'],'mono'=>true];
    if ($asset['hostname'])      $rows[] = ['bg'=>'#f8fafc','icon'=>'💻','label'=>'Hostname','val'=>$asset['hostname'],'mono'=>true];
    if ($asset['ip_address'])    $rows[] = ['bg'=>'#f8fafc','icon'=>'🌐','label'=>'IP Address','val'=>$asset['ip_address'],'mono'=>true];
    ?>
    <?php if ($rows): ?>
    <div class="info-card" style="margin-top:1px">
        <div class="info-section-title">ข้อมูลอุปกรณ์</div>
        <?php foreach ($rows as $r): ?>
        <div class="info-row">
            <div class="info-icon" style="background:<?= $r['bg'] ?>"><?= $r['icon'] ?></div>
            <div class="info-text">
                <div class="info-label"><?= h($r['label']) ?></div>
                <div class="info-value<?= $r['mono'] ? ' mono' : '' ?>"><?= h($r['val']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Warranty -->
    <?php if ($warrantyDays !== null):
        $wIcon  = $warrantyExpired ? '⚠️' : ($warrantyDays <= 90 ? '🟡' : '🛡️');
        $wColor = $warrantyExpired ? '#dc2626' : ($warrantyDays <= 90 ? '#d97706' : '#059669');
        $wBg    = $warrantyExpired ? '#fef2f2' : ($warrantyDays <= 90 ? '#fffbeb' : '#f0fdf4');
        if (!$warrantyExpired) {
            $totalLife = 365 * 3;
            $pct = min(100, max(4, round($warrantyDays / $totalLife * 100)));
        }
    ?>
    <div class="warranty-card" style="margin-top:1px">
        <div class="warranty-icon"><?= $wIcon ?></div>
        <div class="warranty-text">
            <div class="warranty-title">ประกัน / Warranty</div>
            <div class="warranty-val" style="color:<?= $wColor ?>">
                <?php if ($warrantyExpired): ?>
                    หมดอายุแล้ว <?= abs($warrantyDays) ?> วัน
                <?php else: ?>
                    เหลือ <?= number_format($warrantyDays) ?> วัน
                    <span style="font-weight:500;color:#94a3b8;font-size:11px">
                        (ถึง <?= date('d M Y', strtotime($asset['warranty_expires_on'])) ?>)
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!$warrantyExpired): ?>
            <div class="warranty-bar-wrap">
                <div class="warranty-bar" style="width:<?= $pct ?>%;background:<?= $wColor ?>"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">Powertech · <?= h($companyName) ?> · <?= date('Y') ?></div>

    <!-- Sticky action bar -->
    <div class="sticky-actions">
        <a class="btn-repair" href="<?= h($ticketUrl) ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            แจ้งซ่อม / แจ้งปัญหา
        </a>
        <div class="action-row">
            <?php if ($lineGroupUrl): ?>
            <a class="btn-sm btn-line" href="<?= h($lineGroupUrl) ?>" target="_blank">
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M10 0C4.477 0 0 3.582 0 8c0 3.064 1.944 5.74 4.866 7.242-.068.245-.437 1.574-.5 1.815-.079.295.108.292.226.213.093-.063 1.479-.978 2.08-1.375A11.5 11.5 0 0010 16c5.523 0 10-3.582 10-8S15.523 0 10 0"/></svg>
                LINE IT
            </a>
            <?php endif; ?>
            <a class="btn-sm btn-login" href="<?= h($selfBase . '/index.html') ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                หน้าแรก
            </a>
        </div>
    </div>

<?php else: ?>

    <div class="empty">
        <div class="empty-icon">🔍</div>
        <div class="empty-title">ไม่พบข้อมูลอุปกรณ์</div>
        <div class="empty-sub">รหัส: <b><?= h($equipmentId ?: '-') ?></b></div>
    </div>

<?php endif; ?>

</div>

</body>
</html>
