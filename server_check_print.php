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

$items = $conn->query("SELECT i.* FROM sc_items i WHERE i.room_id=$room_id AND (i.is_active=1 OR EXISTS (
        SELECT 1 FROM sc_values v JOIN sc_records r ON r.id=v.record_id
        WHERE v.item_id=i.id AND v.val<>'' AND r.room_id=$room_id AND r.check_date BETWEEN '" . sprintf('%04d-%02d-01', $year, $month) . "' AND '" . date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month))) . "'))
    ORDER BY i.is_active DESC, i.sort_order")->fetch_all(MYSQLI_ASSOC);
// รายการที่เลิกใช้แล้ว (แสดงเพราะมีผลตรวจในเดือนนี้) ไว้ท้ายตาราง พร้อมบอกชัดว่าเลิกใช้
// ตารางแสดงเฉพาะรายการปัจจุบัน · รายการที่เลิกใช้ระหว่างเดือน สรุปเป็นหมายเหตุใต้ตาราง (ประวัติไม่หาย ตารางไม่ยาว)
$retiredItems = array_values(array_filter($items, fn($i) => !(int)$i['is_active']));
$items = array_values(array_filter($items, fn($i) => (int)$i['is_active']));

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
$lateMap = []; // วันที่ (เลขวัน) → ['at' => วันลงระบบ, 'by', 'reason']
if (!$isBlank) {
    $records = $conn->query("SELECT id,check_date,checked_by,created_at,saved_by_name FROM sc_records WHERE room_id=$room_id AND check_date BETWEEN '$from' AND '$to' ORDER BY check_date")->fetch_all(MYSQLI_ASSOC);
    foreach ($records as $rec) {
        $rid  = (int)$rec['id'];
        $vals = $conn->query("SELECT item_id,val FROM sc_values WHERE record_id=$rid")->fetch_all(MYSQLI_ASSOC);
        $vmap = [];
        foreach ($vals as $v) $vmap[(string)$v['item_id']] = $v['val'];
        $d = (int)date('d', strtotime($rec['check_date']));
        $data[$d] = ['checked_by' => $rec['checked_by'], 'values' => $vmap];
        $created = substr((string)$rec['created_at'], 0, 10);
        if ($created && $created >= date('Y-m-d', strtotime($rec['check_date'] . ' +2 day'))) $lateMap[$d] = ['at' => $created, 'by' => $rec['saved_by_name']];
    }
    // เหตุผลที่ผู้บันทึกกรอก (รายวัน หรือจากปุ่มเติมวันที่ว่างทั้งเดือน)
    $st = $conn->prepare("SELECT target_id, user_name, details FROM audit_log WHERE action='server_check_backfill' AND target_id LIKE ? ORDER BY id");
    $like = 'sc:' . $room_id . ':%';
    $st->bind_param('s', $like);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $lg) {
        $det = json_decode((string)$lg['details'], true) ?: [];
        $dates = !empty($det['dates']) ? $det['dates'] : [substr($lg['target_id'], strlen('sc:' . $room_id . ':'))];
        foreach ($dates as $dt) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dt) || $dt < $from || $dt > $to) continue;
            $dd = (int)substr($dt, 8, 2);
            $lateMap[$dd] = array_merge($lateMap[$dd] ?? ['at' => '', 'by' => $lg['user_name']], ['reason' => $det['reason'] ?? '']);
        }
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
// วันที่มีแต่ชื่อผู้ตรวจ ไม่มีผลตรวจสักข้อ ไม่นับว่าตรวจแล้ว (บันทึกเปล่าจากปุ่มใส่ชื่อทั้งเดือนแบบเดิม)
$data = array_filter($data, fn($rec) => count(array_filter($rec['values'], fn($v) => $v !== '' && $v !== null)) > 0);
$lateMap = array_intersect_key($lateMap, $data);
$retiredNote = [];
foreach ($retiredItems as $ri) {
    $okDays = []; $badDays = [];
    foreach ($data as $d => $rec) {
        $v = $rec['values'][(string)$ri['id']] ?? '';
        if ($v === '' || $v === null) continue;
        if (in_array($v, ['✖', '✗', 'issue', 'X', 'x'], true)) $badDays[] = $d; else $okDays[] = $d;
    }
    if ($okDays || $badDays) $retiredNote[] = '<b>' . h($ri['label']) . '</b> — '
        . ($okDays ? 'ปกติ วันที่ ' . implode(', ', $okDays) : '') . ($okDays && $badDays ? ' · ' : '')
        . ($badDays ? '<span style="color:#dc2626">มีปัญหา วันที่ ' . implode(', ', $badDays) . '</span>' : '');
}
$itemById = [];
foreach ($items as $it) $itemById[(string)$it['id']] = $it;
$daysChecked = count($data);
$daysAllOk   = 0;
foreach ($data as $d => $rec) {
    $hasIssue = false;
    // ปัญหา = ติ๊ก ✗ (เก็บเป็น ✖) หรือตัวเลขต่ำกว่าเกณฑ์ — เดิมเช็กแค่คำว่า 'issue' จึงนับไม่เจอเลย
    foreach ($rec['values'] as $iid => $v) {
        $it = $itemById[(string)$iid] ?? null;
        $isNum = $it && in_array($it['input_type'], ['percent', 'days'], true) && is_numeric($v);
        if (in_array($v, ['issue', '✖', '✗', 'X', 'x'], true) || ($isNum && (float)$v < (float)($it['threshold'] ?: 90))) { $hasIssue = true; break; }
    }
    if (!$hasIssue) $daysAllOk++;
}
$daysIssue   = $daysChecked - $daysAllOk;
// ยังไม่ตรวจ = วันทำงานที่ผ่านมาแล้ว (ไม่นับเสาร์-อาทิตย์/วันหยุด/วันข้างหน้า) ที่ไม่มีบันทึก
$daysMissing = 0;
for ($d = 1; $d <= $days; $d++) {
    $dt = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $w = (int)date('w', strtotime($dt));
    if ($dt > date('Y-m-d') || $w === 0 || $w === 6 || isset($holidayMap[$dt]) || isset($data[$d])) continue;
    $daysMissing++;
}

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
<?php foreach ($items as $ix => $item): ?>
        <tr><td class="it"><b><?= $ix + 1 ?>.</b> <?= h($item['label']) ?></td>
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
        if (isset($lateMap[$d]) && !$isBlank) $cell .= '<b style="color:#d97706">*</b>';
