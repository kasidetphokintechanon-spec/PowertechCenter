<?php
// api/actions/denyRequest.php
$loan_id = $_POST['id'] ?? 0;
if (!$loan_id) throw new Exception("Loan ID is required.");

$stmt = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE id = ? AND status = 'รออนุมัติ'");
if (!$stmt) throw new Exception("Prepare failed (Deny Request): " . $conn->error);

$stmt->bind_param("i", $loan_id);
if ($stmt->execute()) {
    logAssetHistory($conn, $loan_id, 'LOAN_DENIED', "Loan request denied by admin.");
    
    $asset_res = $conn->query("SELECT name, equipment_id, borrower, company FROM it_assets WHERE id = $loan_id");
    if ($asset = $asset_res->fetch_assoc()) {
        $msg = "<b>🚫 Loan Request Denied</b>\n<b>🏢 Company:</b> " . ($asset['company']??'-') . "\n<b>Asset:</b> {$asset['name']} ({$asset['equipment_id']})\n<b>User:</b> " . ($asset['borrower'] ?? 'Unknown');
        sendTelegramNotification($conn, $msg, 'loan');
    }
    
    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("Execute failed (Deny Request): " . $stmt->error);
}
?>