<?php
// api/actions/server_check/delete_holiday.php
$_role = $_SESSION['role'] ?? 'guest';
$_dept = $_SESSION['primary_department'] ?? '';
// แผนก IT ในระบบชื่อ "Information Technology" (เดิมเช็กแค่ "IT" ช่างที่ไม่ใช่ admin เลยแก้ไม่ได้)
if (!isset($_SESSION['user_id']) || ($_role !== 'admin' && !in_array($_dept, ['IT', 'Information Technology'], true))) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized — IT only']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['status'=>'error','message'=>'missing id']); exit; }

$conn->query("DELETE FROM company_holidays WHERE id=$id");
echo json_encode(['status' => 'success']);