?>
            <td class="<?= $isPh ? 'ph' : ($isWe ? 'we' : '') ?>"><?= $cell ?></td>
<?php endfor; ?>
        </tr>
    </tbody>
</table>
<div class="sc-leg"><b style="color:#059669">✓</b> ปกติ · <b style="color:#1d4ed8">O</b> Backup ปกติ · <b style="color:#dc2626">✗</b> มีปัญหา · N/A ไม่เกี่ยวข้อง · — ยังไม่ตรวจ / วันหยุด<?= $holidayMap ? ' · <span style="color:#f97316">ช่องสีส้ม = วันหยุดบริษัท</span>' : '' ?><?= $lateMap ? ' · <b style="color:#d97706">*</b> = บันทึกย้อนหลัง' : '' ?><?= $isBlank ? ' · <b>ช่องกล้อง</b>: เขียนจำนวนตัวที่ออนไลน์ · <b>ช่องเก็บภาพ</b>: เขียนจำนวนวัน' : '' ?></div>
<?php if ($retiredNote): ?><div class="sc-leg" style="border-top:1px dashed #e2e8f0">รายการที่เลิกใช้ระหว่างเดือน (ผลตรวจก่อนเลิกใช้): <?= implode(' · ', $retiredNote) ?></div><?php endif; ?></div>
<?php
$tableHtml = ob_get_clean();

