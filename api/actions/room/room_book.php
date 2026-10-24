<?php
// api/actions/room/room_book.php — จองห้อง (ได้ทันที) · ผู้จองจากบัญชีที่ล็อกอิน · เวลาทับคนอื่นจองไม่ได้
_initRoomTables($conn);
$roomId = (int)($_POST['room_id'] ?? 0);
$st = $conn->prepare("SELECT id, name, seats, company, needs_approval FROM rm_rooms WHERE id = ? AND is_active = 1");
$st->bind_param('i', $roomId); $st->execute();
$room = $st->get_result()->fetch_assoc();
if (!$room) throw new Exception('ไม่พบห้อง หรือห้องปิดใช้งานอยู่');
$title = mb_substr(trim(strip_tags((string)($_POST['title'] ?? ''))), 0, 255);
if (mb_strlen($title) < 2) throw new Exception('ใส่หัวข้อการประชุม');
$keeper = roomKeeps($conn, $roomId);
[$start, $end] = roomCheckTime((string)($_POST['start'] ?? ''), (string)($_POST['end'] ?? ''), $keeper);
roomCheckRules($conn, $room, $start, $end, $keeper);
$att = max(0, min(999, (int)($_POST['attendees'] ?? 0)));
$extras = roomExtrasClean($_POST['extras'] ?? '[]', roomExtrasFor($conn, $room['company']));
$note = mb_substr(trim(strip_tags((string)($_POST['extras_note'] ?? ''))), 0, 500);
$contact = mb_substr(trim(strip_tags((string)($_POST['contact'] ?? ''))), 0, 100);
[$host, $parts, $guest, $gco, $gnote] = roomDetailFields($_POST);
if ($c = roomClash($conn, $roomId, $start, $end)) throw new Exception("ห้องไม่ว่าง — {$c['booker_name']} จองไว้ " . date('H:i', strtotime($c['start_at'])) . '–' . date('H:i', strtotime($c['end_at'])));
$uid = (string)$_SESSION['user_id'];
$name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$dept = (string)($_SESSION['primary_department'] ?? '');
$co = (string)($_SESSION['company'] ?? '');
$ex = implode(',', $extras);
// กันสองคนกดจองช่วงเดียวกันพร้อมกัน: ล็อกห้องก่อนเช็กซ้ำ
$conn->begin_transaction();
try {
    $lk = $conn->prepare("SELECT id FROM rm_rooms WHERE id = ? FOR UPDATE"); $lk->bind_param('i', $roomId); $lk->execute(); $lk->get_result();
    if ($c = roomClash($conn, $roomId, $start, $end)) throw new Exception("ห้องเพิ่งถูกจอง — {$c['booker_name']} " . date('H:i', strtotime($c['start_at'])) . '–' . date('H:i', strtotime($c['end_at'])));
    $code = roomNextCode($conn);
    // ห้องที่ตั้งให้ต้องอนุมัติ → รออนุมัติ (กันเวลาไว้แล้ว) · ผู้ดูแลห้องจองเองไม่ต้องอนุมัติ
    $status = (int)$room['needs_approval'] && !$keeper ? 'pending' : 'booked';
    $ins = $conn->prepare("INSERT INTO rm_bookings (code, room_id, title, start_at, end_at, attendees, booker_id, booker_name, department, company, contact, extras, extras_note, status, host, participants, has_guest, guest_company, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $ins->bind_param('sisssissssssssssiss', $code, $roomId, $title, $start, $end, $att, $uid, $name, $dept, $co, $contact, $ex, $note, $status, $host, $parts, $guest, $gco, $gnote); $ins->execute();
    $id = $conn->insert_id;
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
if ($status === 'pending') roomNotify($conn, 'room_book', '🕓 ขออนุมัติใช้ห้อง', roomGet($conn, $id), [], true);
else roomNotify($conn, 'room_book', '📅 จองห้อง — ขอเตรียมของ', roomGet($conn, $id));
echo json_encode(['status' => 'success', 'id' => $id, 'code' => $code, 'pending' => $status === 'pending', 'over_capacity' => $room['seats'] > 0 && $att > $room['seats']]);
