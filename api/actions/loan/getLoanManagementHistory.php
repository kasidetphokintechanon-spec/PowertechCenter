<?php
// api/actions/getLoanManagementHistory.php
$sql = "SELECT 
            history_id, equipment_id, equipment_name, borrower, borrower_id, lend_date, 
            expected_return_date, return_date, approver_name, return_receiver_name
        FROM it_loan_history
        ORDER BY lend_date DESC, history_id DESC";
$result = $conn->query($sql);
if (!$result) throw new Exception("SQL Error in getLoanManagementHistory: " . $conn->error);
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
?>