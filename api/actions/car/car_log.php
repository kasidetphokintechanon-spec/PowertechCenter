<?php
// api/actions/car/car_log.php — ทะเบียนการใช้รถ (ทุกใบในช่วงเดือน/ปี รวมที่ยกเลิก/ไม่อนุมัติพร้อมเหตุผล) สำหรับ audit · สิทธิ์ car.manage
// ใครใช้คันไหน วันไหน ใครอนุมัติ ใครรับกุญแจคืน · format=csv = ไฟล์เปิดใน Excel (บันทึก audit_log ทุกครั้งที่ส่งออก — มีข้อมูลบุคคล)
_initCarTables($conn);
if (preg_match('/^\d{4}$/', (string)($_GET['year'] ?? ''))) { $from = $_GET['year'] . '-01-01 00:00:00'; $to = ($_GET['year'] + 1) . '-01-01 00:00:00'; $label = $_GET['year']; }
else { $m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m'); $from = "$m-01 00:00:00"; $to = date('Y-m-d 00:00:00', strtotime("$m-01 +1 month")); $label = $m; }
[$w, $p] = carScopeSql($conn);
$st = $conn->prepare("SELECT b.code, c.plate, TRIM(CONCAT(COALESCE(c.brand, ''), ' ', COALESCE(c.name, ''))) AS car, c.company AS car_company, b.start_at, b.end_at, b.purpose, b.destination,
        b.booker_name, b.rider_name, b.department, b.passengers, b.passenger_names, IF(b.need_driver, COALESCE(d.name, 'ขอคนขับ (ไม่ได้เลือก)'), 'ขับเอง') AS driver,
        b.status, b.created_at, b.decided_by, b.decided_at, b.returned_at, b.return_note, b.received_by, b.received_at, b.cancelled_by, b.cancel_reason
    FROM cr_bookings b JOIN cr_cars c ON c.id = b.car_id LEFT JOIN cr_drivers d ON d.id = b.driver_id
    WHERE b.start_at >= ? AND b.start_at < ?{$w} ORDER BY b.start_at, c.plate");
$args = array_merge([$from, $to], $p);
$st->bind_param(str_repeat('s', count($args)), ...$args); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$TH = ['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติ', 'returning' => 'รอรับกุญแจ', 'returned' => 'คืนแล้ว', 'rejected' => 'ไม่อนุมัติ', 'cancelled' => 'ยกเลิก'];
if (($_GET['format'] ?? '') === 'csv') {
    $me = (string)($_SESSION['user_id'] ?? ''); $by = carActor(); $ip = $_SERVER['REMOTE_ADDR'] ?? ''; $det = "ส่งออกทะเบียนการใช้รถ {$label} (" . count($rows) . ' ใบ)';
    $a = $conn->prepare("INSERT INTO audit_log (user_id, user_name, action, target_id, details, ip_address) VALUES (?, ?, 'car_log_export', ?, ?, ?)");
    if ($a) { $a->bind_param('sssss', $me, $by, $label, $det, $ip); $a->execute(); }
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=car_log_' . $label . '.csv');
    $o = fopen('php://output', 'w');
    fputs($o, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยถูก
    fputcsv($o, ['เลขที่', 'ทะเบียน', 'รถ', 'บริษัทรถ', 'ออก', 'คืน', 'ไปทำอะไร', 'ไปที่', 'ผู้จอง', 'ผู้ใช้รถ', 'แผนก', 'จำนวนคน', 'ผู้ร่วมเดินทาง', 'คนขับ', 'สถานะ',
        'ส่งคำขอ', 'ผู้อนุมัติ/ไม่อนุมัติ', 'เวลาอนุมัติ', 'กดคืนรถ', 'หมายเหตุคืนรถ', 'ผู้รับกุญแจ', 'เวลารับกุญแจ', 'ผู้ยกเลิก', 'เหตุผล']);
    foreach ($rows as $r) fputcsv($o, [$r['code'], $r['plate'], $r['car'], $r['car_company'], $r['start_at'], $r['end_at'], $r['purpose'], $r['destination'], $r['booker_name'], $r['rider_name'], $r['department'],
        $r['passengers'], $r['passenger_names'], $r['driver'], $TH[$r['status']] ?? $r['status'], $r['created_at'], $r['decided_by'], $r['decided_at'], $r['returned_at'], $r['return_note'], $r['received_by'], $r['received_at'], $r['cancelled_by'], $r['cancel_reason']]);
    fclose($o);
    exit;
}
foreach ($rows as &$r) $r['status_th'] = $TH[$r['status']] ?? $r['status'];
unset($r);
echo json_encode(['status' => 'success', 'label' => $label, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
