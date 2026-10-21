<?php
// api/actions/server_check/delete_holiday.php
$_role = $_SESSION['role'] ?? 'guest';
$_dept = $_SESSION['primary_department'] ?? '';
if (!isset($_SESSION['user_id']) || ($_role !== 'admin' && $_dept !== 'IT')) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized — IT only']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['status'=>'error','message'=>'missing id']); exit; }

$conn->query("DELETE FROM company_holidays WHERE id=$id");
echo json_encode(['status' => 'success']);
