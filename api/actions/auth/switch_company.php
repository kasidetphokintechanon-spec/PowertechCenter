<?php
// api/actions/switch_company.php

$target_company = $_POST['company'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (!$user_id || !$target_company) throw new Exception("Invalid request.");

// ตรวจสอบว่า User มีสิทธิ์ในบริษัทปลายทางหรือไม่
$stmt = $conn->prepare("SELECT department FROM employee_assignments WHERE employee_id = ? AND company = ?");
$stmt->bind_param("ss", $user_id, $target_company);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // อัปเดต Session เป็นบริษัทใหม่
    $_SESSION['company'] = $target_company;
    $_SESSION['primary_department'] = $row['department'];
    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("คุณไม่มีสิทธิ์เข้าใช้งานบริษัทนี้");
}
?>