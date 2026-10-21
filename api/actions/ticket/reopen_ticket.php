<?php
// api/actions/ticket/reopen_ticket.php
// User reports the issue is NOT resolved → Awaiting Confirmation → In Progress

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$ticket_id = trim($_POST['ticket_id'] ?? '');
$reason    = trim($_POST['reason']    ?? '');

if (!$ticket_id) throw new Exception("Missing ticket_id");
if (!$reason)    throw new Exception("กรุณาระบุเหตุผลที่ปัญหายังไม่ได้รับการแก้ไข");

$role = $_SESSION['role'] ?? 'guest';

$stmt = $conn->prepare(
    "SELECT id, status, requester_id, requester, servicedBy, company
     FROM it_logs WHERE id = ? LIMIT 1"
);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("s", $ticket_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) throw new Exception("Ticket not found");
if ($row['status'] !== 'Awaiting Confirmation') {
    throw new Exception("Ticket is not in Awaiting Confirmation status (current: {$row['status']})");
}

// Only the requester or admin/staff may reopen
if (
    !in_array($role, ['admin', 'staff'], true) &&
    (string)($row['requester_id'] ?? '') !== (string)$_SESSION['user_id']
) {
    throw new Exception("Access Denied");
}

$actor = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_id']));
$now   = date('Y-m-d H:i:s');
$note  = '[' . date("d/m/Y H:i") . "] Reopen by {$actor}: {$reason}";

// Append reopen note to solution log
$s2 = $conn->prepare("SELECT solution FROM it_logs WHERE id = ? LIMIT 1");
if ($s2) {
    $s2->bind_param("s", $ticket_id);
    $s2->execute();
    $sRow = $s2->get_result()->fetch_assoc();
}
$oldSol = trim((string)($sRow['solution'] ?? ''));
$newSol = $oldSol === '' ? $note : ($oldSol . "\n" . $note);

// Reset awaiting_confirmation_at if column exists (so timer resets if IT re-sends)
$colCheck = $conn->query("SHOW COLUMNS FROM it_logs LIKE 'awaiting_confirmation_at'");
$hasAwaitCol = ($colCheck && $colCheck->num_rows > 0);

$sqlUpdate = $hasAwaitCol
    ? "UPDATE it_logs SET status='In Progress', solution=?, last_work_start_time=?, closed_date=NULL, completed_date=NULL, awaiting_confirmation_at=NULL WHERE id=?"
    : "UPDATE it_logs SET status='In Progress', solution=?, last_work_start_time=?, closed_date=NULL, completed_date=NULL WHERE id=?";

$s3 = $conn->prepare($sqlUpdate);
if (!$s3) throw new Exception("Prepare failed: " . $conn->error);
$s3->bind_param("sss", $newSol, $now, $ticket_id);
if (!$s3->execute()) throw new Exception("Update failed: " . $s3->error);

// Notify IT team via Telegram
$msg = "<b>🔄 Ticket Reopened ↩️</b>\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>🏢 Company:</b> "   . ($row['company']    ?? '-') . "\n" .
       "<b>🎫 Ticket:</b> <code>{$ticket_id}</code>\n" .
       "<b>👤 User:</b> "      . ($row['requester']  ?? '-') . "\n" .
       "<b>👨‍🔧 Tech:</b> "   . ($row['servicedBy'] ?? '-') . "\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>❗ เหตุผล:</b> {$reason}\n" .
       "<b>🕒 Time:</b> " . date("d/m/Y H:i") . "\n" .
       "\n#Reopened #UserFeedback";
sendTelegramNotification($conn, $msg, 'repair');

echo json_encode(['status' => 'success', 'message' => 'Ticket reopened successfully.']);
?>
