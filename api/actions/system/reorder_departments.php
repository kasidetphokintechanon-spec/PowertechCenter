<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'employee.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order) || empty($order)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$colCheck = $conn->query("SHOW COLUMNS FROM departments LIKE 'sort_order'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE departments ADD COLUMN sort_order INT NOT NULL DEFAULT 999 AFTER dept_abbr");
}

$stmt = $conn->prepare("UPDATE departments SET sort_order = ? WHERE dept_id = ?");
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}

try {
    $conn->begin_transaction();
    foreach ($order as $index => $id) {
        $sortOrder = $index + 1;
        $deptId = (string)$id;
        $stmt->bind_param("is", $sortOrder, $deptId);
        $stmt->execute();
    }
    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}

if (function_exists('logAssetHistory')) {
    logAssetHistory($conn, 0, 'DEPARTMENTS_REORDER', 'order=' . json_encode($order, JSON_UNESCAPED_UNICODE));
}

echo json_encode(['status' => 'success']);
?>
