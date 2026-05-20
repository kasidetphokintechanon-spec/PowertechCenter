<?php
// api/actions/update_loan_status.php
$loan_id = $_POST['id'] ?? 0;
$new_status = $_POST['status'] ?? ''; // 'Active' or 'Completed'
$signature_path = $_POST['signature'] ?? '';
$return_condition = trim((string)($_POST['return_condition'] ?? ''));
$return_notes = trim((string)($_POST['return_notes'] ?? ''));
$user_name = $_SESSION['display_name'] ?? 'Admin';

if (!$loan_id || !$new_status) throw new Exception("Loan ID and Status are required.");

if ($new_status === 'Active') {
    $stmt = $conn->prepare("UPDATE it_assets SET status = 'ถูกยืม' WHERE id = ? AND status = 'รออนุมัติ'");
    if (!$stmt) throw new Exception("Prepare failed (Approve Loan): " . $conn->error);
    $stmt->bind_param("i", $loan_id);
    if ($stmt->execute()) {
        $asset_res = $conn->query("SELECT * FROM it_assets WHERE id = $loan_id");
        if ($asset = $asset_res->fetch_assoc()) {
            $hist_stmt = $conn->prepare("INSERT INTO it_loan_history (equipment_id, equipment_name, borrower, department, borrower_id, purpose, expected_return_date, approver_name, lend_date, borrower_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            if ($hist_stmt) {
                $hist_stmt->bind_param("sssssssss", $asset['equipment_id'], $asset['name'], $asset['borrower'], $asset['department'], $asset['borrower_id'], $asset['purpose'], $asset['return_date'], $user_name, $signature_path);
                $hist_stmt->execute();
            }
            logAssetHistory($conn, $loan_id, 'LOAN_APPROVED', "Loan approved for " . ($asset['borrower'] ?? 'user') . " by " . $user_name);
            
            $msg = "<b>✅ Loan Approved</b>\n<b>🏢 Company:</b> " . ($asset['company']??'-') . "\n<b>Asset:</b> {$asset['name']} ({$asset['equipment_id']})\n<b>Borrower:</b> {$asset['borrower']}\n<b>Approved By:</b> {$user_name}";
            sendTelegramNotification($conn, $msg, 'loan');
        }
        echo json_encode(['status' => 'success']);
    } else throw new Exception("Execute failed (Approve Loan): " . $stmt->error);
} elseif ($new_status === 'Completed') {
    if ($return_condition === '') {
        throw new Exception("กรุณาระบุผลตรวจสภาพก่อนคืนอุปกรณ์");
    }
    $asset_res = $conn->query("SELECT equipment_id, name, borrower, company FROM it_assets WHERE id = $loan_id");
    $asset_data = $asset_res ? $asset_res->fetch_assoc() : null;
    $equipment_id = $asset_data['equipment_id'] ?? '';
    
    $stmt = $conn->prepare("UPDATE it_assets SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE id = ? AND status = 'ถูกยืม'");
    if (!$stmt) throw new Exception("Prepare failed (Return Loan): " . $conn->error);
    $stmt->bind_param("i", $loan_id);
    if ($stmt->execute()) {
        if ($equipment_id) {
            $hist_stmt = $conn->prepare("UPDATE it_loan_history SET return_date = NOW(), return_receiver_name = ?, return_approver_signature = ?, return_condition = ?, return_notes = ? WHERE equipment_id = ? AND return_date IS NULL ORDER BY history_id DESC LIMIT 1");
            if ($hist_stmt) { $hist_stmt->bind_param("sssss", $user_name, $signature_path, $return_condition, $return_notes, $equipment_id); $hist_stmt->execute(); }
        }
        logAssetHistory($conn, $loan_id, 'LOAN_RETURNED', "Asset returned and received by " . $user_name . " | Condition: " . $return_condition);
        
        if ($asset_data) {
            $msg = "<b>🔄 Asset Returned</b>\n<b>🏢 Company:</b> " . ($asset_data['company']??'-') . "\n<b>Asset:</b> {$asset_data['name']} ({$equipment_id})\n<b>Returned By:</b> {$asset_data['borrower']}\n<b>Receiver:</b> {$user_name}\n<b>Condition:</b> {$return_condition}";
            sendTelegramNotification($conn, $msg, 'loan');
        }
        
        echo json_encode(['status' => 'success']);
    } else throw new Exception("Execute failed (Return Loan): " . $stmt->error);
} else throw new Exception("Invalid status for loan update.");
?>
