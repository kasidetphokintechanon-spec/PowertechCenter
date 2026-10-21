<?php
/**
 * server_check_print.php — ใบตรวจห้อง Server ประจำเดือน (A4 แนวนอน แผ่นเดียว — แกนกลาง js/doc-print.js · เลขฟอร์ม server_check)
 * Usage: server_check_print.php?room_id={id}&month={1-12}&year={YYYY}
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('<p style="font-family:sans-serif;color:#dc2626;padding:40px">กรุณาเข้าสู่ระบบก่อนพิมพ์เอกสาร <a href="login.html">Login</a></p>');
}

$hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1'])));
$conn  = null;
foreach ($hosts as $host) {
    $c = mysqli_init(); if (!$c) continue;
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}
if (!$conn) die('Database connection failed');
$conn->set_charset('utf8mb4');

$room_id  = (int)($_GET['room_id'] ?? 0);
$year     = (int)($_GET['year']    ?? date('Y'));
$month    = (int)($_GET['month']   ?? date('n'));
$isBlank  = !empty($_GET['blank']); // ?blank=1 → ฟอร์มเปล่าสำหรับกรอกมือ
$isBW     = !empty($_GET['bw']);    // ?bw=1    → พิมพ์ขาวดำ

if (!$room_id) die('Missing room_id parameter');
if ($month < 1 || $month > 12) $month = (int)date('n');
if ($year  < 2000 || $year > 2100) $year = (int)date('Y');

$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

$room = $conn->query("SELECT * FROM sc_rooms WHERE id=$room_id")->fetch_assoc();
if (!$room) die('ไม่พบห้อง Server Room ID: ' . $room_id);

$items = $conn->query("SELECT * FROM sc_items WHERE room_id=$room_id ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// Load holidays for this month
$phRows    = $conn->query("SELECT holiday_date, name FROM company_holidays WHERE holiday_date BETWEEN '$from' AND '$to'")->fetch_all(MYSQLI_ASSOC);
$holidayMap = [];
foreach ($phRows as $ph) $holidayMap[$ph['holiday_date']] = $ph['name'];

// Pre-compute camera calc tables for percent items that have total_count
$cameraCalcs = []; // item_id → [ ['offline'=>n,'pct'=>x], ... ]
foreach ($items as $it) {
    if ($it['input_type'] === 'percent' && !empty($it['total_count']) && (int)$it['total_count'] > 0) {
        $total = (int)$it['total_count'];
        $thr   = (int)($it['threshold'] ?: 90);
        $rows  = [];
        // Show offline 0..4 or until below threshold
        for ($off = 0; $off <= min(4, $total); $off++) {
            $pct  = round(($total - $off) / $total * 100, 1);
            $pass = $pct >= $thr;
            $rows[] = ['offline' => $off, 'pct' => $pct, 'pass' => $pass];
        }
        $cameraCalcs[(string)$it['id']] = ['label' => $it['label'], 'total' => $total, 'thr' => $thr, 'rows' => $rows];
    }
}

$data = [];
if (!$isBlank) {
    $records = $conn->query("SELECT id,check_date,checked_by FROM sc_records WHERE room_id=$room_id AND check_date BETWEEN '$from' AND '$to' ORDER BY check_date")->fetch_all(MYSQLI_ASSOC);
    foreach ($records as $rec) {
        $rid  = (int)$rec['id'];
        $vals = $conn->query("SELECT item_id,val FROM sc_values WHERE record_id=$rid")->fetch_all(MYSQLI_ASSOC);
        $vmap = [];
        foreach ($vals as $v) $vmap[(string)$v['item_id']] = $v['val'];
        $d = (int)date('d', strtotime($rec['check_date']));
        $data[$d] = ['checked_by' => $rec['checked_by'], 'values' => $vmap];
    }
}

// ── Company setup ──────────────────────────────────────────────────────────
$co = strtoupper($room['company'] ?? 'PTA');
$coColors = ['PTA' => '#059669', 'PT4' => '#dc2626', 'PTE' => '#2563eb'];
$coThemes  = ['PTA' => 'theme-pta', 'PT4' => 'theme-pt4', 'PTE' => 'theme-pte'];
$coNames   = [
    'PTA' => ['Powertech Engine Assembly Co., Ltd.', 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด'],
    'PT4' => ['Powertech 2004 Co., Ltd.',            'บริษัท พาวเวอร์เทค 2004 จำกัด'],
    'PTE' => ['Powertech Energy Solutions Co., Ltd.','บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด'],
];
$accentColor = $coColors[$co] ?? '#2563eb';
$themeClass  = $coThemes[$co]  ?? '';
$coNameEn    = $coNames[$co][0] ?? $co;
$coNameTh    = $coNames[$co][1] ?? $co;

// Load company info from DB (address, phone)
$stmtCo = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
$coKey  = 'company_info_' . $co;
$stmtCo->bind_param('s', $coKey);
$stmtCo->execute();
$coInfoRow  = $stmtCo->get_result()->fetch_assoc();
$stmtCo->close();
$coInfoData = $coInfoRow ? (json_decode($coInfoRow['setting_value'], true) ?? []) : [];
if (!empty($coInfoData['name_en'])) $coNameEn = $coInfoData['name_en'];
if (!empty($coInfoData['name_th'])) $coNameTh = $coInfoData['name_th'];
$coAddressTh = trim($coInfoData['address_th'] ?? '');
$coAddressEn = trim($coInfoData['address_en'] ?? '');
$coAddr      = $coAddressTh ?: $coAddressEn;
$coPhone     = trim($coInfoData['phone'] ?? '');
$coLogoUrl   = 'uploads/logos/' . strtolower($co) . '_logo.png';

// ── Date helpers ───────────────────────────────────────────────────────────
$thMonthsFull = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
                 'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$thMonthsShort = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$thDow = ['อา','จ','อ','พ','พฤ','ศ','ส'];
$thYear    = $year + 543;
$monthName = $thMonthsFull[$month] . ' ' . $thYear;
$printDate = date('d') . ' ' . $thMonthsShort[(int)date('n')] . ' ' . (date('Y') + 543);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Stats ──────────────────────────────────────────────────────────────────
$daysChecked = count($data);
$daysAllOk   = 0;
foreach ($data as $d => $rec) {
    $hasIssue = false;
    foreach ($rec['values'] as $v) { if ($v === 'issue') { $hasIssue = true; break; } }
    if (!$hasIssue) $daysAllOk++;
}
$daysIssue   = $daysChecked - $daysAllOk;
$daysMissing = $days - $daysChecked;

// ── Cell render ────────────────────────────────────────────────────────────
function renderCell($val, $type, $thresh, $bw = false) {
    if ($val === null || $val === '') return '<span style="color:#64748b">—</span>';
    switch ($val) {
        case '###':
        case 'ok':    return $bw
            ? '<span style="font-weight:700">✓</span>'
            : '<span style="color:#059669;font-weight:700">✓</span>';
        case "\u{2716}":
        case '✖':
        case 'issue': return $bw
            ? '<b style="font-weight:900">✗</b>'
            : '<span style="color:#dc2626;font-weight:700">✗</span>';
        case 'O':     return $bw
            ? '<span style="font-weight:700">O</span>'
            : '<span style="color:#1d4ed8;font-weight:700">O</span>';
        case 'na':    return '<span style="color:#475569;font-size:.6em">N/A</span>';
    }
    if ($type === 'percent') {
        $n = (int)$val;
        if ($bw) {
            $mark = $n >= 90 ? '' : ($n >= 70 ? '△' : '▼');
            return "<span style=\"font-weight:600\">{$n}%{$mark}</span>";
        }
        $c = $n >= 90 ? '#059669' : ($n >= 70 ? '#d97706' : '#dc2626');
        return "<span style=\"color:$c;font-weight:600\">{$n}%</span>";
    }
    if ($type === 'days') {
        $n = (int)$val;
        $ok = $thresh ? ($n >= $thresh) : ($n >= 30);
        if ($bw) return "<span style=\"font-weight:600\">" . ($ok ? '' : '▼') . "{$n}</span>";
        $c = $ok ? '#059669' : '#dc2626';
        return "<span style=\"color:$c;font-weight:600\">{$n}</span>";
    }
    if ($type === 'backup') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            $t = strtotime($val); $diff = (int)floor((time() - $t) / 86400);
            $thm = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
            $dm = (int)date('d',$t); $mm = (int)date('m',$t);
            if ($bw) return "<span style=\"font-size:.65em\">{$dm}/{$thm[$mm]}</span>";
            $c = $diff <= 7 ? '#059669' : ($diff <= 14 ? '#d97706' : '#dc2626');
            return "<span style=\"color:$c;font-size:.65em\">{$dm}/{$thm[$mm]}</span>";
        }
        return $bw
            ? '<span style="font-weight:700">O</span>'
            : '<span style="color:#059669;font-weight:700">O</span>';
    }
    return h($val);
}

// ── สร้างส่วนต่าง ๆ ของใบ (HTML) แล้วส่งให้แกนกลาง js/doc-print.js วางหน้าตามาตรฐาน ──
$dowOf = fn($d) => (int)date('w', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d)));
// ช่องแคบ → แสดงแค่ชื่อต้น (ข้ามคำนำหน้า Mr./นาย ฯลฯ)
$firstName = fn($n) => preg_split('/\s+/', trim(preg_replace('/^(Mr|Mrs|Ms|Miss)\.?\s+|^(นางสาว|นาย|นาง)\s*/iu', '', trim($n))))[0] ?? '';
$phOf  = fn($d) => $holidayMap[sprintf('%04d-%02d-%02d', $year, $month, $d)] ?? null;

