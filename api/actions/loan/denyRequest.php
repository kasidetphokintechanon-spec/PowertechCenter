<?php
// api/actions/loan/denyRequest.php
$loan_id = $_POST['id'] ?? 0;
$deny_reason = trim($_POST['deny_reason'] ?? '');
if (!$loan_id) throw new Exception("Loan ID is required.");

// Fetch asset info before clearing borrower fields
$pre = $conn->prepare("SELECT name, equipment_id, borrower, company FROM it_assets WHERE id = ? AND status = 'รออนุมัติ'");
if (!$pre) throw new Exception("Prepare failed: " . $conn->error);
$pre->bind_param("i", $loan_id);
$pre->execute();
$asset = $pre->get_result()->fetch_assoc();
if (!$asset) throw new Exception("คำขอยืมไม่พบหรือไม่อยู่ในสถานะรออนุมัติ");

$stmt = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE id = ? AND status = 'รออนุมัติ'");
if (!$stmt) throw new Exception("Prepare failed (Deny Request): " . $conn->error);

$stmt->bind_param("i", $loan_id);
if ($stmt->execute()) {
    $logNote = "Loan request denied by admin" . ($deny_reason ? ". Reason: {$deny_reason}" : ".");
    logAssetHistory($conn, $loan_id, 'LOAN_DENIED', $logNote);

    $reasonLine = $deny_reason ? "\n<b>Reason:</b> {$deny_reason}" : '';
    $msg = "<b>🚫 Loan Request Denied</b>\n<b>🏢 Company:</b> " . ($asset['company']??'-') . "\n<b>Asset:</b> {$asset['name']} ({$asset['equipment_id']})\n<b>User:</b> " . ($asset['borrower'] ?? 'Unknown') . $reasonLine;
    sendTelegramNotification($conn, $msg, 'loan');

    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("Execute failed (Deny Request): " . $stmt->error);
}
?>