<?php
// api/actions/update_department_phone.php
$company = $_POST['company'] ?? '';
$department = $_POST['department'] ?? '';
$sub_department = $_POST['sub_department'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($sub_department === '') $sub_department = null;

$stmt = $conn->prepare("INSERT INTO department_contacts (company, department, sub_department, phone) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE phone = VALUES(phone)");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("ssss", $company, $department, $sub_department, $phone);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("Update failed: " . $stmt->error);
}
?>