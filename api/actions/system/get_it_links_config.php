<?php
// api/actions/system/get_it_links_config.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'it_links_config' LIMIT 1");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row && $row['setting_value']) {
    $data = json_decode($row['setting_value'], true);
    if (is_array($data) && isset($data['companies'])) {
        echo json_encode(['status' => 'success', 'exists' => true, 'config' => $data]);
        exit;
    }
}

echo json_encode(['status' => 'success', 'exists' => false, 'config' => null]);
