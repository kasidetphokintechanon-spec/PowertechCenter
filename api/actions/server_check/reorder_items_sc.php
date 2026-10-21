<?php
// api/actions/server_check/reorder_items_sc.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order) || empty($order)) { echo json_encode(['status'=>'error','message'=>'Invalid data']); exit; }

$stmt = $conn->prepare("UPDATE sc_items SET sort_order = ?, item_no = ? WHERE id = ?");
foreach ($order as $index => $id) {
    $pos = $index + 1;
    $iid = (int)$id;
    $stmt->bind_param("iii", $pos, $pos, $iid);
    $stmt->execute();
}

echo json_encode(['status' => 'success']);
