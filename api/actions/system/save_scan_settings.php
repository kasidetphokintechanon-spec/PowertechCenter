<?php
$pairs = [
    'asset_scan_url_primary' => trim($_POST['primary_url']    ?? ''),
    'asset_scan_url_backup'  => trim($_POST['backup_url']     ?? ''),
    'it_line_group_url'      => trim($_POST['line_group_url'] ?? ''),
    'line_qr_pte'            => trim($_POST['line_qr_pte']    ?? ''),
    'line_qr_pt4'            => trim($_POST['line_qr_pt4']    ?? ''),
    'line_qr_pta'            => trim($_POST['line_qr_pta']    ?? ''),
    'line_id_pte'            => trim($_POST['line_id_pte']    ?? ''),
    'line_id_pt4'            => trim($_POST['line_id_pt4']    ?? ''),
    'line_id_pta'            => trim($_POST['line_id_pta']    ?? ''),
];
$stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
foreach ($pairs as $k => $v) {
    $stmt->bind_param('ss', $k, $v);
    $stmt->execute();
}
echo json_encode(['status' => 'success']);
