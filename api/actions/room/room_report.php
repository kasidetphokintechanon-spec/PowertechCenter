<?php
// api/actions/room/room_report.php — การใช้ห้องรายเดือน: จำนวนครั้ง ชั่วโมงที่ใช้ อัตราการใช้ (ในเวลาทำการ 08–17) ยกเลิก ตามแผนก · สิทธิ์ room.manage
_initRoomTables($conn);
$m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$from = "$m-01 00:00:00"; $to = date('Y-m-d 00:00:00', strtotime("$m-01 +1 month"));
// วันทำการ จ-ศ ของเดือน × 9 ชม. = ชั่วโมงที่ห้องเปิดให้ใช้
$wd = 0; for ($d = strtotime("$m-01"); $d < strtotime($to); $d += 86400) if (date('N', $d) < 6) $wd++;
$st = $conn->prepare("SELECT r.id, r.name, r.building, r.seats,
        SUM(b.status = 'booked') AS n, SUM(b.status = 'cancelled') AS cancelled,
        COALESCE(SUM(IF(b.status = 'booked', TIMESTAMPDIFF(MINUTE, GREATEST(b.start_at, ?), LEAST(b.end_at, ?)), 0)), 0) / 60 AS hours,
        COALESCE(SUM(b.status = 'booked' AND b.extras <> ''), 0) AS with_extras
    FROM rm_rooms r LEFT JOIN rm_bookings b ON b.room_id = r.id AND b.start_at < ? AND b.end_at > ?
    GROUP BY r.id ORDER BY n DESC, r.name");
$st->bind_param('ssss', $from, $to, $to, $from); $st->execute();
$rooms = array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['name'], 'building' => $r['building'], 'seats' => (int)$r['seats'], 'n' => (int)$r['n'], 'cancelled' => (int)$r['cancelled'],
    'hours' => round((float)$r['hours'], 1), 'with_extras' => (int)$r['with_extras'], 'util' => $wd ? min(100, round((float)$r['hours'] / ($wd * 9) * 100)) : 0], $st->get_result()->fetch_all(MYSQLI_ASSOC));
$st = $conn->prepare("SELECT COALESCE(NULLIF(department, ''), 'ไม่ระบุ') d, COUNT(*) n FROM rm_bookings WHERE status = 'booked' AND start_at < ? AND end_at > ? GROUP BY d ORDER BY n DESC LIMIT 15");
$st->bind_param('ss', $to, $from); $st->execute();
echo json_encode(['status' => 'success', 'month' => $m, 'workdays' => $wd, 'rooms' => $rooms, 'departments' => $st->get_result()->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE);