// เกณฑ์ผ่าน/ไม่ผ่าน: รวมรายการที่ใช้เกณฑ์เดียวกันเป็นบรรทัดเดียว อ้างเลขข้อ (ใบไม่ยาวเกินแผ่น)
$critGroups = [];
foreach ($items as $ix => $it) {
    $thr = (int)($it['threshold'] ?: 90);
    switch ($it['input_type']) {
        case 'status':  $k = 'status';  $txt = '<b>สถานะอุปกรณ์</b> <span style="color:#059669">ผ่าน = ✓</span> | <span style="color:#dc2626">ไม่ผ่าน = ✗</span>'; break;
        case 'backup':  $k = 'backup';  $txt = '<b>สำรองข้อมูล</b> <span style="color:#1d4ed8">ผ่าน = O</span> | <span style="color:#dc2626">ไม่ผ่าน = ✗</span>'; break;
        case 'days':    $k = "days$thr"; $txt = "<b>เก็บภาพย้อนหลัง</b> <span style=\"color:#059669\">ผ่าน ≥ {$thr} วัน</span> | <span style=\"color:#dc2626\">ไม่ผ่าน &lt; {$thr} วัน</span>"; break;
        default:
            $tot = (int)($it['total_count'] ?? 0);
            $k = "pct$thr-$tot";
            $txt = "<b>ออนไลน์</b> <span style=\"color:#059669\">ผ่าน ≥ {$thr}%</span> | <span style=\"color:#dc2626\">ไม่ผ่าน &lt; {$thr}%</span>";
            // กล้อง: บอกเลยว่าดับได้กี่ตัว ไม่ต้องคำนวณเอง
            if ($tot > 0) $txt .= ' <span style="color:#475569">(ทั้งหมด ' . $tot . ' ตัว ดับได้ไม่เกิน ' . (int)floor($tot * (1 - $thr / 100) + 1e-9) . ' ตัว)</span>';
    }
    if (!isset($critGroups[$k])) $critGroups[$k] = ['txt' => $txt, 'nos' => []];
    $critGroups[$k]['nos'][] = $ix + 1;
}
// เลขข้อติดกันเขียนเป็นช่วง เช่น 1–7, 11, 14
$noRange = function (array $nos): string {
    $out = []; $st = $prev = null;
    foreach ($nos as $n) {
        if ($prev !== null && $n === $prev + 1) { $prev = $n; continue; }
        if ($st !== null) $out[] = $st === $prev ? "$st" : "{$st}–{$prev}";
        $st = $prev = $n;
    }
    if ($st !== null) $out[] = $st === $prev ? "$st" : "{$st}–{$prev}";
    return implode(', ', $out);
};
$criteriaHtml = $critGroups ? '<div class="sc-crit">' . implode('', array_map(fn($g) => '<div><span style="color:#64748b">ข้อ ' . $noRange($g['nos']) . ':</span> ' . $g['txt'] . '</div>', $critGroups)) . '</div>' : '';

// รายการที่ไม่ผ่าน → ช่องบันทึกการแก้ไข (เว้นแถวว่างให้เขียนมืออย่างน้อย 4 แถว)
$itemMap = [];
foreach ($items as $it) $itemMap[(string)$it['id']] = $it['label'];
$issueRows = [];
foreach ($data as $d => $rec) {
    foreach ($rec['values'] as $iid => $val) {
        // ตัวเลขต่ำกว่าเกณฑ์ก็ต้องมีช่องบันทึกการแก้ไข เหมือนข้อที่ติ๊ก ✗
        $it = $itemById[(string)$iid] ?? null;
        $numFail = $it && in_array($it['input_type'], ['percent', 'days'], true) && is_numeric($val) && (float)$val < (float)($it['threshold'] ?: 90);
        if ($numFail) $issueRows[] = [$d . ' ' . $thMonthsShort[$month] . ' ' . $thYear, ($itemMap[$iid] ?? "Item #$iid") . ' (' . $val . ($it['input_type'] === 'percent' ? '%' : ' วัน') . ')'];
        elseif (in_array($val, ['✗', 'issue', 'X', 'x', "\u{2716}"], true)) $issueRows[] = [$d . ' ' . $thMonthsShort[$month] . ' ' . $thYear, $itemMap[$iid] ?? "Item #$iid"];
    }
}
$caRows = implode('', array_map(fn($r) => '<tr><td style="color:#dc2626;font-weight:600">' . h($r[0]) . '</td><td>' . h($r[1]) . '</td><td></td><td></td><td></td></tr>', $issueRows))
        . str_repeat('<tr><td style="height:6mm"></td><td></td><td></td><td></td><td></td></tr>', max(0, ($isBlank || count($items) <= 12 ? 4 : 2) - count($issueRows)));
$caHtml = '<table class="dp-tbl"><thead><tr><th style="width:24mm">วันที่</th><th style="width:55mm">รายการ</th><th>การแก้ไข</th><th style="width:35mm">ผู้รับผิดชอบ</th><th style="width:24mm">วันที่แก้ไข</th></tr></thead><tbody>' . $caRows . '</tbody></table>';