ob_start(); ?>
<div class="dp-sec"><table class="sc-tbl">
    <colgroup><col style="width:44mm"><?php for ($d = 1; $d <= $days; $d++): ?><col><?php endfor; ?></colgroup>
    <thead><tr><th class="it">รายการตรวจสอบ / ITEM</th>
<?php for ($d = 1; $d <= $days; $d++): $dow = $dowOf($d); $ph = $phOf($d); ?>
        <th class="<?= $ph ? 'ph' : ($dow === 0 || $dow === 6 ? 'we' : '') ?>"<?= $ph ? ' title="' . h($ph) . '"' : '' ?>><?= $d ?><small><?= $thDow[$dow] ?></small></th>
<?php endfor; ?>
    </tr></thead>
    <tbody>
<?php foreach ($items as $item): ?>
        <tr><td class="it"><b><?= (int)$item['item_no'] ?>.</b> <?= h($item['label']) ?></td>
<?php   for ($d = 1; $d <= $days; $d++):
            $dow = $dowOf($d); $isWe = $dow === 0 || $dow === 6; $isPh = $phOf($d) !== null;
            if ($isPh) $cell = '<span style="color:#f97316;font-weight:700">—</span>';
            elseif ($isBlank) $cell = $isWe ? '<span style="color:#94a3b8">—</span>' : '';
            else $cell = renderCell(isset($data[$d]) ? ($data[$d]['values'][(string)$item['id']] ?? '') : null, $item['input_type'], $item['threshold'], $isBW);
