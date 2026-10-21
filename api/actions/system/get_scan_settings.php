<?php
$keys = ['asset_scan_url_primary','asset_scan_url_backup','it_line_group_url','line_qr_pte','line_qr_pt4','line_qr_pta','line_id_pte','line_id_pt4','line_id_pta'];
$in   = implode(',', array_fill(0, count($keys), '?'));
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
$stmt->bind_param(str_repeat('s', count($keys)), ...$keys);
$stmt->execute();
$s = [];
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $s[$r['setting_key']] = $r['setting_value'];
echo json_encode([
    'status'       => 'success',
    'primary_url'  => $s['asset_scan_url_primary'] ?? 'http://192.168.0.2/PowertechCenter',
    'backup_url'   => $s['asset_scan_url_backup']  ?? 'https://powertecha.myqnapcloud.com:8081/PowertechCenter',
    'line_group_url' => $s['it_line_group_url'] ?? '',
    'line_qr_pte'   => $s['line_qr_pte']       ?? '',
    'line_qr_pt4'   => $s['line_qr_pt4']       ?? '',
    'line_qr_pta'   => $s['line_qr_pta']       ?? '',
    'line_id_pte'   => $s['line_id_pte']       ?? '',
    'line_id_pt4'   => $s['line_id_pt4']       ?? '',
    'line_id_pta'   => $s['line_id_pta']       ?? '',
]);
