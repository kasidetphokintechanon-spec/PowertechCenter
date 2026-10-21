<?php
// api/actions/server_check/save_item_sc.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$id         = (int)($_POST['id'] ?? 0);
$room_id    = (int)($_POST['room_id'] ?? 0);
$label      = trim(strip_tags($_POST['label'] ?? ''));
$input_type = $_POST['input_type'] ?? 'status';
$threshold  = ($_POST['threshold'] ?? '') === '' ? null : (int)$_POST['threshold'];

$allowedTypes = ['status','percent','days','backup'];
if (!in_array($input_type, $allowedTypes, true)) { echo json_encode(['status'=>'error','message'=>'input_type ไม่ถูกต้อง']); exit; }
if ($label === '') { echo json_encode(['status'=>'error','message'=>'กรุณากรอกชื่อรายการ']); exit; }
if ($input_type !== 'days') $threshold = null; // threshold only meaningful for 'days'

if ($id) {
    $stmt = $conn->prepare("UPDATE sc_items SET label=?, input_type=?, threshold=? WHERE id=?");
    $stmt->bind_param("ssii", $label, $input_type, $threshold, $id);
    if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>$stmt->error]); exit; }
    echo json_encode(['status'=>'success','id'=>$id]);
} else {
    if (!$room_id) { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }
    $row    = $conn->query("SELECT COALESCE(MAX(sort_order),0) FROM sc_items WHERE room_id=" . (int)$room_id)->fetch_row();
    $nextNo = (int)($row[0] ?? 0) + 1;
    $stmt = $conn->prepare("INSERT INTO sc_items (room_id,item_no,label,input_type,threshold,sort_order,is_active) VALUES (?,?,?,?,?,?,1)");
    $stmt->bind_param("iissii", $room_id, $nextNo, $label, $input_type, $threshold, $nextNo);
    if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>$stmt->error]); exit; }
    echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
}