?>
            <td class="<?= $isPh ? 'ph' : ($isWe ? 'we' : '') ?>"><?= $cell ?></td>
<?php   endfor; ?>
        </tr>
<?php endforeach; ?>
        <tr class="by"><td class="it">ผู้ตรวจ / Checked by</td>
<?php for ($d = 1; $d <= $days; $d++):
        $dow = $dowOf($d); $isWe = $dow === 0 || $dow === 6; $isPh = $phOf($d) !== null;
        $cb = $data[$d]['checked_by'] ?? '';
        $cell = $isPh ? '<span style="color:#f97316">—</span>' : ($isWe ? '<span style="color:#94a3b8">—</span>' : ($isBlank || !$cb ? '' : h($firstName($cb))));
?>
            <td class="<?= $isPh ? 'ph' : ($isWe ? 'we' : '') ?>"><?= $cell ?></td>
<?php endfor; ?>
        </tr>
    </tbody>
</table>
<div class="sc-leg"><b style="color:#059669">✓</b> ปกติ · <b style="color:#1d4ed8">O</b> Backup ปกติ · <b style="color:#dc2626">✗</b> มีปัญหา · N/A ไม่เกี่ยวข้อง · — ยังไม่ตรวจ / วันหยุด<?= $holidayMap ? ' · <span style="color:#f97316">ช่องสีส้ม = วันหยุดบริษัท</span>' : '' ?></div></div>
<?php
$tableHtml = ob_get_clean();

