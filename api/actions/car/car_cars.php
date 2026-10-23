<?php
// api/actions/car/car_cars.php — รายการรถ + คนขับ (รวมที่ปิดอยู่) สำหรับหน้าผู้ดูแล · สิทธิ์ car.manage
_initCarTables($conn);
$cars = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM cr_bookings b WHERE b.car_id = c.id AND b.status IN ('approved','returning','returned') AND b.start_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)) AS used_90d
    FROM cr_cars c ORDER BY c.is_active DESC, c.sort_order, c.plate")->fetch_all(MYSQLI_ASSOC);
foreach ($cars as &$c) { $c['id'] = (int)$c['id']; $c['seats'] = (int)$c['seats']; $c['is_active'] = (int)$c['is_active']; $c['used_90d'] = (int)$c['used_90d']; $c['can_edit'] = carKeeps($conn, $c['company']); }
unset($c);
$drivers = $conn->query("SELECT * FROM cr_drivers ORDER BY is_active DESC, name")->fetch_all(MYSQLI_ASSOC);
$cos = carKeptCos($conn);
echo json_encode(['status' => 'success', 'cars' => $cars, 'drivers' => $drivers, 'companies' => $cos === null ? ACCESS_COS : $cos], JSON_UNESCAPED_UNICODE);
