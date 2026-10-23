<?php
// api/actions/room/room_cancel.php — ยกเลิกการจอง (ห้องว่างให้คนอื่นทันที) · เจ้าของ หรือผู้ดูแลห้องนั้น · ไม่ลบใบ เก็บเหตุผล
_initRoomTables($conn);
$b = roomGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['booked', 'pending'], true)) throw new Exception('ไม่พบการจอง หรือยกเลิกไปแล้ว');
$mine = (string)$b['booker_id'] === (string)$_SESSION['user_id'];
if (!$mine && !roomKeeps($conn, $b['room_id'])) throw new Exception('ยกเลิกได้เฉพาะการจองของตัวเอง');
$reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500) ?: ($mine ? 'ผู้จองยกเลิกเอง' : 'ผู้ดูแลห้องยกเลิก');
$by = roomActor();
$u = $conn->prepare("UPDATE rm_bookings SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ? AND status IN ('booked','pending')");
$u->bind_param('ssi', $by, $reason, $b['id']); $u->execute();
if ($u->affected_rows < 1) throw new Exception('การจองนี้ถูกเปลี่ยนไปแล้ว');
roomNotify($conn, 'room_cancel', '↩️ ยกเลิกการจองห้อง — ไม่ต้องเตรียมของ', $b, ['โดย' => $by, 'เหตุผล' => $reason]);
echo json_encode(['status' => 'success']);