// เกณฑ์ผ่าน/ไม่ผ่านของแต่ละรายการ
$criteria = [];
foreach ($items as $it) {
    $lbl = '<b>' . h($it['label']) . '</b>: ';
    $thr = (int)($it['threshold'] ?: 90);
    switch ($it['input_type']) {
        case 'status':  $criteria[] = $lbl . '<span style="color:#059669">ผ่าน = ✓</span> | <span style="color:#dc2626">ไม่ผ่าน = ✗</span>'; break;
        case 'backup':  $criteria[] = $lbl . '<span style="color:#1d4ed8">ผ่าน = O</span> | <span style="color:#dc2626">ไม่ผ่าน = ✗</span>'; break;
        case 'days':    $criteria[] = $lbl . "<span style=\"color:#059669\">ผ่าน ≥ {$thr} วัน</span> | <span style=\"color:#dc2626\">ไม่ผ่าน &lt; {$thr} วัน</span>"; break;
        case 'percent':
            $line = $lbl . "<span style=\"color:#059669\">ผ่าน ≥ {$thr}%</span> | <span style=\"color:#dc2626\">ไม่ผ่าน &lt; {$thr}%</span>";
            // กล้อง: บอกให้เลยว่าดับกี่ตัวแล้วตก ไม่ต้องคำนวณเอง
            if (!empty($cameraCalcs[(string)$it['id']])) {
                $cc = $cameraCalcs[(string)$it['id']];
                $line .= '<br>' . implode(' · ', array_map(fn($r) => '<span style="color:' . ($r['pass'] ? '#059669' : '#dc2626') . '">'
                    . ($r['offline'] === 0 ? "ครบ {$cc['total']} ตัว" : "ดับ {$r['offline']} ตัว") . " = {$r['pct']}%</span>", $cc['rows']));
            }
            $criteria[] = $line; break;
    }
}
$criteriaHtml = $criteria ? '<div class="sc-crit">' . implode('', array_map(fn($c) => "<div>$c</div>", $criteria)) . '</div>' : '';

// รายการที่ไม่ผ่าน → ช่องบันทึกการแก้ไข (เว้นแถวว่างให้เขียนมืออย่างน้อย 4 แถว)
$itemMap = [];
foreach ($items as $it) $itemMap[(string)$it['id']] = $it['label'];
$issueRows = [];
foreach ($data as $d => $rec) {
    foreach ($rec['values'] as $iid => $val) {
        if (in_array($val, ['✗', 'issue', 'X', 'x', "\u{2716}"], true)) $issueRows[] = [$d . ' ' . $thMonthsShort[$month] . ' ' . $thYear, $itemMap[$iid] ?? "Item #$iid"];
    }
}
$caRows = implode('', array_map(fn($r) => '<tr><td style="color:#dc2626;font-weight:600">' . h($r[0]) . '</td><td>' . h($r[1]) . '</td><td></td><td></td><td></td></tr>', $issueRows))
        . str_repeat('<tr><td style="height:6mm"></td><td></td><td></td><td></td><td></td></tr>', max(0, 4 - count($issueRows)));
$caHtml = '<table class="dp-tbl"><thead><tr><th style="width:24mm">วันที่</th><th style="width:55mm">รายการ</th><th>การแก้ไข</th><th style="width:35mm">ผู้รับผิดชอบ</th><th style="width:24mm">วันที่แก้ไข</th></tr></thead><tbody>' . $caRows . '</tbody></table>';

$summary = $isBlank ? 'แบบฟอร์มเปล่า (กรอกด้วยมือ)'
    : "ตรวจแล้ว <b>{$daysChecked}</b>/{$days} วัน · ปกติ <b style=\"color:#059669\">{$daysAllOk}</b> · พบปัญหา <b style=\"color:" . ($daysIssue ? '#dc2626' : 'inherit') . "\">{$daysIssue}</b> · ยังไม่ตรวจ {$daysMissing}";
