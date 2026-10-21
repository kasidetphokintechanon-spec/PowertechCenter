<?php
/**
 * print_asset.php — ใบมอบ/รับทรัพย์สิน / Asset Handover Form (A4 แผ่นเดียว)
 * Usage: print_asset.php?id={it_asset_db_id}
 *   or:  print_asset.php?ids=1,2,3   (multiple assets in one form)
 * หน้าตามาตรฐานเดียวกับทุกใบในระบบ: PHP ส่งข้อมูล → js/doc-print.js สร้างใบ (เลขฟอร์มจาก settings: handover)
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('<p style="font-family:sans-serif;color:#dc2626;padding:40px">กรุณาเข้าสู่ระบบก่อนพิมพ์เอกสาร <a href="login.html">Login</a></p>');
}

$hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1', '::1'])));
$conn = null;
foreach ($hosts as $host) {
    $c = mysqli_init(); if (!$c) continue;
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}
if (!$conn) die('Database connection failed');
$conn->set_charset('utf8mb4');

// Support single id or comma-separated ids
$rawIds = trim($_GET['ids'] ?? $_GET['id'] ?? '');
$ids = array_slice(array_values(array_filter(array_map('intval', explode(',', $rawIds)), fn($v) => $v > 0)), 0, 200);
if (empty($ids)) die('Missing id parameter');

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$stmt = $conn->prepare("
    SELECT a.id, a.equipment_id, a.asset_id, a.name, a.brand, a.model, a.serial_number, a.status, a.company,
           a.location, a.borrower, c.cat_name, d.dept_name AS department_name,
           COALESCE(NULLIF(e.name, ''), a.borrower) AS holder
    FROM it_assets a
    LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
    LEFT JOIN departments d ON a.owning_department = d.dept_id
    LEFT JOIN employees e ON a.custodian_employee_id = e.id
    WHERE a.id IN ($placeholders)
    ORDER BY a.id
");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
$assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($assets)) { http_response_code(404); die('ไม่พบทรัพย์สินที่ระบุ'); }

// QR ต้องเปิดได้จากมือถือในวง LAN → ใช้ URL สแกนที่ตั้งไว้ในระบบ (เหมือนสติกเกอร์)
$st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'asset_scan_url_primary' LIMIT 1");
$st->execute();
$scanBase = rtrim((string)($st->get_result()->fetch_assoc()['setting_value'] ?? ''), '/');
$st->close();
if ($scanBase === '') $scanBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$data = [
    'docNo'  => 'AST-' . date('Y') . '-' . str_pad($assets[0]['id'], 4, '0', STR_PAD_LEFT),
    'qr'     => $scanBase . '/asset_scan.html?id=' . (int)$assets[0]['id'],
    'assets' => $assets,
];
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบมอบ/รับทรัพย์สิน — <?= htmlspecialchars($data['docNo'], ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
<p style="font-family:sans-serif;padding:40px;color:#64748b">กำลังเตรียมเอกสาร...</p>
<script src="js/vendor/qrcode.min.js"></script>
<script src="js/doc-print.js?v=20261022"></script>
<script>
(async () => {
    const D = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const { esc, section, thDate } = DocPrint;
    const a0 = D.assets[0];
    const rows = D.assets.map((a, i) => `<tr><td style="text-align:center">${i + 1}</td><td class="mono">${esc(a.equipment_id || a.asset_id || '-')}</td>
        <td>${esc(a.name)}${a.cat_name ? `<div style="font-size:7.6pt;color:#64748b">${esc(a.cat_name)}</div>` : ''}</td>
        <td>${esc([a.brand, a.model].filter(Boolean).join(' ') || '-')}</td><td class="mono">${esc(a.serial_number || '-')}</td><td>${esc(a.status || 'ดี')}</td></tr>`);
    // เว้นแถวว่างให้เขียนเพิ่มด้วยมือได้ (อย่างน้อย 4 แถว)
    for (let i = D.assets.length; i < 4; i++) rows.push(`<tr><td style="text-align:center;color:#cbd5e1">${i + 1}</td><td></td><td>&nbsp;</td><td></td><td></td><td></td></tr>`);

    const html = await DocPrint.build({
        company: a0.company, formKey: 'handover', classified: true, toolbar: true,
        docNo: D.docNo, dateText: 'วันที่มอบ ' + thDate(new Date()), qr: D.qr,
        title: { th: 'ใบมอบ / รับทรัพย์สิน', en: 'Asset Handover Form' },
        info: [['ผู้รับทรัพย์สิน', a0.holder], ['แผนก / บริษัท', [a0.department_name, a0.company].filter(Boolean).join(' · ')],
               ['สถานที่ใช้งาน', a0.location], ['จำนวนรายการ', D.assets.length + ' รายการ']],
        blocks: [
            section('รายการทรัพย์สิน / ASSETS', `<table class="dp-tbl"><thead><tr><th style="width:8%;text-align:center">ลำดับ</th><th style="width:20%">รหัสทรัพย์สิน</th><th>รายการ</th>
                <th style="width:20%">ยี่ห้อ / รุ่น</th><th style="width:17%">Serial Number</th><th style="width:10%">สภาพ</th></tr></thead><tbody>${rows.join('')}</tbody></table>`, { html: true }),
            // TISAX: ผู้รับต้องรับทราบความรับผิดชอบต่อทรัพย์สินและข้อมูลก่อนเซ็น
            `<div class="dp-note"><b>เงื่อนไขการรับมอบ</b> — ผู้รับทรัพย์สินรับทราบและยินยอมว่าจะดูแลรักษาทรัพย์สินของบริษัทด้วยความระมัดระวัง ใช้เพื่องานของบริษัทเท่านั้น
                หากสูญหายหรือเสียหายจากการใช้งานที่ไม่เหมาะสม ผู้รับจะต้องรับผิดชอบตามระเบียบของบริษัท และเมื่อย้ายงานหรือพ้นสภาพพนักงานจะส่งคืนฝ่าย IT</div>`,
        ],
        sigs: [{ role: 'ผู้มอบ / Issued By' }, { role: 'ผู้รับ / Received By', name: a0.holder }, { role: 'ผู้อนุมัติ / Approved By' }],
    });
    document.open(); document.write(html); document.close();
})();
</script>
</body>
</html>
