<?php
/**
 * asset_print.php — หน้าพิมพ์รวมของทะเบียนพัสดุ IT (แทนกล่องพิมพ์สติกเกอร์ 2 ชุดเดิมใน admin.html)
 *   ?mode=labels&ids=1,2,3[&size=m|s][&layout=a4|roll]   สติกเกอร์ QR ติดเครื่อง
 *   ?mode=audit&company=PTA[&group=dept|location][&ids=…][&retired=1]   ใบตรวจนับทรัพย์สิน
 * TISAX: ต้องล็อกอิน + มีสิทธิ์จัดการพัสดุ · ทุกครั้งที่กดพิมพ์จะลง log (log_print_event)
 */
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function deny($msg, $code) {
    http_response_code($code);
    die('<p style="font-family:sans-serif;color:#dc2626;padding:40px">' . h($msg) . ' <a href="login.html">เข้าสู่ระบบ</a></p>');
}

if (empty($_SESSION['user_id'])) deny('กรุณาเข้าสู่ระบบก่อนพิมพ์เอกสาร', 401);
$perms = $_SESSION['effective_permissions'] ?? [];
$canManage = ($_SESSION['role'] ?? '') === 'admin' || in_array('*', $perms, true) || in_array('asset.manage', $perms, true);
if (!$canManage) deny('ไม่มีสิทธิ์พิมพ์เอกสารทะเบียนพัสดุ', 403);

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

$mode    = ($_GET['mode'] ?? 'labels') === 'audit' ? 'audit' : 'labels';
$size    = ($_GET['size'] ?? 'm') === 's' ? 's' : 'm';
$layout  = ($_GET['layout'] ?? 'a4') === 'roll' ? 'roll' : 'a4';
$group   = ($_GET['group'] ?? 'dept') === 'location' ? 'location' : 'dept';
$retired = !empty($_GET['retired']);
$company = strtoupper(trim($_GET['company'] ?? ''));
if (!in_array($company, ['PTA', 'PT4', 'PTE'], true)) $company = '';
$ids = array_slice(array_values(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))), fn($v) => $v > 0)), 0, 1000);

if ($mode === 'labels' && !$ids) die('ไม่ได้เลือกพัสดุ');
if ($mode === 'audit' && !$company && !$ids) die('กรุณาเลือกบริษัท');

