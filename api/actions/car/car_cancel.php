<?php
// api/actions/car/car_cancel.php — ยกเลิกใบจอง (รถว่างให้คนอื่นทันที) · เจ้าของ (ก่อนออกรถ) หรือผู้ดูแลรถคันนั้น · ไม่ลบใบ เก็บเหตุผล
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['pending', 'approved'], true)) throw new Exception('ไม่พบใบจอง หรือปิดไปแล้ว');
$mine = (string)$b['booker_id'] === (string)$_SESSION['user_id'];
$keeper = carKeeps($conn, $b['car_company']);
if (!$mine && !$keeper) throw new Exception('ยกเลิกได้เฉพาะการจองของตัวเอง');
// อนุมัติแล้วและถึงเวลาใช้รถ ต้องกด "คืนรถ" แทน — ไม่งั้นประวัติจะไม่รู้ว่ากุญแจอยู่กับใคร
if (!$keeper && $b['status'] === 'approved' && strtotime($b['start_at']) <= time()) throw new Exception('ถึงเวลาใช้รถแล้ว — ถ้าใช้เสร็จให้กด "คืนรถ" แทน');
$reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500) ?: ($mine ? 'ผู้จองยกเลิกเอง' : 'ผู้ดูแลรถยกเลิก');
$by = carActor();
$u = $conn->prepare("UPDATE cr_bookings SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?, cancel_reason = ? WHERE id = ? AND status IN ('pending','approved')");
$u->bind_param('ssi', $by, $reason, $b['id']); $u->execute();
if ($u->affected_rows < 1) throw new Exception('ใบจองนี้ถูกเปลี่ยนไปแล้ว');
carNotify($conn, 'car_cancel', '↩️ ยกเลิกการจองรถ', $b, ['โดย' => $by, 'เหตุผล' => $reason]);
echo json_encode(['status' => 'success']);
