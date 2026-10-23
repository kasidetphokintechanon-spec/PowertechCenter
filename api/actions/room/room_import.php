<?php
// api/actions/room/room_import.php — นำเข้าห้อง + การจองจากระบบ roomsys เดิม (ครั้งเดียวตอนเปิดใช้) · เฉพาะ Admin กลาง
// รับ JSON จากสคริปต์ส่งออกที่รันบนเครื่องเดิม · ซ้ำได้ (ใช้ legacy_id กันนำเข้าซ้ำ) · ห้องที่มีอยู่แล้วไม่ทับข้อมูลที่แก้ไปแล้ว
_initRoomTables($conn);
if (($_SESSION['role'] ?? '') !== 'admin') throw new Exception('เฉพาะ Admin กลาง');
$j = json_decode((string)($_POST['payload'] ?? ''), true);
if (!is_array($j) || !isset($j['rooms'], $j['reservations'])) throw new Exception('ข้อมูลนำเข้าไม่ถูกต้อง');
// รหัสของเดิม (tb_equip) → ของที่ขอเตรียมแบบใหม่
$EQ = ['01' => 'welcome', '02' => 'water', '03' => 'coffee', '04' => 'coffee', '05' => 'snack', '06' => 'food', '07' => 'fruit', '08' => 'snack', '09' => 'projector', '10' => 'projector', '11' => 'confcall', '12' => 'mic', '13' => 'mic'];
$map = [];
foreach ($conn->query("SELECT id, legacy_id FROM rm_rooms WHERE legacy_id IS NOT NULL")->fetch_all() as [$id, $lg]) $map[(int)$lg] = (int)$id;
$newRooms = 0;
$ins = $conn->prepare("INSERT INTO rm_rooms (name, building, seats, contact_name, contact_phone, note, is_active, sort_order, legacy_id, company) VALUES (?,?,?,?,?,?,?,?,?,?)");
foreach ($j['rooms'] as $r) {
    $lg = (int)$r['id']; if (isset($map[$lg])) continue;
    $name = trim(preg_replace('/^RM\d+-/', '', (string)$r['name'])) ?: (string)$r['name'];
    $b = trim((string)$r['place']); $s = (int)$r['sit_amount']; $cn = trim((string)$r['person_name']); $cp = trim((string)$r['phone']); $nt = trim((string)$r['detail']);
    $act = (int)$r['status'] === 0 ? 1 : 0; $so = $lg;
    // ระบบเดิมไม่มีบริษัท — เดาจากชื่อห้อง (เช่น PTE Meeting Room) ที่เหลือให้ผู้ดูแลตั้งเอง
    $co = preg_match('/(PTA|PT4|PTE)/i', (string)$r['name'], $mm) ? strtoupper($mm[1]) : null;
    $ins->bind_param('ssisssiiis', $name, $b, $s, $cn, $cp, $nt, $act, $so, $lg, $co); $ins->execute();
    $map[$lg] = $conn->insert_id; $newRooms++;
}
$have = array_flip(array_map('intval', array_column($conn->query("SELECT legacy_id FROM rm_bookings WHERE legacy_id IS NOT NULL")->fetch_all(), 0)));
$emp = array_flip(array_column($conn->query("SELECT id FROM employees")->fetch_all(), 0));
$bi = $conn->prepare("INSERT INTO rm_bookings (code, room_id, title, start_at, end_at, attendees, booker_id, booker_name, department, contact, extras, extras_note, status, cancel_reason, legacy_id, created_at, host, participants, note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$n = 0; $skip = 0;
foreach ($j['reservations'] as $v) {
    $lg = (int)$v['id'];
    if (isset($have[$lg]) || !isset($map[(int)$v['room_id']])) { $skip++; continue; }
    $ex = [];
    foreach (array_filter(array_map('trim', explode(',', (string)$v['equipment']))) as $e) if (isset($EQ[substr($e, 0, 2)])) $ex[] = $EQ[substr($e, 0, 2)];
    $ex = implode(',', array_values(array_unique($ex)));
    $code = 'RS-' . ($v['docno'] ?: $lg);
    $rid = $map[(int)$v['room_id']]; $title = mb_substr(trim((string)$v['title']) ?: 'ประชุม', 0, 255);
    $s = (string)$v['start_date']; $e = (string)$v['end_date']; $att = (int)$v['human_amount'];
    $bid = isset($emp[(string)$v['user_code']]) ? (string)$v['user_code'] : null;
    $bn = trim((string)($v['person_name'] ?: $v['user_name'])) ?: '-'; $dp = (string)$v['depart_name']; $tel = (string)$v['tel'];
    $note = mb_substr(trim((string)$v['equipment_other']), 0, 500);
    $cancel = (int)$v['cancel_doc'] === 1 || (int)$v['status'] === 4;
    $stt = $cancel ? 'cancelled' : 'booked'; $cr = $cancel ? (mb_substr((string)$v['cancel_comment'], 0, 500) ?: 'ยกเลิกในระบบเดิม') : null;
    $ca = (string)($v['date_create'] ?: $s);
    $host = mb_substr(trim((string)($v['manager'] ?? '')), 0, 255); $parts = mb_substr(trim((string)($v['human_name'] ?? '')), 0, 1000); $gn = mb_substr(trim((string)($v['comment'] ?? '')), 0, 500);
    $bi->bind_param('sissssisssssssissss', $code, $rid, $title, $s, $e, $att, $bid, $bn, $dp, $tel, $ex, $note, $stt, $cr, $lg, $ca, $host, $parts, $gn);
    try { $bi->execute(); $n++; } catch (Throwable $x) { $skip++; }
}
echo json_encode(['status' => 'success', 'rooms_added' => $newRooms, 'bookings_added' => $n, 'skipped' => $skip]);
