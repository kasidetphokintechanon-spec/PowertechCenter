<?php
// api/actions/car/car_import.php — นำเข้ารถ + คนขับ + ใบจองจาก carsys เดิม (ครั้งเดียวตอนเปิดใช้) · เฉพาะ Admin กลาง
// รับ JSON จากสคริปต์ส่งออกที่รันบนเครื่องเดิม · ซ้ำได้ (legacy_id กันนำเข้าซ้ำ)
// สถานะเดิม: 0 = รอ (ส่วนใหญ่ไม่มีใครกด) · 1 = เปิดอ่านแล้ว · 2 = อนุมัติ · 3 = ไม่อนุมัติ · 4 = ยกเลิก — ระบบเดิมไม่เคยบันทึกการคืนรถ
_initCarTables($conn);
if (($_SESSION['role'] ?? '') !== 'admin') throw new Exception('เฉพาะ Admin กลาง');
$j = json_decode((string)($_POST['payload'] ?? ''), true);
if (!is_array($j) || !isset($j['cars'], $j['bookings'])) throw new Exception('ข้อมูลนำเข้าไม่ถูกต้อง');
$t = fn($v, $n = 255) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$v)), 0, $n);

$carMap = [];
foreach ($conn->query("SELECT id, legacy_id FROM cr_cars WHERE legacy_id IS NOT NULL")->fetch_all() as [$id, $lg]) $carMap[(int)$lg] = (int)$id;
$newCars = 0;
$ins = $conn->prepare("INSERT INTO cr_cars (plate, province, name, brand, car_type, seats, color, is_active, sort_order, legacy_id, note) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
foreach ($j['cars'] as $c) {
    $lg = (int)$c['id']; if (isset($carMap[$lg])) continue;
    $plate = $t($c['code'], 30) ?: ('รถ #' . $lg); $prov = $t($c['prov_name'] ?? '', 60); $model = $t($c['model'] ?? '', 150);
    // ชื่อยี่ห้อที่สะกดผิดในระบบเดิม
    $brand = str_ireplace('Volk Swagen', 'Volkswagen', $t($c['brand_name'] ?? '', 60));
    $type = $t($c['cartype_name'] ?? '', 30); $color = $t($c['color'] ?? '', 60);
    $seats = (int)$c['seats']; $act = (int)$c['status'] === 0 ? 1 : 0; $so = $lg; $note = $t($c['comment'] ?? '', 500);
    $ins->bind_param('sssssisiiis', $plate, $prov, $model, $brand, $type, $seats, $color, $act, $so, $lg, $note); $ins->execute();
    $carMap[$lg] = $conn->insert_id; $newCars++;
}
$drvMap = [];
foreach ($conn->query("SELECT id, legacy_id FROM cr_drivers WHERE legacy_id IS NOT NULL")->fetch_all() as [$id, $lg]) $drvMap[(int)$lg] = (int)$id;
$di = $conn->prepare("INSERT INTO cr_drivers (name, nickname, phone, is_active, legacy_id) VALUES (?,?,?,?,?)");
foreach ($j['drivers'] ?? [] as $d) {
    $lg = (int)$d['id']; if (isset($drvMap[$lg])) continue;
    $nm = $t(trim(($d['firstname'] ?? '') . ' ' . ($d['surname'] ?? '')), 150) ?: ('คนขับ #' . $lg); $nk = $t($d['nickname'] ?? '', 60); $ph = $t($d['tel'] ?? '', 50);
    $act = (int)$d['status'] === 0 ? 1 : 0;
    $di->bind_param('sssii', $nm, $nk, $ph, $act, $lg); $di->execute();
    $drvMap[$lg] = $conn->insert_id;
}

$have = array_flip(array_map('intval', array_column($conn->query("SELECT legacy_id FROM cr_bookings WHERE legacy_id IS NOT NULL")->fetch_all(), 0)));
$findEmp = employeeMatcher($conn);
$fill = $conn->prepare("UPDATE cr_bookings SET booker_id = ? WHERE legacy_id = ? AND booker_id IS NULL"); $linked = 0;
$bi = $conn->prepare("INSERT INTO cr_bookings (code, car_id, driver_id, need_driver, purpose, destination, start_at, end_at, passengers, passenger_names, booker_id, booker_name, rider_name, department, contact, note, status,
    decided_at, decided_by, received_at, received_by, cancelled_at, cancel_reason, legacy_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$n = 0; $skip = 0; $now = time();
foreach ($j['bookings'] as $v) {
    $lg = (int)$v['id'];
    if (isset($have[$lg])) {
        if ($bid = $findEmp($v['user_code'] ?? null, $v['user_name'] ?? '', $v['person_name'] ?? '')) { $fill->bind_param('si', $bid, $lg); $fill->execute(); $linked += $fill->affected_rows; }
        $skip++; continue;
    }
    if (!isset($carMap[(int)$v['car_id']]) || empty($v['start_date']) || empty($v['end_date'])) { $skip++; continue; }
    $code = 'CS-' . ($t($v['docno'], 16) ?: $lg);
    $car = $carMap[(int)$v['car_id']]; $drv = $drvMap[(int)$v['driver_id']] ?? null; $need = $drv ? 1 : 0;
    $purpose = $t($v['working'] ?? '') ?: 'ใช้รถ';
    $dest = $t(implode(' ', array_unique(array_filter([$t($v['address'] ?? ''), $t($v['province'] ?? '')]))));
    $s = (string)$v['start_date']; $e = (string)$v['end_date']; $pax = (int)$v['passenger_amount'];
    $names = $t($v['passenger_name'] ?? '', 1000);
    $bid = $findEmp($v['user_code'] ?? null, $v['user_name'] ?? '', $v['person_name'] ?? '');
    // ระบบเดิม: user_save = คนกดจอง · person_name = คนใช้รถ (ถ้าเป็นคนอื่น = จองให้)
    $bn = $t($v['user_name'] ?: $v['person_name']) ?: '-'; $rider = $t($v['person_name'] ?? '');
    $nm = fn($x) => mb_strtolower(preg_replace('/[\s.]+/u', '', preg_replace('/^\s*(mr|mrs|ms|miss|dr|นางสาว|นาย|นาง|คุณ)\.?\s*/iu', '', $x)));
    // ชื่อเดียวกัน หรือพิมพ์ชื่อตัวเองซ้ำสองรอบ (มีในข้อมูลเดิม) = ใช้รถเอง
    if ($rider !== '' && ($nm($rider) === $nm($bn) || ($nm($bn) !== '' && mb_strpos($nm($rider), $nm($bn)) !== false))) $rider = '';
    $rider = $rider ?: null; $dp = $t($v['depart_name'] ?? '', 150); $tel = $t($v['tel'] ?? '', 100); $note = $t($v['comment'] ?? '', 500);
    $old = (int)$v['status']; $ended = strtotime($e) < $now;
    $dAt = $v['approve_date'] ?: null; $dBy = $dAt ? 'ระบบเดิม (carsys)' : null; $rAt = null; $rBy = null; $cAt = null; $cr = null;
    if ($old === 2) { $st = $ended ? 'returned' : 'approved'; if ($ended) { $rAt = $e; $rBy = 'ระบบเดิม (ไม่ได้บันทึกการคืน)'; } }
    elseif ($old === 3) { $st = 'rejected'; $cr = $t($v['approve_comment'] ?? '', 500) ?: 'ไม่อนุมัติในระบบเดิม'; }
    elseif ($old === 4) { $st = 'cancelled'; $cAt = $dAt ?: $s; $cr = $t($v['approve_comment'] ?? '', 500) ?: 'ยกเลิกในระบบเดิม'; }
    elseif ($ended) { $st = 'cancelled'; $cAt = $e; $cr = 'ไม่ได้อนุมัติในระบบเดิม'; }   // รอค้างจนเลยเวลา
    else $st = 'pending';
    $ca = (string)($v['date_create'] ?: $s);
    $bi->bind_param('siiissssissssssssssssssis', $code, $car, $drv, $need, $purpose, $dest, $s, $e, $pax, $names, $bid, $bn, $rider, $dp, $tel, $note, $st, $dAt, $dBy, $rAt, $rBy, $cAt, $cr, $lg, $ca);
    try { $bi->execute(); $n++; } catch (Throwable $x) { $skip++; }
}
echo json_encode(['status' => 'success', 'cars_added' => $newCars, 'drivers' => count($drvMap), 'bookings_added' => $n, 'owners_linked' => $linked, 'skipped' => $skip]);
