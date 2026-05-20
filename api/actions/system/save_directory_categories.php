<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
if (function_exists('hasPermission') && !hasPermission($conn, 'employee.manage') && !hasPermission($conn, 'admin.access_admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permission']);
    exit;
}

$configRaw = json_decode($_POST['config'] ?? '[]', true);
if (!is_array($configRaw) || empty($configRaw)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

$defaultItems = [
    ['key' => 'management', 'label' => 'Management'],
    ['key' => 'all', 'label' => 'พนักงานทั้งหมด'],
    ['key' => 'favorites', 'label' => 'ปักหมุด'],
    ['key' => 'executives', 'label' => 'ผู้บริหาร'],
    ['key' => 'inactive', 'label' => 'พนักงาน Inactive']
];
$allowed = ['management', 'all', 'favorites', 'executives', 'inactive'];
$defaultLabelMap = [];
foreach ($defaultItems as $it) {
    $defaultLabelMap[$it['key']] = $it['label'];
}

$items = [];
$seen = [];
foreach ($configRaw as $it) {
    if (!is_array($it)) continue;
    $key = strtolower(trim((string)($it['key'] ?? '')));
    if ($key === '' || !in_array($key, $allowed, true)) continue;
    if (isset($seen[$key])) continue;
    $label = trim((string)($it['label'] ?? ''));
    if ($label === '') $label = $defaultLabelMap[$key] ?? $key;
    if (mb_strlen($label, 'UTF-8') > 60) $label = mb_substr($label, 0, 60, 'UTF-8');
    $items[] = ['key' => $key, 'label' => $label];
    $seen[$key] = true;
}

foreach ($defaultItems as $it) {
    $k = $it['key'];
    if (!isset($seen[$k])) {
        $items[] = ['key' => $k, 'label' => $it['label']];
    }
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$beforeValue = '';
$beforeRes = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'directory_category_nav' LIMIT 1");
if ($beforeRes && $beforeRow = $beforeRes->fetch_assoc()) {
    $beforeValue = (string)($beforeRow['setting_value'] ?? '');
}

$value = json_encode($items, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('directory_category_nav', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
if (!$stmt) {
    throw new Exception("Prepare failed: " . $conn->error);
}
$stmt->bind_param("s", $value);
$stmt->execute();

if (function_exists('logAssetHistory')) {
    logAssetHistory(
        $conn,
        0,
        'DIRECTORY_CATEGORY_NAV_UPDATE',
        'before=' . $beforeValue . ' | after=' . $value
    );
}

echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);
?>
