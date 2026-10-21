<?php
// api/actions/server_check/reorder_rooms.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order) || empty($order)) { echo json_encode(['status'=>'error','message'=>'Invalid data']); exit; }

$stmt = $conn->prepare("UPDATE sc_rooms SET sort_order = ? WHERE id = ?");
foreach ($order as $index => $id) {
    $sortOrder = $index + 1;
    $rid = (int)$id;
    $stmt->bind_param("ii", $sortOrder, $rid);
    $stmt->execute();
}

echo json_encode(['status' => 'success']);
