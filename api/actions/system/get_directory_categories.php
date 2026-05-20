<?php
$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$defaultItems = [
    ['key' => 'management', 'label' => 'ระดับ Management'],
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

$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'directory_category_nav' LIMIT 1");
$value = '';
if ($res && $row = $res->fetch_assoc()) {
    $value = (string)($row['setting_value'] ?? '');
}

$raw = json_decode($value, true);
if (!is_array($raw)) $raw = [];

$items = [];
$seen = [];
foreach ($raw as $it) {
    $key = is_array($it) ? (string)($it['key'] ?? '') : (string)$it;
    $key = strtolower(trim($key));
    if ($key === '' || !in_array($key, $allowed, true)) continue;
    if (isset($seen[$key])) continue;
    $label = is_array($it) ? trim((string)($it['label'] ?? '')) : '';
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

echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);
?>
