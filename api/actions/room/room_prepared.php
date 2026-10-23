<?php
// api/actions/room/room_prepared.php — ผู้ดูแลกด "เตรียมแล้ว" (ผู้จองเห็นในการจองของฉัน) · กดซ้ำ = ยกเลิกสถานะ · สิทธิ์ room.manage
_initRoomTables($conn);
$b = roomGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || $b['status'] !== 'booked') throw new Exception('ไม่พบการจอง หรือยกเลิกไปแล้ว');
if (!roomKeeps($conn, $b['room_id'])) throw new Exception('ไม่ได้ดูแลห้องนี้');
$by = roomActor();
if ($b['prepared_at']) { $u = $conn->prepare("UPDATE rm_bookings SET prepared_at = NULL, prepared_by = NULL WHERE id = ?"); $u->bind_param('i', $b['id']); }
else { $u = $conn->prepare("UPDATE rm_bookings SET prepared_at = NOW(), prepared_by = ? WHERE id = ?"); $u->bind_param('si', $by, $b['id']); }
$u->execute();
echo json_encode(['status' => 'success', 'prepared' => !$b['prepared_at']]);
