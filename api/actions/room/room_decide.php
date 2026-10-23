<?php
// api/actions/room/room_decide.php — อนุมัติ / ไม่อนุมัติการจองห้องที่ต้องอนุมัติ · ผู้ดูแลห้องนั้น (สิทธิ์ room.manage)
_initRoomTables($conn);
$b = roomGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || $b['status'] !== 'pending') throw new Exception('ไม่มีการจองที่รออนุมัติ');
if (!roomKeeps($conn, $b['room_id'])) throw new Exception('ไม่ได้ดูแลห้องนี้');
$ok = ($_POST['decision'] ?? '') === 'approve';
$reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500);
if (!$ok && $reason === '') throw new Exception('ใส่เหตุผลที่ไม่อนุมัติ (ผู้จองจะเห็น)');
$by = roomActor();
if ($ok) { $u = $conn->prepare("UPDATE rm_bookings SET status = 'booked', prepared_by = NULL WHERE id = ? AND status = 'pending'"); $u->bind_param('i', $b['id']); }
else { $u = $conn->prepare("UPDATE rm_bookings SET status = 'rejected', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ? AND status = 'pending'"); $u->bind_param('ssi', $by, $reason, $b['id']); }
$u->execute();
if ($u->affected_rows < 1) throw new Exception('การจองนี้ถูกเปลี่ยนไปแล้ว');
$b['status'] = $ok ? 'booked' : 'rejected';
if ($ok) roomNotify($conn, 'room_book', '✅ อนุมัติใช้ห้อง — ขอเตรียมของ', $b, ['อนุมัติโดย' => $by]);
echo json_encode(['status' => 'success']);
