<?php
// api/actions/room/room_prep.php — ผู้ดูแลห้อง: การจองในห้องที่ดูแล ตั้งแต่วันที่เลือก ไป N วัน · สิทธิ์ room.manage ($ACTION_PERMS)
_initRoomTables($conn);
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$days = max(1, min(14, (int)($_GET['days'] ?? 1)));
$from = $date . ' 00:00:00'; $to = date('Y-m-d', strtotime("$date +$days day")) . ' 00:00:00';
$kept = roomKeptIds($conn);
$w = "b.status IN ('booked','pending') AND b.start_at < ? AND b.end_at > ?"; $p = [$to, $from]; $t = 'ss';
// รออนุมัติ: ทุกใบที่ยังไม่จบ ไม่จำกัดช่วงวัน — ใบที่จองล่วงหน้าไกลจะได้ไม่ตกหล่น
if (!empty($_GET['pending'])) { $w = "b.status = 'pending' AND b.end_at > NOW()"; $p = []; $t = ''; }
if ($kept !== null) { $w .= ' AND b.room_id IN (' . implode(',', array_fill(0, count($kept), '?')) . ')'; $p = array_merge($p, $kept); $t .= str_repeat('i', count($kept)); }
$st = $conn->prepare("SELECT b.*, r.name AS room_name, r.building FROM rm_bookings b JOIN rm_rooms r ON r.id = b.room_id WHERE {$w} ORDER BY b.start_at");
if ($t) $st->bind_param($t, ...$p);
$st->execute();
echo json_encode(['status' => 'success', 'date' => $date, 'days' => $days, 'all_rooms' => $kept === null, 'data' => array_map('roomRow', $st->get_result()->fetch_all(MYSQLI_ASSOC))], JSON_UNESCAPED_UNICODE);
