<?php
// api/actions/car/car_return.php — คนจองกด "คืนรถแล้ว" → รอผู้ดูแลรับกุญแจ (ใบยังไม่ปิดจนผู้ดูแลกดรับคืน)
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || $b['status'] !== 'approved') throw new Exception('ใบนี้คืนรถไม่ได้ (ยังไม่อนุมัติ หรือคืนไปแล้ว)');
if ((string)$b['booker_id'] !== (string)$_SESSION['user_id'] && !carKeeps($conn, $b['car_company'])) throw new Exception('คืนรถได้เฉพาะคนที่จอง');
if (strtotime($b['start_at']) > time() + 3600) throw new Exception('ยังไม่ถึงเวลาใช้รถ — ถ้าไม่ใช้แล้วให้กด "ยกเลิก"');
$note = mb_substr(trim(strip_tags((string)($_POST['note'] ?? ''))), 0, 500);
$u = $conn->prepare("UPDATE cr_bookings SET status = 'returning', returned_at = NOW(), return_note = ? WHERE id = ? AND status = 'approved'");
$u->bind_param('si', $note, $b['id']); $u->execute();
if ($u->affected_rows < 1) throw new Exception('ใบจองนี้ถูกเปลี่ยนไปแล้ว');
carNotify($conn, 'car_return', '🔑 คืนรถ — รอรับกุญแจ', $b, ['หมายเหตุ' => $note]);
echo json_encode(['status' => 'success']);
