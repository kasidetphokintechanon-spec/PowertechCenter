<?php
// api/actions/server_check/save_room.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$id       = (int)($_POST['id'] ?? 0);
$code     = trim(strip_tags($_POST['code'] ?? ''));
$name     = trim(strip_tags($_POST['name'] ?? ''));
$location = trim(strip_tags($_POST['location'] ?? ''));
$company  = strtoupper(trim($_POST['company'] ?? ''));

if ($code === '')                            { echo json_encode(['status'=>'error','message'=>'กรุณากรอกรหัสห้อง']); exit; }
if ($name === '')                            { echo json_encode(['status'=>'error','message'=>'กรุณากรอกชื่อห้อง']); exit; }
if (!in_array($company, ['PTA','PT4','PTE'], true)) { echo json_encode(['status'=>'error','message'=>'บริษัทไม่ถูกต้อง']); exit; }

if ($id) {
    $stmt = $conn->prepare("UPDATE sc_rooms SET code=?, name=?, location=?, company=? WHERE id=?");
    $stmt->bind_param("ssssi", $code, $name, $location, $company, $id);
    if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>$stmt->error]); exit; }
    echo json_encode(['status'=>'success','id'=>$id]);
} else {
    $maxOrd = (int)($conn->query("SELECT COALESCE(MAX(sort_order),0) FROM sc_rooms")->fetch_row()[0] ?? 0);
    $sort_order = $maxOrd + 1;
    $stmt = $conn->prepare("INSERT INTO sc_rooms (code,name,location,company,sort_order,is_active) VALUES (?,?,?,?,?,1)");
    $stmt->bind_param("ssssi", $code, $name, $location, $company, $sort_order);
    if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>$stmt->error]); exit; }
    echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
}
