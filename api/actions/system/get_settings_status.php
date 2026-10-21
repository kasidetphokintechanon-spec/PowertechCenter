<?php
$keys = [
    'telegram_bot_token', 'telegram_chat_id',
    'line_channel_token', 'line_active',
    'it_support_phone', 'sla_normal',
    'notify_routing_enabled',
    'company_logo_path',
];
$in   = implode(',', array_fill(0, count($keys), '?'));
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
$stmt->bind_param(str_repeat('s', count($keys)), ...$keys);
$stmt->execute();
$res = $stmt->get_result();
$s = [];
while ($row = $res->fetch_assoc()) $s[$row['setting_key']] = $row['setting_value'];

echo json_encode([
    'status'    => 'success',
    'telegram'  => !empty($s['telegram_bot_token']) && !empty($s['telegram_chat_id']),
    'line'      => !empty($s['line_channel_token']) && ($s['line_active'] ?? '0') === '1',
    'it_support'=> !empty($s['it_support_phone']) || !empty($s['sla_normal']),
    'notify'    => !empty($s['notify_routing_enabled']) && $s['notify_routing_enabled'] === '1',
    'logo'      => !empty($s['company_logo_path']),
]);
