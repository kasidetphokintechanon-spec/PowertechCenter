<?php
// api/actions/system/get_pending_counts.php

// ไม่ต้องใส่ header('Content-Type: application/json'); เพราะ api.php ใส่ให้แล้ว

// 1. Count Pending Repairs
$repair_count = 0;
try {
    // ใช้ try-catch เพื่อป้องกัน Error 500 กรณีตาราง it_logs ยังไม่ถูกสร้าง
    $sql_repair = "SELECT COUNT(*) as count FROM it_logs WHERE status = 'Pending'";
    $res_repair = $conn->query($sql_repair);
    if ($res_repair) {
        $row = $res_repair->fetch_assoc();
        $repair_count = $row['count'];
    }
} catch (Throwable $e) {
    // กรณีเกิด Error ให้ข้ามไป (ค่าเป็น 0)
}

// 2. Count Pending Loans
$loan_count = 0;
try {
    $sql_loan = "SELECT COUNT(*) as count FROM it_loan_history WHERE approver_name IS NULL OR approver_name = ''";
    $res_loan = $conn->query($sql_loan);
    if ($res_loan) {
        $row = $res_loan->fetch_assoc();
        $loan_count = $row['count'];
    }
} catch (Throwable $e) {
    // Ignore error
}

echo json_encode([
    'status' => 'success',
    'repair_pending_count' => $repair_count,
    'loan_pending_count' => $loan_count
]);
?>