<?php
// api/actions/car/car_drivers_at.php — คนขับแต่ละคนว่างไหมในช่วงเวลาของใบจอง (ใช้ตอนอนุมัติ/เปลี่ยนคนขับ) · สิทธิ์ car.manage
_initCarTables($conn);
$b = carGet($conn, (int)($_GET['id'] ?? 0));
if (!$b) throw new Exception('ไม่พบใบจอง');
$out = [];
foreach ($conn->query("SELECT id, name, nickname, phone FROM cr_drivers WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC) as $d) {
    $d['id'] = (int)$d['id'];
    $d['busy'] = carDriverClash($conn, $d['id'], $b['start_at'], $b['end_at'], $b['id']);
    $out[] = $d;
}
echo json_encode(['status' => 'success', 'drivers' => $out, 'current' => $b['driver_id'] !== null ? (int)$b['driver_id'] : null], JSON_UNESCAPED_UNICODE);
