<?php
$settings = [
    'it_support_phone' => $_POST['phone']        ?? '',
    'it_support_line'  => $_POST['line']         ?? '',
    'sla_normal'       => $_POST['sla_normal']   ?? '',
    'sla_urgent'       => $_POST['sla_urgent']   ?? '',
    'sla_critical'     => $_POST['sla_critical'] ?? '',
    'sla_hours'        => $_POST['sla_hours']    ?? '',
];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settings as $k => $v) { $stmt->bind_param('ss', $k, $v); $stmt->execute(); }
    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
