<?php
$keys = [
    'it_support_phone', 'it_support_line',
    'sla_normal', 'sla_urgent', 'sla_critical', 'sla_hours',
    'line_qr_pte', 'line_qr_pt4', 'line_qr_pta'
];
$in   = implode(',', array_fill(0, count($keys), '?'));
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($in)");
$stmt->bind_param(str_repeat('s', count($keys)), ...$keys);
$stmt->execute();
$res = $stmt->get_result();
$s = [];
while ($row = $res->fetch_assoc()) $s[$row['setting_key']] = $row['setting_value'];

echo json_encode([
    'status'     => 'success',
    'phone'      => $s['it_support_phone'] ?? 'ต่อภายใน 1234',
    'line'       => $s['it_support_line']  ?? '@PTCITSupport',
    'sla_normal'   => $s['sla_normal']   ?? 'ภายใน 4 ชม. (วันทำการ)',
    'sla_urgent'   => $s['sla_urgent']   ?? 'ภายใน 2 ชม.',
    'sla_critical' => $s['sla_critical'] ?? 'ภายใน 30 นาที',
    'sla_hours'    => $s['sla_hours']    ?? 'จ.–ศ. 08:00–17:30',
    'line_qr_pte'  => $s['line_qr_pte'] ?? '',
    'line_qr_pt4'  => $s['line_qr_pt4'] ?? '',
    'line_qr_pta'  => $s['line_qr_pta'] ?? '',
]);
