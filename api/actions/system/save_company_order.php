<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'employee.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$orderRaw = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($orderRaw) || empty($orderRaw)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$order = [];
foreach ($orderRaw as $code) {
    $normalized = strtoupper(trim((string)$code));
    if ($normalized === '') continue;
    if (!preg_match('/^[A-Z0-9_\\-]{2,20}$/', $normalized)) continue;
    if (!in_array($normalized, $order, true)) $order[] = $normalized;
}
if (empty($order)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order']);
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$beforeValue = '';
$beforeRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'directory_company_order' LIMIT 1");
if ($beforeRes && $beforeRow = $beforeRes->fetch_assoc()) {
    $beforeValue = (string)($beforeRow['setting_value'] ?? '');
}

$value = json_encode($order, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('directory_company_order', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $value);
$stmt->execute();

if (function_exists('logAssetHistory')) {
    logAssetHistory(
        $conn,
        0,
        'DIRECTORY_COMPANY_ORDER_UPDATE',
        'before=' . $beforeValue . ' | after=' . $value
    );
}

echo json_encode(['status' => 'success', 'order' => $order]);
?>
