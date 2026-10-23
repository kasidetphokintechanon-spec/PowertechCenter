<?php
// api/actions/car/car_report.php — การใช้รถรายเดือน / รายปี (year=YYYY): ต่อคัน (ครั้ง ชั่วโมง อัตราการใช้ในเวลาทำการ 08–17) · ตามแผนก · ตามคนขับ · ไปที่ไหนบ่อย · อนุมัติเร็วแค่ไหน
// สิทธิ์ car.manage · นับเฉพาะรถของบริษัทที่ดูแล · ใบที่ใช้จริง = อนุมัติแล้ว / รอรับกุญแจ / คืนแล้ว
_initCarTables($conn);
$year = preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? (string)$_GET['year'] : '';
$m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
if ($year) { $from = "$year-01-01 00:00:00"; $to = ($year + 1) . '-01-01 00:00:00'; }
else { $from = "$m-01 00:00:00"; $to = date('Y-m-d 00:00:00', strtotime("$m-01 +1 month")); }
$wd = 0; for ($d = strtotime(substr($from, 0, 10)); $d < strtotime($to); $d += 86400) if (date('N', $d) < 6) $wd++;
[$w, $p] = carScopeSql($conn);
$USED = "b.status IN ('approved','returning','returned')";
// ชั่วโมงนับเฉพาะส่วนที่อยู่ในเดือนนี้ (ใบข้ามเดือนไม่นับซ้ำ)
$HRS = "TIMESTAMPDIFF(MINUTE, GREATEST(b.start_at, ?), LEAST(b.end_at, ?)) / 60";
$q = function ($sql, $types, $args) use ($conn) {
    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$args);
    $st->execute(); return $st->get_result()->fetch_all(MYSQLI_ASSOC);
};
$pt = str_repeat('s', count($p));
$cars = $q("SELECT c.id, c.plate, c.name, c.brand, c.car_type, c.company,
        COALESCE(SUM($USED), 0) AS trips, COALESCE(SUM(IF($USED, $HRS, 0)), 0) AS hours,
        COALESCE(SUM($USED AND b.driver_id IS NOT NULL), 0) AS with_driver, COALESCE(SUM(b.status IN ('cancelled','rejected')), 0) AS dropped
    FROM cr_cars c LEFT JOIN cr_bookings b ON b.car_id = c.id AND b.start_at < ? AND b.end_at > ?
    WHERE (c.is_active = 1 OR b.id IS NOT NULL){$w} GROUP BY c.id ORDER BY trips DESC, c.plate", 'ssss' . $pt, array_merge([$from, $to, $to, $from], $p));
$cars = array_map(fn($r) => ['plate' => $r['plate'], 'name' => $r['brand'] && stripos((string)$r['name'], (string)$r['brand']) !== false ? $r['name'] : trim($r['brand'] . ' ' . $r['name']), 'type' => $r['car_type'], 'company' => $r['company'], 'trips' => (int)$r['trips'],
    'hours' => round((float)$r['hours'], 1), 'with_driver' => (int)$r['with_driver'], 'dropped' => (int)$r['dropped'], 'util' => $wd ? min(100, round((float)$r['hours'] / ($wd * 9) * 100)) : 0], $cars);
$base = "FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id WHERE $USED AND b.start_at < ? AND b.end_at > ?{$w}";
$args = array_merge([$to, $from], $p); $t = 'ss' . $pt;
$depts = $q("SELECT COALESCE(NULLIF(b.department, ''), 'ไม่ระบุ') d, COUNT(*) n, ROUND(SUM(TIMESTAMPDIFF(MINUTE, b.start_at, b.end_at)) / 60, 1) h $base GROUP BY d ORDER BY n DESC LIMIT 15", $t, $args);
$dests = $q("SELECT b.destination d, COUNT(*) n $base AND b.destination <> '' GROUP BY b.destination ORDER BY n DESC LIMIT 10", $t, $args);
$drivers = $q("SELECT d.name, COUNT(*) n, ROUND(SUM(TIMESTAMPDIFF(MINUTE, b.start_at, b.end_at)) / 60, 1) h FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id JOIN cr_drivers d ON d.id = b.driver_id
    WHERE $USED AND b.start_at < ? AND b.end_at > ?{$w} GROUP BY d.id ORDER BY n DESC", $t, $args);
// อนุมัติเร็วแค่ไหน (นาที จากส่งคำขอถึงตัดสิน) — ระบบเดิมช้าเกิน 1 วันถึง 1 ใน 3 ใช้ติดตามว่าดีขึ้นไหม
$sp = $q("SELECT COUNT(*) n, ROUND(AVG(TIMESTAMPDIFF(MINUTE, b.created_at, b.decided_at))) avg_min, SUM(TIMESTAMPDIFF(MINUTE, b.created_at, b.decided_at) <= 60) in_1h
    FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id WHERE b.decided_at IS NOT NULL AND b.legacy_id IS NULL AND b.start_at < ? AND b.end_at > ?{$w}", $t, $args)[0];
echo json_encode(['status' => 'success', 'month' => $m, 'year' => $year, 'workdays' => $wd, 'cars' => $cars, 'departments' => $depts, 'destinations' => $dests, 'drivers' => $drivers,
    'approve' => ['n' => (int)$sp['n'], 'avg_min' => $sp['avg_min'] !== null ? (int)$sp['avg_min'] : null, 'in_1h' => (int)$sp['in_1h']]], JSON_UNESCAPED_UNICODE);
