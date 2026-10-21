<?php
// api/actions/system/get_stock_summary_schedule.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']); exit;
}

$keys = ['stock_summary_enabled', 'stock_summary_time', 'stock_summary_company', 'stock_summary_stock_type', 'stock_summary_last_sent'];
$in   = implode(',', array_map(fn($k) => "'$k'", $keys));
$res  = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
$settings = [];
while ($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

echo json_encode([
    'status'    => 'success',
    'enabled'   => ($settings['stock_summary_enabled'] ?? '0') === '1',
    'time'      => $settings['stock_summary_time']      ?? '08:00',
    'company'    => $settings['stock_summary_company']    ?? '',
    'stock_type' => $settings['stock_summary_stock_type'] ?? '',
    'last_sent'  => $settings['stock_summary_last_sent']  ?? '',
]);
