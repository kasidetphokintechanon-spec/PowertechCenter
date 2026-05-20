<?php
// api/actions/requestEquipment.php
$data = json_decode($_POST['data'], true);
$eqId = $data['id'] ?? '';
$borrower = $data['borrowerName'] ?? '';
$dept = $data['department'] ?? '';
$empId = $data['employeeId'] ?? '';
$rDate = $data['returnDate'] ?? '';
$purpose = $data['purpose'] ?? '';

if (!$eqId) throw new Exception("Equipment ID is required.");

$stmt = $conn->prepare("UPDATE it_assets SET status = 'รออนุมัติ', borrower = ?, borrower_id = ?, department = ?, return_date = ?, purpose = ? WHERE equipment_id = ? AND status = 'ใช้งานปกติ'");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("ssssss", $borrower, $empId, $dept, $rDate, $purpose, $eqId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $res = $conn->query("SELECT id, name, company FROM it_assets WHERE equipment_id = '$eqId'");
        $row = $res->fetch_assoc();
        if ($row) {
            logAssetHistory($conn, $row['id'], 'LOAN_REQUEST', "Requested by $borrower for $purpose");
            
            $msg = "<b>📦 New Loan Request</b>\n" .
                   "<b>🏢 Company:</b> " . ($row['company']??'-') . "\n" .
                   "<b>Asset:</b> {$row['name']} ({$eqId})\n" .
                   "<b>User:</b> {$borrower}\n" .
                   "<b>Dept:</b> {$dept}\n" .
                   "<b>Return:</b> " . date('d/m/Y', strtotime($rDate)) . "\n" .
                   "<b>Purpose:</b> {$purpose}";
            sendTelegramNotification($conn, $msg, 'loan');
        }
        echo json_encode(['status' => 'success']);
    } else throw new Exception("Asset not available.");
} else throw new Exception($stmt->error);
?>