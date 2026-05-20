<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'asset.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order) || empty($order)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}
$stmt = $conn->prepare("UPDATE locations SET sort_order = ? WHERE id = ?");
foreach ($order as $index => $id) {
    $sortOrder = $index + 1;
    $stmt->bind_param("ii", $sortOrder, $id);
    $stmt->execute();
}
if (function_exists('logAssetHistory')) {
    logAssetHistory($conn, 0, 'LOCATIONS_REORDER', 'order=' . json_encode($order, JSON_UNESCAPED_UNICODE));
}
echo json_encode(['status' => 'success']);
?>
