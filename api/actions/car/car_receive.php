<?php
// api/actions/car/car_receive.php — ผู้ดูแลรับกุญแจคืน = ปิดใบ · สิทธิ์ car.manage
// รับได้ทั้งใบที่คนจองกดคืนแล้ว และใบที่ยังไม่กด (เอากุญแจมาคืนแต่ลืมกด) — ไม่ต้องตามให้คนจองกดก่อน
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['approved', 'returning'], true)) throw new Exception('ใบนี้ไม่ได้อยู่ระหว่างใช้รถ');
if (!carKeeps($conn, $b['car_company'])) throw new Exception('ไม่ได้ดูแลรถคันนี้');
if ($b['status'] === 'approved' && strtotime($b['start_at']) > time() + 3600) throw new Exception('ยังไม่ถึงเวลาใช้รถ — ถ้าไม่ใช้แล้วให้กด "ยกเลิก"');
$by = carActor();
$u = $conn->prepare("UPDATE cr_bookings SET status = 'returned', returned_at = COALESCE(returned_at, NOW()), received_at = NOW(), received_by = ? WHERE id = ? AND status IN ('approved','returning')");
$u->bind_param('si', $by, $b['id']); $u->execute();
if ($u->affected_rows < 1) throw new Exception('ใบจองนี้ถูกเปลี่ยนไปแล้ว');
echo json_encode(['status' => 'success']);
