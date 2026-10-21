<?php
// api/actions/system/save_it_links_config.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$raw  = $_POST['config'] ?? '';
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['companies']) || !is_array($data['companies'])) {
    echo json_encode(['status' => 'error', 'message' => 'invalid config']);
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(191) PRIMARY KEY,
        setting_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$json = json_encode($data, JSON_UNESCAPED_UNICODE);
$stmt = $conn->prepare(
    "INSERT INTO system_settings (setting_key, setting_value) VALUES ('it_links_config', ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);
$stmt->bind_param('s', $json);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
