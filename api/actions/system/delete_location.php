<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'asset.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$id = $_POST['id'] ?? '';
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$beforeJson = '';
$beforeStmt = $conn->prepare("SELECT id, name, company, is_active, image FROM locations WHERE id = ? LIMIT 1");
if ($beforeStmt) {
    $beforeStmt->bind_param("i", $id);
    $beforeStmt->execute();
    $beforeRes = $beforeStmt->get_result();
    if ($beforeRes && $row = $beforeRes->fetch_assoc()) {
        $beforeJson = json_encode($row, JSON_UNESCAPED_UNICODE);
        if (!empty($row['image']) && file_exists($row['image'])) {
            @unlink($row['image']);
        }
    }
}

$stmt = $conn->prepare("DELETE FROM locations WHERE id = ?");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    if (function_exists('logAssetHistory')) {
        logAssetHistory($conn, 0, 'LOCATION_DELETE', $beforeJson ?: ('id=' . intval($id)));
    }
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}
?>
