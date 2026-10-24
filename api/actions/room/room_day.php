<?php
// api/actions/room/room_day.php — ห้องทั้งหมด + การจองที่ทับวันที่เลือก (ตารางดูห้องว่าง) · ต้องล็อกอิน (ด่านกลาง)
// ชื่อผู้จอง/แผนก/เบอร์ โชว์ให้คนในบริษัทเห็น — ไว้ติดต่อขอแลกห้อง (ระบบเดิมก็โชว์)
_initRoomTables($conn);
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date'] ?? '')) ? $_GET['date'] : date('Y-m-d');
$rooms = $conn->query("SELECT id, name, building, seats, company, equipment, contact_name, contact_phone, photo, note, needs_approval FROM rm_rooms WHERE is_active = 1 ORDER BY sort_order, name")->fetch_all(MYSQLI_ASSOC);
$allEx = ROOM_EXTRAS;
foreach ($rooms as &$r) {
    $r['id'] = (int)$r['id']; $r['seats'] = (int)$r['seats']; $r['needs_approval'] = (int)$r['needs_approval'];
    $ex = roomExtrasFor($conn, $r['company']); $r['extras'] = array_keys($ex); $allEx += $ex;
    $rl = roomRules($conn, $r['company']); $r['open_from'] = $rl['open_from']; $r['open_to'] = $rl['open_to'];
}
unset($r);
// days=7 = มุมมอง 7 วัน
$days = max(1, min(14, (int)($_GET['days'] ?? 1)));
$from = $date . ' 00:00:00'; $to = date('Y-m-d', strtotime("$date +$days day")) . ' 00:00:00';
$st = $conn->prepare("SELECT id, code, room_id, title, start_at, end_at, attendees, booker_id, booker_name, department, contact, extras, prepared_at, status, host, participants, has_guest, guest_company, note FROM rm_bookings
    WHERE status IN ('booked','pending') AND start_at < ? AND end_at > ? ORDER BY start_at");
$st->bind_param('ss', $to, $from); $st->execute();
$book = array_map('roomRow', $st->get_result()->fetch_all(MYSQLI_ASSOC));
$extras = [];
foreach ($allEx as $k => $v) $extras[] = ['key' => $k, 't' => $v['t'], 'i' => $v['i']];
echo json_encode(['status' => 'success', 'date' => $date, 'days' => $days, 'now' => date('Y-m-d H:i:s'), 'rooms' => $rooms, 'bookings' => $book, 'extras' => $extras,
    'can_manage' => roomCanManage($conn)], JSON_UNESCAPED_UNICODE);
