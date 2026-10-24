<?php
// api/actions/ticket/confirm_resolution.php
// User confirms that their issue has been resolved → moves ticket from Awaiting Confirmation → Completed

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$ticket_id = trim($_POST['ticket_id'] ?? '');
if (!$ticket_id) throw new Exception("Missing ticket_id");

// Schema: ensure user_signature column exists
$colCheck = $conn->query("SHOW COLUMNS FROM it_logs LIKE 'user_signature'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE it_logs ADD COLUMN user_signature MEDIUMTEXT DEFAULT NULL AFTER user_confirmed_by");
}

$role = $_SESSION['role'] ?? 'guest';

$stmt = $conn->prepare("SELECT id, status, requester_id, doc_number FROM it_logs WHERE id = ? LIMIT 1");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("s", $ticket_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) throw new Exception("Ticket not found");
if ($row['status'] !== 'Awaiting Confirmation') {
    throw new Exception("Ticket is not awaiting confirmation (current status: {$row['status']})");
}

// Allow requester or admin/staff to confirm
if (!in_array($role, ['admin', 'staff'], true) && (string)($row['requester_id'] ?? '') !== (string)$_SESSION['user_id']) {
    throw new Exception("Access Denied: only the requester or admin can confirm resolution");
}

$confirmedBy = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id']));
$now = date('Y-m-d H:i:s');

// Accept optional base64 digital signature
$userSig = trim($_POST['user_signature'] ?? '');
// Validate: must be a PNG data URI or empty
if ($userSig !== '' && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $userSig)) {
    $userSig = '';
}

$stmt2 = $conn->prepare(
    "UPDATE it_logs SET status='Completed', completed_date=?, closed_date=?, user_confirmed=1, user_confirmed_at=?, user_confirmed_by=?, user_signature=? WHERE id=?"
);
if (!$stmt2) throw new Exception("Prepare failed: " . $conn->error);
$stmt2->bind_param("ssssss", $now, $now, $now, $confirmedBy, $userSig, $ticket_id);
if (!$stmt2->execute()) throw new Exception("Update failed: " . $stmt2->error);

// Send Telegram notification
$notifyStmt = $conn->prepare("SELECT requester, problem, servicedBy, company FROM it_logs WHERE id = ? LIMIT 1");
$notifyStmt->bind_param("s", $ticket_id);
$notifyStmt->execute();
$ticketQ = $notifyStmt->get_result();
if ($ticketQ && $ticketRow = $ticketQ->fetch_assoc()) {
    $docNum = $row['doc_number'] ?? '-';
    require_once __DIR__ . '/../../../includes/notify_helper.php';
    ticketNotify($conn, 'it_approve', 'ผู้แจ้งยืนยันแล้ว ปิดงาน', $ticket_id, ['ช่าง' => $ticketRow['servicedBy'] ?? '', 'ยืนยันโดย' => $confirmedBy, 'เลขใบซ่อม' => $docNum]);
}

echo json_encode(['status' => 'success', 'message' => 'Resolution confirmed. Ticket is now Completed.']);
?>
