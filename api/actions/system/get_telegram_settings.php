<?php
// api/actions/system/get_telegram_settings.php
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('telegram_token', 'telegram_chat_id', 'telegram_active')");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
echo json_encode([
    'status' => 'success',
    'token' => $settings['telegram_token'] ?? '',
    'chat_id' => $settings['telegram_chat_id'] ?? '',
    'active' => $settings['telegram_active'] ?? '0'
]);
?>