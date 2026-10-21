<?php
/**
 * server_check_print.php — ISO-ready Monthly Server Room Check (A4 Landscape)
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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>รายงาน Server Check — <?= h($room['name']) ?> — <?= h($monthName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
<?php include __DIR__ . '/css/print-forms.css'; ?>

/* ── Landscape overrides ── */
.a4 {
    width: 1060px !important;
    min-height: 700px !important;
    padding: 16px 24px 18px !important;
    font-size: 11px;
    display: flex !important;
    flex-direction: column;
    box-sizing: border-box !important;
    overflow: hidden;
}
.toolbar { max-width: 1060px; width: 1060px; }

/* ── Compact header for landscape ── */
.doc-header { padding-bottom: 8px; margin-bottom: 8px; }
.company-info { gap: 8px; }
.company-info img { height: 38px !important; }
.company-name { font-size: .95rem; }
.doc-no { font-size: .875rem; }
.iso-ctrl { margin-top: 3px; }

/* ── Company themes ── */
.theme-pta .doc-header    { border-color: #059669; }
.theme-pta .doc-title-bar { background: linear-gradient(135deg,#065f46,#059669); }
.theme-pta .company-info  { border-color: #059669; }
.theme-pta .doc-no        { color: #059669; }
.theme-pta .chk-thead     { background: #059669; }

.theme-pt4 .doc-header    { border-color: #dc2626; }
.theme-pt4 .doc-title-bar { background: linear-gradient(135deg,#991b1b,#dc2626); }
.theme-pt4 .company-info  { border-color: #dc2626; }
.theme-pt4 .doc-no        { color: #dc2626; }
.theme-pt4 .chk-thead     { background: #dc2626; }

.theme-pte .doc-header    { border-color: #2563eb; }
.theme-pte .doc-title-bar { background: linear-gradient(135deg,#1e40af,#2563eb); }
.theme-pte .company-info  { border-color: #2563eb; }
.theme-pte .doc-no        { color: #2563eb; }
.theme-pte .chk-thead     { background: #2563eb; }

/* ── Title bar (compact + stats inline) ── */
.doc-title-bar {
    padding: 5px 14px; margin-bottom: 8px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    text-align: left;
}
.doc-title-bar h2 { font-size: .825rem; font-weight: 700; }
.doc-title-bar p  { font-size: .6rem; opacity: .85; margin-top: 1px; }
.doc-title-bar .title-left  { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; overflow: hidden; }
.doc-title-bar .title-stats {
    display: flex; gap: 12px; align-items: center; flex-shrink: 0;
}
.doc-title-bar .ts-item {
    display: flex; flex-direction: column; align-items: center;
    background: rgba(255,255,255,.15); border-radius: 5px; padding: 3px 10px;
    min-width: 52px;
}
.doc-title-bar .ts-num { font-size: .95rem; font-weight: 800; line-height: 1; }
.doc-title-bar .ts-lbl { font-size: .55rem; opacity: .85; margin-top: 1px; }

/* ── Check table ── */
.chk-table {
    border-collapse: collapse;
    font-size: 9.5px; margin-bottom: 8px;
    table-layout: fixed;
    max-width: 100%;
}
.chk-thead th {
    color: #fff; font-weight: 700;
    padding: 3px 0; text-align: center;
    font-size: 9px; line-height: 1.2;
    border: 1px solid rgba(255,255,255,.2);
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.chk-thead th .dow-lbl {
    display: block; font-size: 7px; font-weight: 400; opacity: .75;
}
.chk-thead th.th-item {
    text-align: left; padding: 5px 8px; font-size: 9px; line-height: 1.3;
}
.chk-thead th.weekend-hd { opacity: .6; }
.chk-thead th.ph-hd { color:#f97316 !important; font-weight:800; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
.chk-table .ph-cell { background:#fff7ed !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }

.chk-table tbody td {
    padding: 3px 0; text-align: center; vertical-align: middle;
    border: 1px solid #94a3b8;
    height: 20px;
}
.chk-table tbody td.td-item {
    text-align: left; padding: 3px 6px;
    font-size: 9px; color: #1e293b; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    border: 1px solid #94a3b8;
}
.chk-table tbody td.td-item span.no {
    color: #64748b; margin-right: 3px; font-size: 7.5px; font-weight: 400;
}
.chk-table tbody tr:nth-child(even) td { background: #f5f7fa; }
.chk-table .weekend { background: #e8eaed !important; }

/* ── Signature ── */
.sig-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding-top: 8px; margin-top: auto; }
.sig-box  { border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.sig-header {
    background: #f1f5f9; border-bottom: 1px solid #e2e8f0;
    padding: 5px 12px; display: flex; align-items: baseline; gap: 6px;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.sig-role-th { font-size: .7rem; font-weight: 700; color: #1e293b; }
.sig-role-en { font-size: .58rem; color: #64748b; }
.sig-body { padding: 8px 12px 10px; display: flex; flex-direction: column; align-items: center; gap: 2px; flex: 1; }
.sig-space { flex: 1; min-height: 32px; }
.sig-line-wrap { width: 100%; text-align: center; margin-bottom: 1px; }
.sig-line { border-bottom: 1px solid #475569; margin: 0 6px 3px; }
.sig-label { font-size: .5rem; color: #64748b; letter-spacing: .04em; }
.sig-name { font-size: .58rem; color: #334155; text-align: center; letter-spacing: .01em; }
.sig-date { font-size: .55rem; color: #475569; text-align: center; margin-top: 2px; }

/* ── B&W mode ── */
body.bw .doc-title-bar {
    background: #fff !important;
    border: 2px solid #1e293b;
    -webkit-print-color-adjust: unset; print-color-adjust: unset;
}
body.bw .doc-title-bar h2,
body.bw .doc-title-bar p,
body.bw .doc-title-bar .ts-num,
body.bw .doc-title-bar .ts-lbl { color: #0f172a !important; opacity: 1 !important; }
body.bw .doc-title-bar .ts-item {
    background: #f1f5f9 !important;
    border: 1px solid #94a3b8 !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
body.bw .chk-thead th {
    background: #1e293b !important; color: #fff !important;
    border-color: #fff !important;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
body.bw .chk-thead th.weekend-hd { background: #64748b !important; }
body.bw .chk-thead th.ph-hd { background: #475569 !important; color: #fff !important; }
body.bw .chk-table .ph-cell { background: #e2e8f0 !important; }
body.bw .chk-table .weekend { background: #e8eaed !important; }
body.bw .doc-header { border-color: #1e293b !important; }
body.bw .company-info { border-color: #1e293b !important; }

/* ── Print ── */
@page { size: A4 landscape; margin: 8mm 7mm; }
@media print {
    body { background: none; padding: 0; display: block; }
    .toolbar { display: none !important; }
    .a4 {
        box-shadow: none !important;
        width: 100% !important; margin: 0; padding: 6px 8px 0 !important;
        min-height: 194mm; display: flex !important; flex-direction: column;
        overflow: visible !important;
    }
    .sig-row { margin-top: auto; }
    .chk-table {
        width: 100% !important; max-width: 100% !important;
        border-color: #64748b !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .chk-table td, .chk-table th {
        border-color: #64748b !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .chk-table tbody tr:nth-child(even) td,
    .chk-table .weekend {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .doc-title-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* ── Compact everything for 1-page fit ── */
    .doc-header { padding-bottom: 4px !important; margin-bottom: 4px !important; }
    .company-name { font-size: .8rem !important; }
    .company-info img { height: 30px !important; }
    .doc-no { font-size: .75rem !important; }
    .iso-ctrl { margin-top: 2px !important; }
    .iso-ctrl td { padding: 1px 5px !important; font-size: 7.5px !important; }

    .doc-title-bar { padding: 3px 10px !important; margin-bottom: 4px !important; }
    .doc-title-bar h2 { font-size: .72rem !important; }
    .doc-title-bar p  { font-size: .55rem !important; }
    .doc-title-bar .ts-num { font-size: .8rem !important; }
    .doc-title-bar .ts-lbl { font-size: .5rem !important; }
    .doc-title-bar .ts-item { padding: 2px 7px !important; min-width: 44px !important; }

    .chk-table { margin-bottom: 3px !important; font-size: 8px !important; }
    .chk-table tbody td { height: 15px !important; padding: 1px 0 !important; }
    .chk-table tbody td.td-item { font-size: 7.5px !important; padding: 1px 4px !important; }
    .chk-thead th { padding: 2px 0 !important; font-size: 8px !important; }
    .chk-thead th.th-item { padding: 3px 6px !important; font-size: 8px !important; }

    /* legend */
    .legend-row, div[class*="legend"] { margin-bottom: 3px !important; font-size: 7px !important; }

    /* criteria */
    .criteria-box { padding: 4px 8px !important; margin-bottom: 3px !important; }
    .criteria-box .criteria-title { margin-bottom: 2px !important; font-size: 7.5px !important; }
    .criteria-box .criteria-grid { font-size: 7px !important; gap: 1px 8px !important; }

    /* corrective action */
    .ca-box { padding: 4px 8px !important; margin-bottom: 3px !important; }
    .ca-box table { font-size: 7px !important; }
    .ca-box table td,
    .ca-box table th { padding: 2px 4px !important; height: 14px !important; }

    /* signatures */
    .sig-row { padding-top: 5px !important; gap: 8px !important; margin-top: auto; page-break-inside: avoid; }
    .sig-box { min-height: 70px !important; }
    .sig-header { padding: 4px 10px !important; background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sig-body { padding: 4px 10px 6px !important; gap: 2px !important; }
    .sig-space { min-height: 22px !important; }
    .sig-role-th { font-size: .65rem !important; }
    .sig-role-en { font-size: .55rem !important; }
    .sig-name { font-size: .55rem !important; }
    .sig-date { font-size: .52rem !important; }

    .doc-footer { margin-top: 2px !important; padding-top: 3px !important; }
    .doc-footer-left, .doc-footer-right { font-size: .55rem !important; }

    /* ── Darker borders for print (keep accent colors on header) ── */
    .chk-table td, .chk-table th { border-color: #374151 !important; }
    .sig-box { border-color: #374151 !important; }
    .sig-header { border-bottom-color: #374151 !important; }
    .sig-line { border-bottom-color: #374151 !important; }
    .criteria-box { border-color: #374151 !important; }
    .ca-box { border-color: #374151 !important; }
    .ca-box table td, .ca-box table th { border-color: #374151 !important; }
    .iso-ctrl { border-color: #374151 !important; }
    .iso-ctrl td { border-color: #374151 !important; }
}
</style>
</head>
<body class="<?= h($themeClass) ?><?= $isBW ? ' bw' : '' ?>">

<div class="toolbar">
    <h1>
        <i class="fas fa-server" style="color:<?= h($accentColor) ?>;margin-right:6px"></i>
        <?= $isBlank ? 'แบบฟอร์มเปล่า' : 'รายงาน' ?> — <?= h($room['name']) ?> — <?= h($monthName) ?>
    </h1>
    <a class="close-btn" href="server_check_report.html">
        <i class="fas fa-arrow-left"></i> กลับรายงาน
    </a>
    <?php if (!$isBlank): ?>
    <a class="close-btn" href="?room_id=<?= $room_id ?>&month=<?= $month ?>&year=<?= $year ?>&blank=1" target="_blank"
       style="color:#7c3aed;border-color:#c4b5fd">
        <i class="fas fa-file-lines"></i> ฟอร์มเปล่า
    </a>
    <?php endif; ?>
    <?php
        $bwToggleUrl = '?room_id=' . $room_id . '&month=' . $month . '&year=' . $year
            . ($isBlank ? '&blank=1' : '')
            . ($isBW    ? '' : '&bw=1');
    ?>
    <a class="close-btn" href="<?= h($bwToggleUrl) ?>"
       style="<?= $isBW ? 'background:#1e293b;color:#fff;border-color:#1e293b' : 'color:#334155;border-color:#94a3b8' ?>">
        <i class="fas fa-circle-half-stroke"></i> <?= $isBW ? 'สี' : 'ขาวดำ' ?>
    </a>
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> พิมพ์ / PDF
    </button>
</div>

<div class="a4" style="width:1060px;min-height:700px;padding:16px 24px 18px;box-sizing:border-box">

    <!-- ── Header ── -->
    <div class="doc-header">
        <div class="company-info">
            <img src="<?= h($coLogoUrl) ?>" alt="<?= h($co) ?>"
                 style="height:46px;width:auto;object-fit:contain;"
                 onerror="this.style.display='none'">
            <div>
                <div class="company-name"><?= h($coNameEn) ?></div>
                <div class="company-sub"><?= h($coNameTh) ?></div>
                <?php if ($coAddr): ?>
                <div class="company-addr"><?= h($coAddr) ?></div>
                <?php else: ?>
                <div class="company-addr">IT Support &amp; Maintenance Department</div>
                <?php endif; ?>
                <?php if ($coPhone): ?>
                <div class="company-addr"><?= h($coPhone) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-meta-box">
                <div class="doc-no-label">เลขที่เอกสาร / Form No.</div>
                <div class="doc-no">IT-SC-001</div>
                <div class="doc-date">ห้อง: <?= h($room['name']) ?></div>
                <div class="doc-date">เดือน: <?= h($monthName) ?></div>
            </div>
            <div class="iso-ctrl">
                <table>
                    <tr><td class="lbl">Form No.</td><td class="val">IT-SC-001</td></tr>
                    <tr><td class="lbl">Revision</td><td class="val rev">Rev. 01</td></tr>
                    <tr><td class="lbl">Eff. Date</td><td class="val">01/01/2568</td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Title bar ── -->
    <div class="doc-title-bar">
        <div class="title-left">
            <h2><?= $isBlank ? 'แบบฟอร์มตรวจสอบห้อง Server (กรอกมือ) / Monthly Server Room Inspection Form' : 'รายงานการตรวจสอบห้อง Server ประจำเดือน / Monthly Server Room Inspection Report' ?></h2>
            <p>IT Department &nbsp;|&nbsp; IT-SC-001 Rev.01 &nbsp;|&nbsp; <?= h($co) ?> — <?= h($room['code']) ?> <?= h($room['location']) ?></p>
        </div>
        <?php if (!$isBlank): ?>
        <div class="title-stats">
            <div class="ts-item">
                <span class="ts-num"><?= $daysChecked ?></span>
                <span class="ts-lbl">ตรวจแล้ว / <?= $days ?> วัน</span>
            </div>
            <div class="ts-item">
                <span class="ts-num"><?= $daysAllOk ?></span>
                <span class="ts-lbl">All OK</span>
            </div>
            <div class="ts-item">
                <span class="ts-num" style="color:<?= $daysIssue > 0 ? '#fca5a5' : 'rgba(255,255,255,.6)' ?>"><?= $daysIssue ?></span>
                <span class="ts-lbl">Issue</span>
            </div>
            <div class="ts-item">
                <span class="ts-num" style="color:rgba(255,255,255,.6)"><?= $daysMissing ?></span>
                <span class="ts-lbl">ยังไม่ตรวจ</span>
            </div>
        </div>
        <?php else: ?>
        <div class="title-stats">
            <div class="ts-item" style="background:rgba(255,255,255,.2);min-width:80px">
                <span class="ts-num" style="font-size:.7rem;opacity:.8">แบบฟอร์มเปล่า</span>
                <span class="ts-lbl">กรอกด้วยมือ</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Check table ── -->
    <?php
        $itemColPx  = 170;
        $padLR      = 48;  // 24px × 2
        $a4Content  = 1060 - $padLR; // = 1012px
        $dayColPx   = (int)floor(($a4Content - $itemColPx) / $days);
        $tableWidth = $itemColPx + ($dayColPx * $days); // exact total
    ?>
    <table class="chk-table" style="width:<?= $tableWidth ?>px">
        <colgroup>
            <col style="width:<?= $itemColPx ?>px">
            <?php for ($d = 1; $d <= $days; $d++): ?>
            <col style="width:<?= $dayColPx ?>px">
            <?php endfor; ?>
        </colgroup>
        <thead class="chk-thead">
            <tr>
                <th class="th-item">รายการตรวจสอบ</th>
                <?php for ($d = 1; $d <= $days; $d++):
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $dow  = (int)date('w', strtotime($dateStr));
                    $isWe = ($dow === 0 || $dow === 6);
                    $isPh = isset($holidayMap[$dateStr]);
                    $weCls = $isPh ? ' ph-hd' : ($isWe ? ' weekend-hd' : '');
                    $title = $isPh ? ' title="'.h($holidayMap[$dateStr]).'"' : '';
                ?>
                <th class="<?= $weCls ?>"<?= $title ?>>
                    <?= $d ?><br><span class="dow-lbl"><?= $thDow[$dow] ?></span>
                </th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="td-item">
                    <span class="no"><?= (int)$item['item_no'] ?>.</span>
                    <?= h($item['label']) ?>
                </td>
                <?php for ($d = 1; $d <= $days; $d++):
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $dow   = (int)date('w', strtotime($dateStr));
                    $isWe  = ($dow === 0 || $dow === 6);
                    $isPh  = isset($holidayMap[$dateStr]);
                    $tdCls = $isPh ? 'ph-cell' : ($isWe ? 'weekend' : '');
                    if ($isPh) {
                        $cell = '<span style="color:#f97316;font-weight:700">—</span>';
                    } elseif ($isBlank) {
                        $cell = $isWe ? '<span style="color:#64748b">—</span>' : '';
                    } else {
                        $val  = isset($data[$d]) ? ($data[$d]['values'][(string)$item['id']] ?? '') : null;
                        $cell = renderCell($val, $item['input_type'], $item['threshold'], $isBW);
                    }
                ?>
                <td class="<?= $tdCls ?>"><?= $cell ?></td>
                <?php endfor; ?>
            </tr>
            <?php endforeach; ?>
            <!-- Checked by row -->
            <tr style="border-top:2px solid #94a3b8;background:#f1f5f9">
                <td class="td-item" style="color:#334155;font-size:8.5px;font-weight:700">ผู้ตรวจ / Checked by</td>
                <?php for ($d = 1; $d <= $days; $d++):
                    $dateStr2 = sprintf('%04d-%02d-%02d', $year, $month, $d);
                    $dow  = (int)date('w', strtotime($dateStr2));
                    $isWe = ($dow === 0 || $dow === 6);
                    $isPh2 = isset($holidayMap[$dateStr2]);
                    if ($isPh2) {
                        $cell = '<span style="color:#f97316;font-weight:700">—</span>';
                        $bg   = 'background:#fff7ed;';
                    } elseif ($isWe) {
                        $cell = '<span style="color:#64748b">—</span>';
                        $bg   = '';
                    } else {
                        $bg   = '';
                        if ($isBlank) {
                            $cell = '';
                        } else {
                            $cb   = $data[$d]['checked_by'] ?? '';
                            $cell = h($cb ? (explode(' ', trim($cb))[0] ?? $cb) : '');
                        }
                    }
                ?>
                <td style="font-size:7.5px;color:#475569;vertical-align:middle;line-height:1.2;<?= $bg ?>"><?= $cell ?></td>
                <?php endfor; ?>
            </tr>
        </tbody>
    </table>

    <!-- ── Legend ── -->
    <?php if ($isBlank): ?>
    <div style="display:flex;gap:14px;margin-bottom:8px;font-size:8.5px;color:#64748b;align-items:center">
        <span>✓ = ปกติ / OK &nbsp; O = Backup ปกติ &nbsp; X = มีปัญหา / Issue &nbsp; — = ไม่เกี่ยวข้อง / วันหยุด</span>
        <span style="margin-left:auto;font-size:8px;color:#475569">พิมพ์วันที่: <?= $printDate ?></span>
    </div>
    <?php else: ?>
    <div style="display:flex;gap:14px;margin-bottom:8px;font-size:8.5px;color:#64748b;align-items:center">
        <span><strong style="color:#059669">✓</strong> = ปกติ / OK</span>
        <span><strong style="color:#1d4ed8">O</strong> = Backup ปกติ</span>
        <span><strong style="color:#dc2626">✗</strong> = มีปัญหา / Issue</span>
        <span><span style="color:#475569;font-size:.75em">N/A</span> = ไม่เกี่ยวข้อง</span>
        <span><strong style="color:#475569">—</strong> = ยังไม่ได้ตรวจ</span>
        <span style="margin-left:auto;font-size:8px;color:#475569">พิมพ์วันที่: <?= $printDate ?></span>
    </div>
    <?php endif; ?>

    <!-- ── Criteria box ── -->
    <?php
    $criteriaLines = [];
    foreach ($items as $it) {
        $lbl = $it['label'];
        switch ($it['input_type']) {
            case 'status':
                $criteriaLines[] = "<strong>{$lbl}</strong>: <span style='color:#059669;font-weight:600'>ผ่าน = ✓</span> &nbsp;|&nbsp; <span style='color:#dc2626;font-weight:600'>ไม่ผ่าน = ✗</span>";
                break;
            case 'backup':
                $criteriaLines[] = "<strong>{$lbl}</strong>: <span style='color:#1d4ed8;font-weight:600'>ผ่าน = O</span> &nbsp;|&nbsp; <span style='color:#dc2626;font-weight:600'>ไม่ผ่าน = ✗</span>";
                break;
            case 'percent':
                $thr = $it['threshold'] ?: 90;
                $criteriaLines[] = "<strong>{$lbl}</strong>: <span style='color:#059669;font-weight:600'>ผ่าน ≥ {$thr}%</span> &nbsp;|&nbsp; <span style='color:#dc2626;font-weight:600'>ไม่ผ่าน &lt; {$thr}%</span>";
                // inline camera calc if available
                if (!empty($cameraCalcs[(string)$it['id']])) {
                    $cc   = $cameraCalcs[(string)$it['id']];
                    $calc = "<div style='margin-top:4px;display:flex;flex-wrap:wrap;gap:3px 12px;'>";
                    foreach ($cc['rows'] as $r) {
                        $clr  = $r['pass'] ? '#059669' : '#dc2626';
                        $label = $r['offline'] === 0 ? "ออนไลน์ครบ {$cc['total']} ตัว" : "ดับ {$r['offline']} ตัว";
                        $calc .= "<span style='color:{$clr};font-weight:600'>{$label} = {$r['pct']}%</span>";
                    }
                    $calc .= "</div>";
                    $criteriaLines[] = $calc;
                }
                break;
            case 'days':
                $thr = $it['threshold'] ?: 90;
                $criteriaLines[] = "<strong>{$lbl}</strong>: <span style='color:#059669;font-weight:600'>ผ่าน ≥ {$thr} วัน</span> &nbsp;|&nbsp; <span style='color:#dc2626;font-weight:600'>ไม่ผ่าน &lt; {$thr} วัน</span>";
                break;
        }
    }
    if ($criteriaLines):
    ?>
    <div class="criteria-box" style="border:1px solid #e2e8f0;border-radius:5px;padding:8px 12px;margin-bottom:8px;font-size:8.5px;color:#374151;background:#f9fafb;">
        <div class="criteria-title" style="font-weight:700;color:#0f172a;margin-bottom:5px;font-size:9px;">เกณฑ์การตรวจสอบ / Inspection Criteria</div>
        <div class="criteria-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:3px 16px;">
            <?php foreach ($criteriaLines as $cl): ?>
            <div style="line-height:1.6"><?= $cl ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Corrective Action ── -->
    <?php
    // Build item map id→label
    $itemMap = [];
    foreach ($items as $it) $itemMap[(string)$it['id']] = $it['label'];

    // Collect issue rows
    $issueRows = [];
    foreach ($data as $d => $rec) {
        foreach ($rec['values'] as $iid => $val) {
            if (in_array($val, ['✗','issue','X','x'])) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $thD     = $d . ' ' . ($thMonthsShort[$month] ?? '') . ' ' . $thYear;
                $issueRows[] = ['date' => $thD, 'label' => $itemMap[$iid] ?? "Item #$iid"];
            }
        }
    }
    // Always show at least 3 blank rows for handwriting
    $blankNeeded = max(0, 4 - count($issueRows));
    ?>
    <div class="ca-box" style="border:1px solid #e2e8f0;border-radius:5px;padding:8px 12px;margin-bottom:8px;font-size:8.5px;color:#374151;">
        <div style="font-weight:700;color:#0f172a;margin-bottom:6px;font-size:9px;">
            การแก้ไข / Corrective Action
            <?php if (count($issueRows)): ?>
            <span style="color:#dc2626;font-weight:600;margin-left:6px;">(พบ <?= count($issueRows) ?> รายการที่ไม่ผ่าน)</span>
            <?php else: ?>
            <span style="color:#059669;font-weight:600;margin-left:6px;">(ไม่พบรายการผิดปกติ)</span>
            <?php endif; ?>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:8px;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th style="border:1px solid #e2e8f0;padding:4px 6px;text-align:center;width:60px;font-weight:700;">วันที่</th>
                    <th style="border:1px solid #e2e8f0;padding:4px 6px;text-align:left;width:140px;font-weight:700;">รายการ / Item</th>
                    <th style="border:1px solid #e2e8f0;padding:4px 6px;text-align:left;font-weight:700;">การแก้ไข / Corrective Action</th>
                    <th style="border:1px solid #e2e8f0;padding:4px 6px;text-align:center;width:80px;font-weight:700;">ผู้รับผิดชอบ</th>
                    <th style="border:1px solid #e2e8f0;padding:4px 6px;text-align:center;width:70px;font-weight:700;">วันที่แก้ไข</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($issueRows as $ir): ?>
                <tr>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;text-align:center;color:#dc2626;font-weight:600;"><?= h($ir['date']) ?></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"><?= h($ir['label']) ?></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                </tr>
                <?php endforeach; ?>
                <?php for ($i = 0; $i < $blankNeeded; $i++): ?>
                <tr>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;height:20px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                    <td style="border:1px solid #e2e8f0;padding:5px 6px;"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Signatures ── -->
    <div class="sig-row">
        <?php
        $signers = [
            ['th' => 'ผู้ตรวจสอบ',    'en' => 'Inspector'],
            ['th' => 'หัวหน้าแผนก IT', 'en' => 'Head of IT Department'],
            ['th' => 'ผู้อนุมัติ',     'en' => 'Approver'],
        ];
        foreach ($signers as $s): ?>
        <div class="sig-box">
            <div class="sig-header">
                <span class="sig-role-th"><?= h($s['th']) ?></span>
                <span class="sig-role-en"><?= h($s['en']) ?></span>
            </div>
            <div class="sig-body">
                <div class="sig-space"></div>
                <div class="sig-line-wrap">
                    <div class="sig-line"></div>
                    <div class="sig-label">ลงชื่อ / Signature</div>
                </div>
                <div class="sig-name">( .................................................. )</div>
                <div class="sig-date">วันที่ &nbsp;.......... / .......... / ..............</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Footer ── -->
    <div class="doc-footer">
        <div class="doc-footer-left">
            ออกโดยระบบ Powertech System Center &nbsp;·&nbsp; พิมพ์: <?= $printDate ?>
        </div>
        <div class="doc-footer-right">
            IT-SC-001 Rev.01 &nbsp;·&nbsp; <?= h($co) ?> &nbsp;·&nbsp; <?= h($room['code']) ?> &nbsp;·&nbsp; <?= h($monthName) ?>
        </div>
    </div>

</div>
</body>
</html>
