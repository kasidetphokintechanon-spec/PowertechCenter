<?php
// api/actions/setup/setupHandover.php — ขั้นส่งมอบเครื่องในใบติดตั้ง
//   GET  op=contact&employee_id=&company=   อีเมลพนักงาน (จากสังกัด) ไว้ใส่ช่องผู้รับเมล
//   POST op=email|signed|undo_email|undo_signed  id, [to], [date]
// สิทธิ์ (IT) เช็กที่ด่านกลางใน api.php
require_once __DIR__ . '/../../../includes/setup_handover.php';
ensureSetupHandoverColumns($conn);

$op = $_REQUEST['op'] ?? '';

if ($op === 'contact') {
    $empId = trim((string)($_GET['employee_id'] ?? ''));
    $company = trim((string)($_GET['company'] ?? ''));
    if ($empId === '') { echo json_encode(['status' => 'success', 'email' => '']); exit; }
    // บริษัทเดียวกับใบติดตั้งก่อน → สังกัดหลัก → อันไหนก็ได้ที่มีอีเมล
    $st = $conn->prepare("SELECT email FROM employee_assignments WHERE employee_id = ? AND email IS NOT NULL AND email <> ''
                          ORDER BY (company = ?) DESC, is_primary DESC LIMIT 1");
    $st->bind_param('ss', $empId, $company);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    echo json_encode(['status' => 'success', 'email' => $row['email'] ?? ''], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed');
$id = (int)($_POST['id'] ?? 0);
requireSetupInScope($conn, $id);
if ($id <= 0) throw new Exception('ต้องบันทึกใบติดตั้งก่อน');
$by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'IT';

if ($op === 'email') {
    $to = mb_substr(trim((string)($_POST['to'] ?? '')), 0, 200);
    $st = $conn->prepare("UPDATE setup_records SET email_sent_at = NOW(), email_sent_to = ?, email_sent_by = ? WHERE id = ?");
    $st->bind_param('ssi', $to, $by, $id);
} elseif ($op === 'signed') {
    $date = $_POST['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
    $st = $conn->prepare("UPDATE setup_records SET signed_at = ?, signed_by = ? WHERE id = ?");
    $st->bind_param('ssi', $date, $by, $id);
} elseif ($op === 'undo_email') {
    $st = $conn->prepare("UPDATE setup_records SET email_sent_at = NULL, email_sent_to = NULL, email_sent_by = NULL WHERE id = ?");
    $st->bind_param('i', $id);
} elseif ($op === 'undo_signed') {
    $st = $conn->prepare("UPDATE setup_records SET signed_at = NULL, signed_by = NULL WHERE id = ?");
    $st->bind_param('i', $id);
} else {
    throw new Exception('Unknown op');
}
$st->execute();

$r = $conn->prepare("SELECT email_sent_at, email_sent_to, email_sent_by, signed_at, signed_by FROM setup_records WHERE id = ?");
$r->bind_param('i', $id);
$r->execute();
echo json_encode(['status' => 'success', 'handover' => $r->get_result()->fetch_assoc()], JSON_UNESCAPED_UNICODE);
