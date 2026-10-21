<?php
// api/actions/server_check/delete_room.php — soft delete (keeps historical sc_records/sc_values intact)
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$id = (int)($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['status'=>'error','message'=>'missing id']); exit; }

$stmt = $conn->prepare("UPDATE sc_rooms SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $id);
if (!$stmt->execute()) { echo json_encode(['status'=>'error','message'=>$stmt->error]); exit; }

$conn->query("UPDATE sc_items SET is_active = 0 WHERE room_id = " . (int)$id);

echo json_encode(['status'=>'success']);
