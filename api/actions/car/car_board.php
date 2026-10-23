<?php
// api/actions/car/car_board.php — ผู้ดูแลรถ · สิทธิ์ car.manage · เฉพาะรถของบริษัทที่ดูแล
// view: pending = รออนุมัติทั้งหมด · active = ออกรถอยู่ / รอรับกุญแจ / เลยเวลาคืน · อื่น ๆ = ใบที่ทับช่วง date ถึง +days
_initCarTables($conn);
$view = (string)($_GET['view'] ?? 'pending');
[$w, $p] = carScopeSql($conn); $t = str_repeat('s', count($p));
if ($view === 'pending') $cond = "b.status = 'pending' AND b.end_at > NOW()";
elseif ($view === 'active') $cond = "(b.status = 'returning' OR (b.status = 'approved' AND b.start_at <= NOW() + INTERVAL 1 HOUR))";
else {
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
    $days = max(1, min(31, (int)($_GET['days'] ?? 1)));
    $cond = "b.status IN ('pending','approved','returning','returned') AND b.start_at < ? AND b.end_at > ?";
    array_unshift($p, date('Y-m-d', strtotime("$date +$days day")) . ' 00:00:00', $date . ' 00:00:00'); $t = 'ss' . $t;
}
$st = $conn->prepare("SELECT b.*, c.plate, c.province, c.name AS car_name, c.brand, c.seats, c.company AS car_company, d.name AS driver_name, d.phone AS driver_phone
    FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id WHERE {$cond}{$w} ORDER BY b.start_at LIMIT 300");
if ($t) $st->bind_param($t, ...$p);
$st->execute();
$rows = array_map('carRow', $st->get_result()->fetch_all(MYSQLI_ASSOC));
$drivers = $conn->query("SELECT id, name, nickname, phone FROM cr_drivers WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
echo json_encode(['status' => 'success', 'data' => $rows, 'drivers' => $drivers, 'todo' => carKeeperTodo($conn)], JSON_UNESCAPED_UNICODE);
