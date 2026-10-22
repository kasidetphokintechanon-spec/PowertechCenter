<?php
// api/actions/server_check/get_items_sc.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$room_id = (int)($_GET['room_id'] ?? 0);
if (!$room_id) { echo json_encode(['status'=>'error','message'=>'missing room_id']); exit; }

$stmt = $conn->prepare(
    "SELECT id, room_id, item_no, label, input_type, threshold, total_count, sort_order
     FROM sc_items WHERE room_id = ? AND is_active = 1 ORDER BY sort_order, id"
);
$stmt->bind_param("i", $room_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(['status' => 'success', 'items' => $items]);
