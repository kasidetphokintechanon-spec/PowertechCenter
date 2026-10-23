<?php
// api/actions/car/car_my.php — การจองรถของฉัน: ใบที่ยังไม่จบ + ย้อนหลัง 90 วัน
_initCarTables($conn);
$uid = (string)$_SESSION['user_id'];
$me = trim((string)($_SESSION['display_name'] ?? ''));   // ใบที่ติดรถไปด้วย (ชื่ออยู่ในผู้ร่วมเดินทาง) ก็แสดง
$st = $conn->prepare("SELECT b.*, c.plate, c.province, c.name AS car_name, c.brand, c.seats, c.company AS car_company, d.name AS driver_name, d.phone AS driver_phone
    FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id
    WHERE (b.booker_id = ? OR (? <> '' AND b.passenger_names LIKE CONCAT('%', ?, '%'))) AND (b.status IN ('pending','approved','returning') OR b.end_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
    ORDER BY b.status IN ('pending','approved','returning') DESC, IF(b.status IN ('pending','approved','returning'), b.start_at, NULL) ASC, b.start_at DESC LIMIT 100");
$st->bind_param('sss', $uid, $me, $me); $st->execute();
$rows = array_map(function ($r) use ($me) {
    $r = carRow($r);
    $r['joined'] = !$r['mine'] && in_array($me, array_map('trim', preg_split('/\s*,\s*/u', (string)$r['passenger_names'])), true);
    return $r;
}, $st->get_result()->fetch_all(MYSQLI_ASSOC));
echo json_encode(['status' => 'success', 'now' => date('Y-m-d H:i:s'), 'data' => array_values(array_filter($rows, fn($r) => $r['mine'] || $r['joined']))], JSON_UNESCAPED_UNICODE);
