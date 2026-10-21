<?php
// api/actions/ticket/auto_close_tickets.php
// Auto-closes tickets that have been in "Awaiting Confirmation" for > AUTO_CLOSE_DAYS days.
// Called as a non-blocking background fetch from the frontend on page load.

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

const AUTO_CLOSE_DAYS = 3;

// Ensure the tracking column exists (may not yet if update_item.php hasn't run)
$colCheck = $conn->query("SHOW COLUMNS FROM it_logs LIKE 'awaiting_confirmation_at'");
if (!$colCheck || $colCheck->num_rows === 0) {
    echo json_encode(['status' => 'success', 'closed' => 0, 'note' => 'column_not_ready']);
    exit;
}

$cutoff = date('Y-m-d H:i:s', strtotime('-' . AUTO_CLOSE_DAYS . ' days'));
$now    = date('Y-m-d H:i:s');

$stmt = $conn->prepare(
    "SELECT id, requester, servicedBy, company
     FROM it_logs
     WHERE status = 'Awaiting Confirmation'
       AND awaiting_confirmation_at IS NOT NULL
       AND awaiting_confirmation_at <= ?
     LIMIT 50"
);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("s", $cutoff);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$closed = 0;
foreach ($rows as $row) {
    $note = '[' . date("d/m/Y H:i") . "] Auto-closed: ผู้แจ้งไม่ยืนยันภายใน " . AUTO_CLOSE_DAYS . " วัน — ระบบปิดงานอัตโนมัติ";

    // Append auto-close note to solution
    $s2 = $conn->prepare("SELECT solution FROM it_logs WHERE id = ?");
    $s2->bind_param("s", $row['id']);
    $s2->execute();
    $sRow = $s2->get_result()->fetch_assoc();
    $oldSol = trim((string)($sRow['solution'] ?? ''));
    $newSol = $oldSol === '' ? $note : ($oldSol . "\n" . $note);

    // Update with optimistic lock — only if still Awaiting Confirmation
    $s3 = $conn->prepare(
        "UPDATE it_logs
         SET status='Completed', completed_date=?, closed_date=?,
             solution=?, user_confirmed=0
         WHERE id = ? AND status = 'Awaiting Confirmation'"
    );
    if (!$s3) continue;
    $s3->bind_param("ssss", $now, $now, $newSol, $row['id']);
    if ($s3->execute() && $conn->affected_rows > 0) {
        $closed++;

        // Notify IT group so the team is aware
        $msg = "<b>⏰ Auto-Closed Ticket</b>\n" .
               "➖➖➖➖➖➖➖➖➖➖\n" .
               "<b>🏢 Company:</b> "  . ($row['company']    ?? '-') . "\n" .
               "<b>🎫 Ticket:</b> <code>{$row['id']}</code>\n" .
               "<b>👤 User:</b> "     . ($row['requester']  ?? '-') . "\n" .
               "<b>👨‍🔧 Tech:</b> " . ($row['servicedBy']  ?? '-') . "\n" .
               "<b>📝 Reason:</b> ผู้แจ้งไม่ยืนยันภายใน " . AUTO_CLOSE_DAYS . " วัน\n" .
               "<b>🕒 Time:</b> " . date("d/m/Y H:i") . "\n" .
               "\n#AutoClosed #System";
        sendTelegramNotification($conn, $msg, 'repair');
    }
}

echo json_encode(['status' => 'success', 'closed' => $closed]);
?>
