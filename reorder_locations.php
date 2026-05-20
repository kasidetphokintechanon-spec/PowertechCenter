<?php
// api/actions/system/reorder_locations.php
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'staff')) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
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

echo json_encode(['status' => 'success']);
?>