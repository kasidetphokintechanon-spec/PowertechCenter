<?php
// api/actions/system/save_telegram_settings.php
$token = $_POST['token'] ?? '';
$chat_id = $_POST['chat_id'] ?? '';
$active = $_POST['active'] ?? '0';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    
    $settings = [
        'telegram_token' => $token,
        'telegram_chat_id' => $chat_id,
        'telegram_active' => $active
    ];

    foreach ($settings as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }
    
    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>