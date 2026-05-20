<?php
// api/actions/cancel_loan_request.php
$loan_id = $_POST['id'] ?? 0; // This will be the asset's db_id
$user_id = $_SESSION['user_id'] ?? 0;

if (!$loan_id || !$user_id) {
    throw new Exception("Loan ID and User ID are required.");
}

// Security check: make sure the user cancelling is the one who borrowed it.
$stmt = $conn->prepare("
    UPDATE it_assets 
    SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL 
    WHERE id = ? AND status = 'รออนุมัติ' AND borrower_id = ?
");
if (!$stmt) throw new Exception("Prepare failed (Cancel Loan): " . $conn->error);

$stmt->bind_param("is", $loan_id, $user_id);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        logAssetHistory($conn, $loan_id, 'LOAN_CANCELLED', "Loan request cancelled by user.");
        echo json_encode(['status' => 'success', 'message' => 'คำขอของคุณถูกยกเลิกแล้ว']);
    } else {
        throw new Exception("ไม่สามารถยกเลิกคำขอได้ อาจจะถูกอนุมัติไปแล้ว");
    }
} else {
    throw new Exception("Execute failed (Cancel Loan): " . $stmt->error);
}
?>