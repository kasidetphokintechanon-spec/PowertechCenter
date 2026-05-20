<?php
// api/actions/system/save_line_settings.php
$channel_token = $_POST['channel_token'] ?? '';
$dest_id = $_POST['dest_id'] ?? '';
$active = $_POST['active'] ?? '0';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $settings = [
        'line_channel_token' => $channel_token,
        'line_dest_id' => $dest_id,
        'line_active' => $active
    ];

    foreach ($settings as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
?>