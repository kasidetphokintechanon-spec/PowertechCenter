<?php
require_once __DIR__ . '/config.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "dompdf ยังไม่ได้ติดตั้ง\nรันคำสั่ง:\ncomposer require dompdf/dompdf";
    exit;
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.html');
    exit;
}

$ticketId = isset($_GET['ticket_id']) ? trim($_GET['ticket_id']) : '';
if ($ticketId === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    echo "missing ticket_id";
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo "database connection failed";
    exit;
}

$stmt = $conn->prepare("SELECT * FROM it_logs WHERE id = ? LIMIT 1");
$stmt->bind_param("s", $ticketId);
$stmt->execute();
$res = $stmt->get_result();
$log = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$log) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    echo "ticket not found";
    exit;
}

$stmtA = $conn->prepare("SELECT stored_filename, original_filename FROM it_log_attachments WHERE log_id = ?");
$stmtA->bind_param("s", $ticketId);
$stmtA->execute();
$attRes = $stmtA->get_result();
$attachments = $attRes ? $attRes->fetch_all(MYSQLI_ASSOC) : [];
$stmtA->close();

function getCompanyPrintInfoPhp($code) {
    $c = strtoupper(trim((string)$code));
    $map = [
        'PTA' => [
            'code' => 'PTA',
            'name' => 'Powertech Engine Assembly Co., Ltd.',
            'address' => '1/8, 1/37-38, 1/40 Moo 5, Tasaarn, Bangpakong, Chachoengsao 24130 Thailand',
            'phone' => 'F4: +66(0)38 578520 | F16-17: +66(0)33 022 161-4'
        ],
        'PT4' => [
            'code' => 'PT4',
            'name' => 'Powertech 2004 Co., Ltd.',
            'address' => '1/11 Moo 5, Tasaarn, Bangpakong, Chachoengsao 24130 Thailand',
            'phone' => 'Tel: +66(0)38 577182 | Fax: +66(0)38 578554'
        ],
        'PTE' => [
            'code' => 'PTE',
            'name' => 'Powertech Energy Solutions Co., Ltd.',
            'address' => '42 Moo 3, Tasaarn, Bangpakong, Chachoengsao 24130 Thailand',
            'phone' => 'Tel: -'
        ]
    ];
    if (isset($map[$c])) return $map[$c];
    return ['code' => $c ?: '-', 'name' => 'Powertech Group', 'address' => '', 'phone' => ''];
}

function formatDurationPhp($start, $end) {
    if (!$start || !$end) return '-';
    try {
        $s = new DateTime($start);
        $e = new DateTime($end);
    } catch (Exception $e) {
        return '-';
    }
    if ($e <= $s) return '-';
    $diff = $s->diff($e);
    $parts = [];
    if ($diff->d > 0) $parts[] = $diff->d . 'd';
    if ($diff->h > 0) $parts[] = $diff->h . 'h';
    if ($diff->i > 0 && count($parts) < 2) $parts[] = $diff->i . 'm';
    if (!$parts) $parts[] = '< 1m';
    return implode(' ', $parts);
}

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function formatSolutionTextPhp($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';
    $cleaned = preg_replace('/\[((\d{1,2}\/\d{1,2}\/\d{2,4})|(\d{4}-\d{1,2}-\d{1,2}))(\s+\d{1,2}:\d{2}(:\d{2})?)?\]\s*/u', '[$1] ', $raw);
    $cleaned = preg_replace('/\[Reopened\s+((\d{1,2}\/\d{1,2}\/\d{2,4})|(\d{4}-\d{1,2}-\d{1,2}))(\s+\d{1,2}:\d{2}(:\d{2})?)?\]\s*/u', '[Reopened $1] ', $cleaned);
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string)$cleaned))));
    return implode("\n", $lines);
}

$companyInfo = getCompanyPrintInfoPhp($log['company'] ?? '');
$duration = $log['completed_date'] ? formatDurationPhp($log['date'] ?? '', $log['completed_date']) : '-';
$rating = isset($log['rating']) && $log['rating'] !== null && $log['rating'] !== '' ? $log['rating'] . '/5' : 'Not rated';
$solutionText = formatSolutionTextPhp($log['solution'] ?? '');

$imgHtml = '';
if ($attachments) {
    $imgs = array_filter($attachments, function ($f) {
        $name = $f['stored_filename'] ?? '';
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name);
    });
    if ($imgs) {
        $imgHtml .= '<div class="attachments"><div class="section-title">รูปภาพแนบ (Attachments)</div><div class="attach-grid">';
        foreach ($imgs as $f) {
            $src = 'uploads/attachments/' . rawurlencode($f['stored_filename']);
            $alt = esc($f['original_filename'] ?? '');
            $imgHtml .= '<div class="attach-item"><img src="' . $src . '" alt="' . $alt . '"></div>';
        }
        $imgHtml .= '</div></div>';
    }
}

$nowPrinted = date('d/m/Y H:i:s');
$printedBy = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? 'N/A');

$ticketUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
    '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') .
    '/log.html?ticket_id=' . rawurlencode($ticketId);

$html = '<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Ticket ' . esc($ticketId) . '</title>
<style>
@font-face {
  font-family: "THSarabunNew";
  src: url("fonts/THSarabunNew.ttf") format("truetype");
}
@page { margin: 20mm; }
body { font-family: "THSarabunNew", DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
.page { width: 100%; }
.row { display: flex; justify-content: space-between; align-items: flex-start; }
.header-left { display: flex; gap: 8px; }
.logo-circle { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #94a3b8; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:bold; }
.company-text { font-size: 10px; }
.company-name { font-size: 12px; font-weight: bold; }
.form-meta { text-align: right; font-size: 9px; line-height: 1.3; }
.title-block { margin-top: 6px; padding-bottom: 6px; border-bottom: 2px solid #111827; display:flex; justify-content:space-between; align-items:flex-end; }
.title-main { font-size: 16px; font-weight: bold; }
.subtitle { font-size: 10px; color: #6b7280; }
.ticket-meta { text-align:right; font-size: 10px; }
.section { margin-top: 10px; }
.section-title { font-size: 9px; font-weight: bold; letter-spacing: .04em; color:#6b7280; text-transform: uppercase; margin-bottom: 3px; }
.box { background:#f8fafc; border:1px solid #e5e7eb; border-radius:4px; padding:6px; }
.grid-2 { display:flex; gap:12px; }
.grid-2 > div { flex:1; }
.job-details p { margin:0 0 2px 0; }
.label { display:inline-block; min-width:80px; font-weight:600; }
.sign-row { display:flex; gap:16px; margin-top:12px; padding-top:8px; border-top:1px solid #e5e7eb; }
.sign-col { flex:1; text-align:center; }
.sign-line { height:40px; border-bottom:1px dotted #cbd5e1; margin-bottom:4px; display:flex; align-items:flex-end; justify-content:center; }
.sign-name { font-size:16px; }
.sign-label { font-size:10px; font-weight:bold; color:#6b7280; text-transform:uppercase; }
.attachments { margin-top:12px; padding-top:8px; border-top:1px solid #e5e7eb; }
.attach-grid { display:flex; flex-wrap:wrap; gap:6px; }
.attach-item { width:80px; height:80px; border:1px solid #e5e7eb; border-radius:4px; overflow:hidden; background:#e5e7eb; }
.attach-item img { width:100%; height:100%; object-fit:cover; }
.footer { margin-top:10px; padding-top:4px; border-top:1px solid #e5e7eb; font-size:8px; color:#9ca3af; display:flex; justify-content:space-between; }
</style>
</head>
<body>
<div class="page">
  <div class="row">
    <div class="header-left">
      ' . (file_exists('uploads/logos/' . strtolower($companyInfo['code']) . '_logo.png') ? '<img src="' . esc('uploads/logos/' . strtolower($companyInfo['code']) . '_logo.png') . '" alt="Logo" style="width:32px;height:32px;border-radius:4px;border:1px solid #94a3b8;background:#ffffff;padding:2px;object-fit:contain;">' : '<div class="logo-circle">' . esc($companyInfo['code']) . '</div>') . '
      <div class="company-text">
        <div class="company-name">' . esc($companyInfo['name']) . '</div>';
if (!empty($companyInfo['address'])) {
    $html .= '<div>' . esc($companyInfo['address']) . '</div>';
}
if (!empty($companyInfo['phone'])) {
    $html .= '<div>' . esc($companyInfo['phone']) . '</div>';
}
$html .= '</div>
    </div>
    <div class="form-meta">
      <div>แบบฟอร์ม FM-IT-001</div>
      <div>Revision 01</div>
      <div>Effective Date: 01/01/2024</div>
    </div>
  </div>

  <div class="title-block">
    <div>
      <div class="title-main">ใบรายงานงานซ่อมระบบสารสนเทศ</div>
      <div class="subtitle">IT Service Job Report</div>
    </div>
    <div class="ticket-meta">
      <div><strong>Case ID:</strong> ' . esc($ticketId) . '</div>
      <div>วันที่แจ้ง: ' . esc(date('d/m/Y', strtotime($log['date'] ?? date('Y-m-d')))) . '</div>
      <div>บริษัท: ' . esc($companyInfo['name']) . '</div>
    </div>
  </div>';
$typeLabel = isset($log['type']) ? $log['type'] : '-';
if (is_string($typeLabel) && strtolower(trim($typeLabel)) === 'other') $typeLabel = 'อื่นๆ';

$html .= '
  <div class="section grid-2">
    <div>
    <div>
      <div class="box">
        <div style="font-size:13px; font-weight:bold;">' . esc($log['requester'] ?? '-') . '</div>
        <div style="font-size:13px; font-weight:bold;">' . esc($log['requester'] ?? '-') . '</div>
        <div style="font-size:11px; color:#4b5563;">' . esc($log['department'] ?? '-') . ' <span>[' . esc($log['company'] ?? '-') . ']</span></div>
      </div>
    </div>
    <div>
      <div class="section-title">รายละเอียดงาน / Job Details</div>
      <div class="box job-details">
        <p><span class="label">ประเภทงาน:</span> ' . esc($typeLabel) . '</p>
        <p><span class="label">ความเร่งด่วน:</span> ' . esc($log['urgency'] ?? '-') . '</p>
        <p><span class="label">ทรัพย์สิน:</span> ' . esc($log['asset_id'] ?? ($log['asset'] ?? '-')) . '</p>
        <p><span class="label">ค่าใช้จ่าย:</span> ' . (isset($log['repair_cost']) && $log['repair_cost'] !== null && $log['repair_cost'] !== '' ? number_format((float)$log['repair_cost'], 2) . ' ฿' : '-') . '</p>';
if (!empty($log['appointment_date'])) {
    $html .= '<p><span class="label">วันนัดหมาย:</span> ' . esc(date('d/m/Y H:i', strtotime($log['appointment_date']))) . '</p>';
}
if (!empty($log['pr_no'])) {
    $html .= '<p><span class="label">PR No.:</span> ' . esc($log['pr_no']) . '</p>';
}
if (!empty($log['po_no'])) {
    $html .= '<p><span class="label">PO No.:</span> ' . esc($log['po_no']) . '</p>';
}
if (!empty($log['vendor_name'])) {
    $html .= '<p><span class="label">Vendor:</span> ' . esc($log['vendor_name']) . '</p>';
}
if (!empty($log['eta_date'])) {
    $html .= '<p><span class="label">ETA:</span> ' . esc(date('d/m/Y', strtotime($log['eta_date']))) . '</p>';
}
$html .= '
        <p><span class="label">วันที่เสร็จ:</span> ' . (!empty($log['completed_date']) ? esc(date('d/m/Y H:i', strtotime($log['completed_date']))) : '-') . '</p>
        <p><span class="label">ระยะเวลาดำเนินการ:</span> ' . esc($duration) . '</p>
      </div>
    </div>
  </div>

  <div class="section grid-2">
    <div>
      <div class="section-title">รายละเอียดปัญหา (Problem / Issue)</div>
      <div class="box" style="min-height:80px;">' . nl2br(esc($log['problem'] ?? '-')) . '</div>
    </div>
    <div>
      <div class="section-title">วิธีการแก้ไข (Solution / Action Taken)</div>
      <div class="box" style="min-height:70px; font-size:11px;">' . nl2br(esc($solutionText ?: '-')) . '</div>
    </div>
  </div>

  <div class="section grid-2">
    <div>
      <div class="section-title">อะไหล่ / วัสดุที่ใช้ (Parts / Materials Used)</div>
      <div class="box" style="min-height:40px; font-size:11px; color:#6b7280;">' .
        esc(implode(' | ', array_filter([
            (isset($log['repair_cost']) && $log['repair_cost'] !== null && $log['repair_cost'] !== '') ? 'Cost: ' . number_format((float)$log['repair_cost']) . ' THB' : '',
            !empty($log['pr_no']) ? 'PR: ' . $log['pr_no'] : '',
            !empty($log['po_no']) ? 'PO: ' . $log['po_no'] : '',
            !empty($log['vendor_name']) ? 'Vendor: ' . $log['vendor_name'] : '',
            !empty($log['eta_date']) ? 'ETA: ' . date('d/m/Y', strtotime($log['eta_date'])) : ''
        ])) ?: '-') .
      '</div>
    </div>
    <div>
      <div class="section-title">ความพึงพอใจผู้ใช้ (User Satisfaction)</div>
      <div class="box" style="min-height:40px; display:flex; align-items:center; gap:4px;">
        <span style="font-weight:bold;">Rating:</span>
        <span>' . esc($rating) . '</span>
      </div>
    </div>
  </div>

  <div class="sign-row">
    <div class="sign-col">
      <div class="sign-line"></div>
      <div class="sign-label">ลายเซ็นผู้แจ้ง / Requester</div>
    </div>
    <div class="sign-col">
      <div class="sign-line"></div>
      <div class="sign-label">ลายเซ็นผู้อนุมัติ / Approver</div>
    </div>
    <div class="sign-col">
      <div class="sign-line"><span class="sign-name">' . esc($log['servicedBy'] ?? '') . '</span></div>
      <div class="sign-label">ลายเซ็นผู้ปฏิบัติงาน / Technician</div>
    </div>
  </div>

  ' . $imgHtml . '

  <div class="footer">
    <div>FM-IT-001 : ใบรายงานงานซ่อมระบบสารสนเทศ / IT Service Job Report</div>
    <div>Revision 01 | Effective 01/01/2024</div>
    <div>Printed by ' . esc($printedBy) . ' on ' . esc($nowPrinted) . '</div>
  </div>
</div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('chroot', __DIR__);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('ticket_' . $ticketId . '.pdf', ['Attachment' => false]);
