<?php
// api/actions/clear_loan_history.php
// ตรวจสอบสิทธิ์ Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    throw new Exception("Access denied. Admin only.");
}

$conn->begin_transaction();
try {
    // 1. ล้างข้อมูลประวัติการยืมทั้งหมด
    $conn->query("TRUNCATE TABLE it_loan_history");
    
    // 2. รีเซ็ตสถานะพัสดุที่ค้างอยู่ (ถูกยืม/รออนุมัติ) ให้กลับเป็นว่าง (ใช้งานปกติ)
    $conn->query("UPDATE it_assets SET status = 'ใช้งานปกติ', borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE status IN ('ถูกยืม', 'รออนุมัติ')");
    
    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) { $conn->rollback(); throw $e; }
?>