$qs = fn($over) => h('?' . http_build_query(array_merge(['room_id' => $room_id, 'month' => $month, 'year' => $year], array_filter(['blank' => $isBlank ? 1 : null, 'bw' => $isBW ? 1 : null]), $over)));
$bar = '<a href="server_check_report.html">← กลับรายงาน</a>'
     . (!$isBlank ? '<a href="' . $qs(['blank' => 1]) . '" target="_blank">ฟอร์มเปล่า</a>' : '')
     . '<span class="seg"><a href="' . $qs(['bw' => null]) . '" class="' . ($isBW ? '' : 'on') . '">สี</a><a href="' . $qs(['bw' => 1]) . '" class="' . ($isBW ? 'on' : '') . '">ขาวดำ</a></span>';

$spec = [
    'company' => $co, 'formKey' => 'server_check', 'classified' => true, 'toolbar' => true, 'barHtml' => $bar, 'size' => 'land',
    'docNo' => 'SC-' . ($room['code'] ?: $room_id) . '-' . sprintf('%04d%02d', $year, $month),
    'dateText' => 'ประจำเดือน ' . $monthName, 'statusText' => $isBlank ? 'ฟอร์มเปล่า' : '',
    'title' => ['th' => $isBlank ? 'แบบฟอร์มตรวจสอบห้อง Server ประจำเดือน' : 'รายงานตรวจสอบห้อง Server ประจำเดือน', 'en' => 'Monthly Server Room Inspection', 'color' => $isBW ? '#1e293b' : null],
    'info' => [['ห้อง', $room['name']], ['ที่ตั้ง', $room['location'] ?? ''], ['เดือน', $monthName], ['ผลการตรวจ', $summary, true]],
    'blocks' => [
        // ขาวดำ: บังคับสีบริษัทเป็นเทาเข้ม ประหยัดหมึกสี
        '<style>.sc-tbl{width:100%;border-collapse:collapse;table-layout:fixed;font-size:7.6pt}.sc-tbl th,.sc-tbl td{border:1px solid #e2e8f0;text-align:center;padding:1px 0;height:5.2mm;overflow:hidden}'
        . '.sc-tbl th{background:#f8fafc;color:#475569;font-size:7pt;line-height:1.1}.sc-tbl th small{display:block;font-weight:400;font-size:6pt;color:#94a3b8}'
        . '.sc-tbl .it{text-align:left;padding:1px 6px;white-space:nowrap;text-overflow:ellipsis;font-size:7.8pt}.sc-tbl .we{background:#f1f5f9}.sc-tbl .ph{background:#fff7ed}.sc-tbl th.ph{color:#f97316}'
        . '.sc-tbl .by td{font-size:6pt;color:#475569;border-top:1.5px solid #94a3b8}.sc-leg{font-size:7pt;color:#64748b;padding:3px 9px}'
        . '.sc-crit{display:grid;grid-template-columns:repeat(3,1fr);gap:2px 14px;padding:4px 9px;font-size:7.4pt;line-height:1.45}'
        . ($isBW ? '.dp-page{--acc:#1e293b!important}' : '') . '</style>' . $tableHtml,
        $criteriaHtml ? '<div class="dp-sec"><div class="dp-sh">เกณฑ์การตรวจสอบ / INSPECTION CRITERIA</div>' . $criteriaHtml . '</div>' : '',
        '<div class="dp-sec"><div class="dp-sh">การแก้ไข / CORRECTIVE ACTION · ' . (count($issueRows) ? '<span style="color:#dc2626">พบ ' . count($issueRows) . ' รายการที่ไม่ผ่าน</span>' : '<span style="color:#059669">ไม่พบรายการผิดปกติ</span>') . '</div><div class="dp-sb html">' . $caHtml . '</div></div>',
    ],
    'sigs' => [['role' => 'ผู้ตรวจสอบ / Inspector'], ['role' => 'หัวหน้าแผนก IT / Head of IT'], ['role' => 'ผู้อนุมัติ / Approver']],
];
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>ตรวจห้อง Server — <?= h($room['name']) ?> — <?= h($monthName) ?></title></head>
<body>
<p style="font-family:sans-serif;padding:40px;color:#64748b">กำลังเตรียมเอกสาร...</p>
<script src="js/doc-print.js?v=20261022"></script>
<script>
(async () => {
    const spec = <?= json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!spec.title.color) delete spec.title.color;
    const html = await DocPrint.build(spec);
    document.open(); document.write(html); document.close();
})();
</script>
</body>
</html>
