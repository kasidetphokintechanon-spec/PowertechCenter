<?php
// api/actions/car/car_update.php — คนจองแก้ใบของตัวเองได้ระหว่าง "รออนุมัติ" (กดผิด ขับเอง/ขอคนขับ เวลา ที่ไป)
// อนุมัติแล้วแก้เองไม่ได้ — ผู้ดูแลจัดรถ/คนขับไปแล้ว ต้องคุยกับผู้ดูแล หรือยกเลิกแล้วจองใหม่
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b) throw new Exception('ไม่พบใบจอง');
if ((string)$b['booker_id'] !== (string)$_SESSION['user_id']) throw new Exception('แก้ได้เฉพาะการจองของตัวเอง');
if ($b['status'] !== 'pending') throw new Exception('อนุมัติแล้ว แก้เองไม่ได้ — ติดต่อผู้ดูแลรถ หรือยกเลิกแล้วจองใหม่');
$carId = (int)($_POST['car_id'] ?? $b['car_id']);
$st = $conn->prepare("SELECT id FROM cr_cars WHERE id = ? AND is_active = 1"); $st->bind_param('i', $carId); $st->execute();
if (!$st->get_result()->fetch_row()) throw new Exception('ไม่พบรถ หรือรถคันนี้ปิดการจองอยู่');
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$purpose = $f('purpose', 255); $dest = $f('destination', 255);
if (mb_strlen($purpose) < 2) throw new Exception('ใส่ว่าเอารถไปทำอะไร');
if ($dest === '') throw new Exception('ใส่สถานที่ที่จะไป');
[$start, $end] = carCheckTime((string)($_POST['start'] ?? ''), (string)($_POST['end'] ?? ''), false);
$need = !empty($_POST['need_driver']) ? 1 : 0;
$driver = $need ? ((int)($_POST['driver_id'] ?? 0) ?: null) : null;
if ($driver) {
    $d = $conn->prepare("SELECT id FROM cr_drivers WHERE id = ? AND is_active = 1"); $d->bind_param('i', $driver); $d->execute();
    if (!$d->get_result()->fetch_row()) throw new Exception('ไม่พบคนขับ');
    if ($x = carDriverClash($conn, $driver, $start, $end, $b['id'])) throw new Exception('คนขับที่เลือกติดงานช่วงนั้น (' . date('H:i', strtotime($x['start_at'])) . '–' . date('H:i', strtotime($x['end_at'])) . ") — เลือก \"ให้ผู้ดูแลเลือก\" หรือคนอื่น");
}
$pax = max(0, min(99, (int)($_POST['passengers'] ?? 0)));
$rider = $f('rider_name', 255) ?: null; $names = $f('passenger_names', 1000); $contact = $f('contact', 100); $note = $f('note', 500);
$conn->begin_transaction();
try {
    $lk = $conn->prepare("SELECT id FROM cr_cars WHERE id = ? FOR UPDATE"); $lk->bind_param('i', $carId); $lk->execute(); $lk->get_result();
    if ($c = carClash($conn, $carId, $start, $end, $b['id'])) throw new Exception("รถคันนี้ไม่ว่าง — {$c['booker_name']} จองไว้ " . date('d/m H:i', strtotime($c['start_at'])) . '–' . date('H:i', strtotime($c['end_at'])));
    $u = $conn->prepare("UPDATE cr_bookings SET car_id = ?, need_driver = ?, driver_id = ?, purpose = ?, destination = ?, start_at = ?, end_at = ?, passengers = ?, passenger_names = ?, rider_name = ?, contact = ?, note = ? WHERE id = ? AND status = 'pending'");
    $u->bind_param('iiissssissssi', $carId, $need, $driver, $purpose, $dest, $start, $end, $pax, $names, $rider, $contact, $note, $b['id']); $u->execute();
    if ($u->affected_rows < 0) throw new Exception('บันทึกไม่สำเร็จ');
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
echo json_encode(['status' => 'success']);
