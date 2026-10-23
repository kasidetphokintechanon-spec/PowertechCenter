<?php
// api/actions/car/car_join.php — "ขอติดรถไปด้วย" / ออกจากการติดรถ · ต้องล็อกอิน (ด่านกลาง)
// รถคันเดียวกันจองทับเวลาไม่ได้ — ถ้าจะไปทางเดียวกัน ให้ติดรถใบที่มีอยู่แทน ชื่อเข้าไปอยู่ในผู้ร่วมเดินทาง (ประวัติรู้ว่าใครอยู่บนรถ)
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['pending', 'approved'], true) || strtotime($b['end_at']) < time()) throw new Exception('ใบจองนี้ติดรถไม่ได้แล้ว (ปิดไปแล้ว หรือเลยเวลา)');
if ((string)$b['booker_id'] === (string)$_SESSION['user_id']) throw new Exception('เป็นใบจองของคุณเอง');
$me = trim((string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? ''));
if ($me === '') throw new Exception('ไม่พบชื่อผู้ใช้');
$list = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/u', (string)$b['passenger_names']))));
$has = in_array($me, $list, true);
$leave = ($_POST['mode'] ?? '') === 'leave';
if ($leave && !$has) throw new Exception('คุณไม่ได้อยู่ในรายชื่อผู้ร่วมเดินทาง');
if (!$leave && $has) throw new Exception('คุณอยู่ในรายชื่อผู้ร่วมเดินทางแล้ว');
$pax = (int)$b['passengers'];
if ($leave) { $list = array_values(array_diff($list, [$me])); $pax = max(1, $pax - 1); }
else {
    if ((int)$b['seats'] > 0 && max(1, $pax) >= (int)$b['seats']) throw new Exception("รถเต็มแล้ว (นั่งได้ {$b['seats']} คน)");
    $list[] = $me; $pax = max(1, $pax) + 1;   // ไม่ได้ใส่จำนวนคนไว้ = นับคนจอง 1 คน
}
$names = mb_substr(implode(', ', $list), 0, 1000);
$u = $conn->prepare("UPDATE cr_bookings SET passenger_names = ?, passengers = ? WHERE id = ? AND status IN ('pending','approved')");
$u->bind_param('sii', $names, $pax, $b['id']); $u->execute();
echo json_encode(['status' => 'success', 'joined' => !$leave, 'passengers' => $pax]);
