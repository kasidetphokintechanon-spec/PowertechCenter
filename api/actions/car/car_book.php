<?php
// api/actions/car/car_book.php — ส่งคำขอจองรถ (ทุกใบรออนุมัติ) · ผู้จองจากบัญชีที่ล็อกอิน · เวลาทับใบอื่นของคันเดียวกันไม่ได้
_initCarTables($conn);
$carId = (int)($_POST['car_id'] ?? 0);
$st = $conn->prepare("SELECT id, plate, seats, company FROM cr_cars WHERE id = ? AND is_active = 1");
$st->bind_param('i', $carId); $st->execute();
$car = $st->get_result()->fetch_assoc();
if (!$car) throw new Exception('ไม่พบรถ หรือรถคันนี้ปิดการจองอยู่');
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$purpose = $f('purpose', 255);
if (mb_strlen($purpose) < 2) throw new Exception('ใส่ว่าเอารถไปทำอะไร');
$dest = $f('destination', 255);
if ($dest === '') throw new Exception('ใส่สถานที่ที่จะไป');
// ระบบเดิม: จองหลังออกรถไปแล้วก็มี (ออกก่อนค่อยมาจอง) — ยอมให้เวลาออกผ่านมาแล้วได้ ขอแค่ยังไม่ถึงเวลาคืน
[$start, $end] = carCheckTime((string)($_POST['start'] ?? ''), (string)($_POST['end'] ?? ''), false);
$pax = max(0, min(99, (int)($_POST['passengers'] ?? 0)));
$rider = $f('rider_name', 255);   // จองให้คนอื่น — ว่าง = คนจองใช้เอง
$names = $f('passenger_names', 1000); $contact = $f('contact', 100); $note = $f('note', 500);
$needDriver = !empty($_POST['need_driver']) ? 1 : 0;
// คนขับที่ต้องการ (ไม่บังคับ) — เป็น "คำขอ" ผู้ดูแลเปลี่ยนได้ตอนอนุมัติ · คนที่ติดงานช่วงนั้นเลือกไม่ได้
$driver = $needDriver ? ((int)($_POST['driver_id'] ?? 0) ?: null) : null;
if ($driver) {
    $d = $conn->prepare("SELECT id FROM cr_drivers WHERE id = ? AND is_active = 1"); $d->bind_param('i', $driver); $d->execute();
    if (!$d->get_result()->fetch_row()) throw new Exception('ไม่พบคนขับ');
    if ($x = carDriverClash($conn, $driver, $start, $end)) throw new Exception('คนขับที่เลือกติดงานช่วงนั้น (' . date('H:i', strtotime($x['start_at'])) . '–' . date('H:i', strtotime($x['end_at'])) . ') — เลือก "ให้ผู้ดูแลเลือก" หรือคนอื่น');
}
$uid = (string)$_SESSION['user_id'];
$name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$dept = (string)($_SESSION['primary_department'] ?? '');
$co = (string)($_SESSION['company'] ?? '');
// กันสองคนกดจองคันเดียวกันพร้อมกัน: ล็อกแถวรถก่อนเช็กซ้ำ
$conn->begin_transaction();
try {
    $lk = $conn->prepare("SELECT id FROM cr_cars WHERE id = ? FOR UPDATE"); $lk->bind_param('i', $carId); $lk->execute(); $lk->get_result();
    if ($c = carClash($conn, $carId, $start, $end)) throw new Exception("รถคันนี้ไม่ว่าง — {$c['booker_name']} จองไว้ " . date('d/m H:i', strtotime($c['start_at'])) . '–' . date('H:i', strtotime($c['end_at'])));
    $code = carNextCode($conn);
    $ins = $conn->prepare("INSERT INTO cr_bookings (code, car_id, need_driver, driver_id, purpose, destination, start_at, end_at, passengers, passenger_names, booker_id, booker_name, rider_name, department, company, contact, note, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')");
    $ins->bind_param('siiissssissssssss', $code, $carId, $needDriver, $driver, $purpose, $dest, $start, $end, $pax, $names, $uid, $name, $rider, $dept, $co, $contact, $note); $ins->execute();
    $id = $conn->insert_id;
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
$u = carUrgency($start);
carNotify($conn, 'car_book', '🚗 ขออนุมัติใช้รถ' . ($u ? " · {$u}" : ''), carGet($conn, $id), [], null, 'cars.html?tab=keep&view=pending', 'เปิดดู / อนุมัติ');
echo json_encode(['status' => 'success', 'id' => $id, 'code' => $code, 'over_seats' => $car['seats'] > 0 && $pax > $car['seats']]);