$lateHtml = '';
if ($lateMap) {
    ksort($lateMap);
    $groups = [];
    foreach ($lateMap as $d => $l) {
        $key = ($l['at'] ?? '') . '|' . ($l['by'] ?? '') . '|' . ($l['reason'] ?? '');
        if ($groups && end($groups)['key'] === $key) { $groups[key($groups)]['days'][] = $d; continue; }
        $groups[] = ['key' => $key, 'days' => [$d], 'l' => $l];
    }
    $thD = fn($ymd) => $ymd ? ((int)substr($ymd, 8, 2)) . ' ' . $thMonthsShort[(int)substr($ymd, 5, 2)] . ' ' . ((int)substr($ymd, 0, 4) + 543) : '-';
    $rowsL = implode('', array_map(function ($g) use ($thD, $thMonthsShort, $month) {
        $ds = $g['days'];
        $txt = count($ds) > 1 ? reset($ds) . '–' . end($ds) . ' ' . $thMonthsShort[$month] . ' (' . count($ds) . ' วัน)' : $ds[0] . ' ' . $thMonthsShort[$month];
        return '<tr><td>' . h($txt) . '</td><td>' . h($thD($g['l']['at'] ?? '')) . '</td><td>' . h($g['l']['by'] ?? '-') . '</td><td>' . (($g['l']['reason'] ?? '') !== '' ? h($g['l']['reason']) : '<span style="color:#94a3b8">ไม่ได้ระบุ (บันทึกก่อนมีกติกา)</span>') . '</td></tr>';
    }, $groups));
    $lateHtml = '<div class="dp-sec"><div class="dp-sh" style="color:#b45309">บันทึกย้อนหลัง / LATE ENTRIES · ' . count($lateMap) . ' วัน</div><div class="dp-sb html"><table class="dp-tbl"><thead><tr><th style="width:42mm">วันที่ตรวจ</th><th style="width:30mm">ลงระบบเมื่อ</th><th style="width:50mm">ผู้บันทึก</th><th>เหตุผล</th></tr></thead><tbody>' . $rowsL . '</tbody></table></div></div>';
}

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
        '<style>.sc-tbl{width:100%;border-collapse:collapse;table-layout:fixed;font-size:7.6pt}.sc-tbl th,.sc-tbl td{border:1px solid #e2e8f0;text-align:center;padding:1px 0;height:' . ($isBlank ? '6.2mm' : (count($items) > 12 ? '4.3mm' : '5.2mm')) . ';overflow:hidden}'
        . '.sc-tbl th{background:#f8fafc;color:#475569;font-size:7pt;line-height:1.1}.sc-tbl th small{display:block;font-weight:400;font-size:6pt;color:#94a3b8}'
        . '.sc-tbl .it{text-align:left;padding:1px 6px;white-space:nowrap;text-overflow:ellipsis;font-size:7.8pt}.sc-tbl .we{background:#f1f5f9}.sc-tbl .ph{background:#fff7ed}.sc-tbl th.ph{color:#f97316}'
        . '.sc-tbl .by td{font-size:6pt;color:#475569;border-top:1.5px solid #94a3b8' . ($isBlank ? ';height:9mm' : '') . '}.sc-leg{font-size:7pt;color:#64748b;padding:3px 9px}'
        . '.sc-crit{display:grid;grid-template-columns:repeat(2,1fr);gap:2px 14px;padding:4px 9px;font-size:7.4pt;line-height:1.45}'
        . ($isBW ? '.dp-page{--acc:#1e293b!important}' : '') . '</style>' . $tableHtml,
        $criteriaHtml ? '<div class="dp-sec"><div class="dp-sh">เกณฑ์การตรวจสอบ / INSPECTION CRITERIA</div>' . $criteriaHtml . '</div>' : '',
        $lateHtml,
        '<div class="dp-sec"><div class="dp-sh">การแก้ไข / CORRECTIVE ACTION' . ($isBlank ? '' : ' · ' . (count($issueRows) ? '<span style="color:#dc2626">พบ ' . count($issueRows) . ' รายการที่ไม่ผ่าน</span>' : '<span style="color:#059669">ไม่พบรายการผิดปกติ</span>')) . '</div><div class="dp-sb html">' . $caHtml . '</div></div>',
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
<script src="js/doc-print.js?v=20261022b"></script>
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
