<?php
$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$defaultOrder = ['PTA', 'PT4', 'PTE'];
$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'directory_company_order' LIMIT 1");
$value = '';
if ($res && $row = $res->fetch_assoc()) {
    $value = (string)($row['setting_value'] ?? '');
}

$rawOrder = json_decode($value, true);
if (!is_array($rawOrder) || empty($rawOrder)) {
    $rawOrder = $defaultOrder;
}

$order = [];
foreach ($rawOrder as $code) {
    $normalized = strtoupper(trim((string)$code));
    if ($normalized === '') continue;
    if (!preg_match('/^[A-Z0-9_\\-]{2,20}$/', $normalized)) continue;
    if (!in_array($normalized, $order, true)) $order[] = $normalized;
}

if (empty($order)) $order = $defaultOrder;
echo json_encode(['status' => 'success', 'order' => $order]);
?>
