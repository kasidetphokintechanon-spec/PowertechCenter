<?php
// api/actions/car/car_day.php — รถที่เปิดให้จอง + ใบจองที่ทับวันที่เลือก (ตารางดูรถว่าง) · ต้องล็อกอิน (ด่านกลาง)
// ใบที่คืนรถแล้วก็แสดง (สีเทา) — ย้อนดูวันก่อน ๆ ได้ว่าใครใช้คันไหน
// ชื่อผู้จอง/แผนก โชว์ให้เห็น — ไว้ติดต่อขอแลกคิวรถ (ระบบเดิมก็โชว์)
_initCarTables($conn);
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$cars = $conn->query("SELECT id, plate, province, name, brand, car_type, seats, color, company, photo, note FROM cr_cars WHERE is_active = 1 ORDER BY sort_order, plate")->fetch_all(MYSQLI_ASSOC);
foreach ($cars as &$c) { $c['id'] = (int)$c['id']; $c['seats'] = (int)$c['seats']; }
unset($c);
$from = $date . ' 00:00:00'; $to = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
$st = $conn->prepare("SELECT b.id, b.code, b.car_id, b.purpose, b.destination, b.start_at, b.end_at, b.passengers, b.passenger_names, b.booker_id, b.booker_name, b.rider_name, b.department, b.contact, b.status, b.need_driver, d.name AS driver_name
    FROM cr_bookings b LEFT JOIN cr_drivers d ON d.id = b.driver_id WHERE b.status IN ('pending','approved','returning','returned') AND b.start_at < ? AND b.end_at > ? ORDER BY b.start_at");
$st->bind_param('ss', $to, $from); $st->execute();
echo json_encode(['status' => 'success', 'date' => $date, 'now' => date('Y-m-d H:i:s'), 'cars' => $cars, 'bookings' => array_map('carRow', $st->get_result()->fetch_all(MYSQLI_ASSOC)),
    'can_manage' => carCanManage($conn)], JSON_UNESCAPED_UNICODE);
