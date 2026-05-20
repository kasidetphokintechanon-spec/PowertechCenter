<?php
// api/actions/system/get_line_settings.php
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('line_channel_token', 'line_dest_id', 'line_active')");
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
echo json_encode([
    'status' => 'success',
    'channel_token' => $settings['line_channel_token'] ?? '',
    'dest_id' => $settings['line_dest_id'] ?? '',
    'active' => $settings['line_active'] ?? '0'
]);
?>