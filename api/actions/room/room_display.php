<?php
// api/actions/room/room_display.php — ข้อมูลจอหน้าห้อง: ห้อง + การจองวันนี้ · ต้องล็อกอิน (แท็บเล็ตหน้าห้องล็อกอินค้างไว้ ไม่เปิดให้คนนอก — TISAX)
_initRoomTables($conn);
$id = (int)($_GET['room_id'] ?? 0);
$st = $conn->prepare("SELECT id, name, building, seats, photo FROM rm_rooms WHERE id = ?");
$st->bind_param('i', $id); $st->execute();
$room = $st->get_result()->fetch_assoc();
if (!$room) throw new Exception('ไม่พบห้อง');
$from = date('Y-m-d 00:00:00'); $to = date('Y-m-d 00:00:00', strtotime('+1 day'));
$st = $conn->prepare("SELECT code, title, start_at, end_at, attendees, booker_name, department, host, has_guest, guest_company FROM rm_bookings WHERE room_id = ? AND status = 'booked' AND start_at < ? AND end_at > ? ORDER BY start_at");
$st->bind_param('iss', $id, $to, $from); $st->execute();
echo json_encode(['status' => 'success', 'now' => date('Y-m-d H:i:s'), 'room' => $room, 'bookings' => $st->get_result()->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE);