// ── ดึงพัสดุ ──
$where = []; $types = ''; $params = [];
if ($ids) { $where[] = 'a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $types .= str_repeat('i', count($ids)); $params = array_merge($params, $ids); }
if ($company) { $where[] = 'a.company = ?'; $types .= 's'; $params[] = $company; }
// ใบตรวจนับไม่ต้องนับของที่ตัดจำหน่าย/เลิกใช้แล้ว (เว้นแต่ขอ)
if ($mode === 'audit' && !$retired) $where[] = "COALESCE(a.status,'') NOT IN ('ตัดจำหน่าย','เลิกใช้งาน')";
$sql = "SELECT a.id, a.equipment_id, a.name, a.brand, a.model, a.serial_number, a.company, a.location, a.status,
               a.borrower, a.custodian_type, c.cat_name, d.dept_name, e.name AS custodian_name, cd.dept_name AS custodian_dept
        FROM it_assets a
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
        LEFT JOIN departments d ON a.owning_department = d.dept_id
        LEFT JOIN employees e ON a.custodian_employee_id = e.id
        LEFT JOIN departments cd ON a.custodian_dept_id = cd.dept_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . " ORDER BY a.company, d.dept_name, a.equipment_id";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$assets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
if (!$assets) die('ไม่พบพัสดุที่เลือก');

// ── ข้อมูลบริษัท / ลิงก์ QR ──
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('company_info_PTA','company_info_PT4','company_info_PTE','asset_scan_url_primary')");
while ($r = $res->fetch_assoc()) $settings[$r['setting_key']] = $r['setting_value'];
$CO = [
    'PTA' => ['color' => '#059669', 'en' => 'Powertech Engine Assembly Co., Ltd.', 'th' => 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด'],
    'PT4' => ['color' => '#dc2626', 'en' => 'Powertech 2004 Co., Ltd.', 'th' => 'บริษัท พาวเวอร์เทค 2004 จำกัด'],
    'PTE' => ['color' => '#2563eb', 'en' => 'Powertech Energy Solutions Co., Ltd.', 'th' => 'บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด'],
];
foreach ($CO as $k => &$v) {
    $info = json_decode($settings['company_info_' . $k] ?? '', true) ?: [];
    if (!empty($info['name_en'])) $v['en'] = $info['name_en'];
    if (!empty($info['name_th'])) $v['th'] = $info['name_th'];
}
unset($v);
// QR ต้องเปิดได้จากมือถือในวง LAN → ใช้ URL ที่ตั้งไว้ในระบบ ไม่ใช่ localhost ของเครื่องที่สั่งพิมพ์
$scanBase = rtrim($settings['asset_scan_url_primary'] ?? '', '/');
if ($scanBase === '') $scanBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

function holderOf($a) {
    if (($a['custodian_type'] ?? '') === 'department') return 'แผนก ' . ($a['custodian_dept'] ?: ($a['dept_name'] ?: '-'));
    return $a['custodian_name'] ?: ($a['borrower'] ?: '');
}
function qs($over) { return h('asset_print.php?' . http_build_query(array_merge($_GET, $over))); }

$printedBy = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$now = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
$thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
$thDate = (int)$now->format('j') . ' ' . $thMonths[(int)$now->format('n')] . ' ' . ((int)$now->format('Y') + 543);

$docCo = $company ?: ($assets[0]['company'] ?? '');
$docNo = $mode === 'audit' ? 'AUD-' . ($docCo ?: 'ALL') . '-' . $now->format('Ymd-Hi') : 'LBL-' . $now->format('Ymd-Hi');
// ── ใบตรวจนับ: หน้าตามาตรฐานเดียวกับทุกใบ (js/doc-print.js · แนวนอน หลายหน้าได้ · เลขฟอร์ม audit) ──
if ($mode === 'audit') {
    $groups = [];
    foreach ($assets as $a) {
        $key = $group === 'location' ? (trim((string)$a['location']) ?: 'ไม่ระบุที่ตั้ง') : ($a['dept_name'] ?: 'ไม่ระบุแผนก');
        $groups[$key][] = [
            'code' => $a['equipment_id'], 'name' => $a['name'], 'model' => trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? '')), 'cat' => $a['cat_name'],
            'sn' => $a['serial_number'], 'holder' => holderOf($a), 'status' => $a['status'],
            // ที่ตั้ง "PTA/PT4/PTE" คือค่าเก่าที่ใส่ชื่อบริษัทแทนที่ตั้ง → ไม่แสดง
            'other' => $group === 'location' ? $a['dept_name'] : (preg_match('/^(PTA|PT4|PTE)$/i', (string)$a['location']) ? '' : $a['location']),
        ];
    }
    ksort($groups, SORT_NATURAL);
    $seg = fn($items) => '<span class="seg">' . implode('', array_map(fn($it) => '<a href="' . qs($it[0]) . '" class="' . ($it[2] ? 'on' : '') . '">' . h($it[1]) . '</a>', $items)) . '</span>';
    $bar = 'จัดกลุ่มตาม ' . $seg([[['group' => 'dept'], 'แผนก', $group === 'dept'], [['group' => 'location'], 'ที่ตั้ง', $group === 'location']])
         . (!$ids ? ' ' . $seg([[['retired' => ''], 'เฉพาะที่ใช้อยู่', !$retired], [['retired' => '1'], 'รวมที่เลิกใช้', $retired]]) : '');
    $data = ['company' => $docCo, 'docNo' => $docNo, 'printedBy' => $printedBy, 'group' => $group, 'groups' => $groups, 'count' => count($assets), 'bar' => $bar,
             'log' => ['count' => count($assets), 'company' => $docCo, 'ids' => array_slice(array_map('intval', array_column($assets, 'id')), 0, 200)]];
    header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>ใบตรวจนับทรัพย์สิน — <?= h($docNo) ?></title></head>
<body>
<p style="font-family:sans-serif;padding:40px;color:#64748b">กำลังเตรียมเอกสาร...</p>
<script src="js/doc-print.js?v=20261022"></script>
<script>
(async () => {
    const D = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const { esc, section, thDate } = DocPrint;
    const box = '<span style="display:inline-block;width:9px;height:9px;border:1px solid #334155;border-radius:2px;margin:0 2px 0 5px;vertical-align:-1px"></span>';
    let n = 0;
    const body = Object.entries(D.groups).map(([g, rows]) => `<tr class="grp"><td colspan="9">${esc(g)} <span style="font-weight:400;color:#64748b">· ${rows.length} รายการ</span></td></tr>`
        + rows.map(a => `<tr><td style="text-align:center;color:#64748b">${++n}</td><td class="mono" style="font-weight:700">${esc(a.code)}</td>
            <td>${esc(a.name)}${a.model ? `<div style="font-size:7.6pt;color:#64748b">${esc(a.model)}${a.cat ? ' · ' + esc(a.cat) : ''}</div>` : ''}</td>
            <td class="mono" style="font-size:7.6pt">${esc(a.sn || '-')}</td><td>${esc(a.holder || '— ว่าง —')}</td><td>${esc(a.other || '-')}</td><td>${esc(a.status || '-')}</td>
            <td style="white-space:nowrap;font-size:8pt">${box}พบ${box}ไม่พบ${box}ชำรุด</td><td></td></tr>`).join('')).join('');
    const blank = '<span style="display:block;border-bottom:1px dotted #94a3b8;height:1.2em"></span>';
    const fill = '<span style="display:inline-block;width:40px;border-bottom:1px dotted #64748b"></span>';

    const html = await DocPrint.build({
        company: D.company, formKey: 'audit', classified: true, toolbar: true, barHtml: D.bar, size: 'land', flow: true,
        docNo: D.docNo, dateText: 'พิมพ์ ' + thDate(new Date(), true) + (D.printedBy ? ' โดย ' + D.printedBy : ''),
        title: { th: 'ใบตรวจนับทรัพย์สิน IT', en: `IT Asset Physical Count Sheet · ${D.count} รายการ · ${Object.keys(D.groups).length} ${D.group === 'location' ? 'ที่ตั้ง' : 'แผนก'}` },
        info: [['วันที่ตรวจ', blank, true], ['รอบการตรวจ', blank, true], ['ผู้ตรวจนับ', blank, true], ['ผู้ร่วมตรวจ', blank, true]],
        blocks: [
            `<div class="dp-sec" style="break-inside:auto"><table class="dp-tbl"><thead><tr><th style="width:26px">#</th><th style="width:130px">รหัสพัสดุ</th><th>อุปกรณ์ / ยี่ห้อ-รุ่น</th><th>S/N</th>
                <th>ผู้ถือครอง</th><th>${D.group === 'location' ? 'แผนก' : 'ที่ตั้ง'}</th><th>สถานะในระบบ</th><th style="width:150px">ผลตรวจ</th><th style="width:130px">หมายเหตุ</th></tr></thead><tbody>${body}</tbody></table></div>`,
            `<div class="dp-note" style="break-inside:avoid"><b>สรุปผล</b> ทั้งหมด <b>${D.count}</b> รายการ · พบ ${fill} · ไม่พบ ${fill} · ชำรุด ${fill} · ไม่อยู่ในทะเบียน (พบเพิ่ม) ${fill}
                <div style="color:#64748b;margin-top:2px">รายการที่ "ไม่พบ" หรือ "ชำรุด" ให้แจ้งฝ่าย IT ภายใน 3 วันทำการ เพื่อปรับปรุงทะเบียน</div></div>`,
        ],
        sigs: [{ role: 'ผู้ตรวจนับ / Counted By' }, { role: 'หัวหน้าแผนก / ผู้รับรอง' }, { role: 'ฝ่าย IT (ผู้ดูแลทะเบียน)' }],
    });
    document.open(); document.write(html); document.close();
    // TISAX: เก็บร่องรอยว่าใครพิมพ์ใบตรวจนับอะไร เมื่อไร (ผูกหลังเขียนหน้าใหม่ — document.open ล้าง listener เดิม)
    let logged = false;
    window.addEventListener('beforeprint', () => {
        if (logged) return; logged = true;
        const fd = new FormData();
        fd.append('document_type', 'asset_audit_sheet'); fd.append('document_id', D.docNo); fd.append('event_type', 'PRINT');
        fd.append('client_meta', JSON.stringify(D.log));
        navigator.sendBeacon ? navigator.sendBeacon('api.php?action=log_print_event', fd) : fetch('api.php?action=log_print_event', { method: 'POST', body: fd });
    });
})();
</script>
</body>
</html>
<?php
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สติกเกอร์พัสดุ — <?= h($docNo) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sarabun', system-ui, sans-serif; background: #f1f5f9; color: #0f172a; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .mono { font-family: 'JetBrains Mono', Consolas, monospace; }

    /* แถบเครื่องมือ (จอเท่านั้น) */
    .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e2e8f0; }
    .bar h1 { font-size: 15px; font-weight: 700; margin-right: auto; }
    .bar h1 small { font-weight: 400; color: #64748b; margin-left: 6px; font-size: 12.5px; }
    .seg { display: inline-flex; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; }
    .seg a { padding: 6px 12px; font-size: 13px; font-weight: 600; color: #475569; text-decoration: none; background: #fff; }
    .seg a + a { border-left: 1px solid #cbd5e1; }
    .seg a.on { background: #0f172a; color: #fff; }
    .seg-lbl { font-size: 12px; color: #64748b; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font: inherit; font-size: 13.5px; font-weight: 700; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155; text-decoration: none; }
    .btn.primary { background: #2563eb; border-color: #2563eb; color: #fff; }
    .hint { width: 100%; font-size: 12px; color: #64748b; }
    .stage { padding: 20px 0 40px; }

    /* ═════ สติกเกอร์ ═════ */
    .sheet { background: #fff; margin: 0 auto 16px; box-shadow: 0 1px 4px rgba(15,23,42,.12); display: grid; }
    .sheet.a4-m { width: 210mm; height: 297mm; grid-template-columns: repeat(3, 70mm); grid-auto-rows: 42.3mm; padding: 0.45mm 0; align-content: start; }
    .sheet.a4-s { width: 210mm; height: 297mm; grid-template-columns: repeat(4, 52.5mm); grid-auto-rows: 29.7mm; align-content: start; }
    .cell { display: flex; align-items: center; justify-content: center; outline: 0.2mm dashed #e2e8f0; outline-offset: -0.1mm; overflow: hidden; }
    .roll { display: flex; flex-wrap: wrap; gap: 6mm; justify-content: center; }
    .roll .cell { background: #fff; box-shadow: 0 1px 4px rgba(15,23,42,.12); outline: 0; }

    .lbl { display: flex; flex-direction: column; overflow: hidden; color: #000; }
    .lbl-top { display: flex; align-items: center; gap: 1.2mm; font-weight: 700; white-space: nowrap; overflow: hidden; }
    .lbl-co { color: #fff; border-radius: 0.8mm; padding: 0 1.1mm; font-weight: 800; letter-spacing: .02em; }
    .lbl-coname { color: #334155; overflow: hidden; text-overflow: ellipsis; text-transform: uppercase; letter-spacing: .03em; }
    .lbl-body { flex: 1; display: flex; align-items: center; gap: 2mm; min-height: 0; }
    .lbl-info { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: center; }
    .lbl-code { font-weight: 800; line-height: 1.05; white-space: nowrap; letter-spacing: -.01em; }
    .lbl-name { font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lbl-sn { color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lbl-qr { flex-shrink: 0; line-height: 0; }
    .lbl-qr img, .lbl-qr canvas { width: 100% !important; height: 100% !important; }
    .lbl-foot { display: flex; justify-content: space-between; color: #475569; border-top: 0.2mm solid #cbd5e1; white-space: nowrap; }

    /* M = 66×38 (ลง 70×42.3 / ม้วน 70×40) */
    .lbl.m { width: 66mm; height: 38mm; padding: 1.8mm 2mm 1.4mm; }
    .lbl.m .lbl-top { font-size: 6.2pt; margin-bottom: 1mm; }
    .lbl.m .lbl-code { font-size: 10.5pt; }
    .lbl.m .lbl-name { font-size: 7pt; margin-top: 0.8mm; }
    .lbl.m .lbl-sn { font-size: 6pt; margin-top: 0.4mm; }
    .lbl.m .lbl-qr { width: 21mm; height: 21mm; }
    .lbl.m .lbl-foot { font-size: 5.2pt; padding-top: 0.7mm; margin-top: 0.8mm; }
    /* S = 48×24 (ลง 52.5×29.7 / ม้วน 50×25) */
    .lbl.s { width: 48mm; height: 24mm; padding: 1.2mm 1.5mm; }
    .lbl.s .lbl-top { font-size: 5.2pt; margin-bottom: 0.6mm; }
    .lbl.s .lbl-coname { display: none; }
    .lbl.s .lbl-code { font-size: 7.6pt; }
    .lbl.s .lbl-name { font-size: 5.6pt; margin-top: 0.6mm; }
    .lbl.s .lbl-sn, .lbl.s .lbl-foot { display: none; }
    .lbl.s .lbl-qr { width: 17mm; height: 17mm; }

    @media print {
        body { background: #fff; }
        .bar { display: none !important; }
        .stage { padding: 0; }
        .sheet, .roll .cell { box-shadow: none; margin: 0; }
        .cell { outline: 0; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .roll { display: block; }
        .roll .cell { page-break-after: always; }
        .roll .cell:last-child { page-break-after: auto; }
    }
<?php if ($layout === 'roll'): ?>
    @page { size: <?= $size === 's' ? '50mm 25mm' : '70mm 40mm' ?>; margin: 0; }
    @media print { .roll .cell { width: <?= $size === 's' ? '50mm' : '70mm' ?>; height: <?= $size === 's' ? '25mm' : '40mm' ?>; } }
<?php else: ?>
    @page { size: A4 portrait; margin: 0; }
<?php endif; ?>
</style>
</head>
<body>
<div class="bar">
    <h1><i class="fa fa-tags" style="color:#2563eb;margin-right:6px"></i>พิมพ์สติกเกอร์พัสดุ<small><?= count($assets) ?> ชิ้น</small></h1>
    <span class="seg-lbl">ขนาด</span>
    <div class="seg">
        <a href="<?= qs(['size' => 'm']) ?>" class="<?= $size === 'm' ? 'on' : '' ?>">ปกติ 70×40</a>
        <a href="<?= qs(['size' => 's']) ?>" class="<?= $size === 's' ? 'on' : '' ?>">เล็ก 50×25</a>
    </div>
    <span class="seg-lbl">กระดาษ</span>
    <div class="seg">
        <a href="<?= qs(['layout' => 'a4']) ?>" class="<?= $layout === 'a4' ? 'on' : '' ?>">A4 แผ่นสติกเกอร์</a>
        <a href="<?= qs(['layout' => 'roll']) ?>" class="<?= $layout === 'roll' ? 'on' : '' ?>">เครื่องพิมพ์ม้วน</a>
    </div>
    <button class="btn primary" onclick="window.print()"><i class="fa fa-print"></i> พิมพ์</button>
    <button class="btn" onclick="window.close()">ปิด</button>
    <div class="hint">
        <?= $layout === 'a4'
            ? ($size === 'm' ? 'ใช้กับสติกเกอร์ A4 แบบ 21 ดวง (70×42.3 มม.)' : 'ใช้กับสติกเกอร์ A4 แบบ 40 ดวง (52.5×29.7 มม.)') . ' · ตอนพิมพ์ตั้ง "ขนาดจริง / 100%" และปิดหัว-ท้ายกระดาษ'
            : 'ตั้งขนาดกระดาษในไดรเวอร์เครื่องพิมพ์ให้ตรง ' . ($size === 'm' ? '70×40' : '50×25') . ' มม.' ?>
    </div>
</div>
<div class="stage">
<?php
    $perSheet = $size === 's' ? 40 : 21;
    $chunks = $layout === 'a4' ? array_chunk($assets, $perSheet) : [$assets];
    foreach ($chunks as $chunk):
        echo $layout === 'a4' ? '<div class="sheet a4-' . $size . '">' : '<div class="roll">';
        foreach ($chunk as $a):
            $co = $CO[$a['company']] ?? ['color' => '#334155', 'en' => 'Powertech'];
            $model = trim(($a['brand'] ?? '') . ' ' . ($a['model'] ?? ''));
            $code = (string)$a['equipment_id'];
            // รหัสยาวให้ตัวเล็กลงเอง ไม่ล้นกล่อง
            // ตัว mono กว้าง ≈ 0.6em · พื้นที่ข้าง QR: M ≈ 39 มม. / S ≈ 26 มม.
            $codePt = min($size === 's' ? 7.6 : 10.5, ($size === 's' ? 26 : 39) / (max(1, strlen($code)) * 0.6 * 0.3528));
?>
    <div class="cell"><div class="lbl <?= $size ?>">
        <div class="lbl-top"><span class="lbl-co" style="background:<?= h($co['color']) ?>"><?= h($a['company'] ?: 'IT') ?></span><span class="lbl-coname"><?= h($co['en']) ?></span></div>
        <div class="lbl-body">
            <div class="lbl-info">
                <div class="lbl-code mono" style="font-size:<?= round($codePt, 2) ?>pt"><?= h($code) ?></div>
                <div class="lbl-name"><?= h($a['name']) ?></div>
                <div class="lbl-sn"><?= $a['serial_number'] ? 'S/N ' . h($a['serial_number']) : h($model) ?></div>
            </div>
            <div class="lbl-qr" data-qr="<?= h($scanBase . '/asset_scan.html?id=' . (int)$a['id']) ?>"></div>
        </div>
        <div class="lbl-foot"><span>ทรัพย์สินบริษัท · ห้ามแกะ</span><span>สแกนดูข้อมูล / แจ้งซ่อม</span></div>
    </div></div>
<?php   endforeach; echo '</div>'; endforeach; ?>
</div>


<script>
    // วาด QR ทุกดวง (ภาพคมพอสำหรับ 300dpi) แล้วค่อยให้พิมพ์ได้
    document.querySelectorAll('[data-qr]').forEach(el => {
        new QRCode(el, { text: el.dataset.qr, width: 256, height: 256, correctLevel: QRCode.CorrectLevel.M });
    });
    // TISAX: เก็บร่องรอยว่าใครพิมพ์เอกสารทะเบียนอะไร เมื่อไร
    let _logged = false;
    window.addEventListener('beforeprint', () => {
        if (_logged) return; _logged = true;
        const fd = new FormData();
        fd.append('document_type', 'asset_labels');
        fd.append('document_id', <?= json_encode($docNo) ?>);
        fd.append('event_type', 'PRINT');
        fd.append('client_meta', <?= json_encode(json_encode(['count' => count($assets), 'company' => $docCo, 'ids' => array_slice(array_map('intval', array_column($assets, 'id')), 0, 200)])) ?>);
        navigator.sendBeacon ? navigator.sendBeacon('api.php?action=log_print_event', fd) : fetch('api.php?action=log_print_event', { method: 'POST', body: fd });
    });
</script>
</body>
</html>
