<?php
// api/actions/car/car_set_driver.php — เปลี่ยน/เลือกคนขับหลังอนุมัติแล้ว (คนเดิมลา/ติดงานด่วน) ไม่ต้องยกเลิกใบให้คนจองจองใหม่ · สิทธิ์ car.manage
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || !in_array($b['status'], ['approved', 'returning'], true)) throw new Exception('เปลี่ยนคนขับได้เฉพาะใบที่อนุมัติแล้วและยังไม่ปิด');
if (!carKeeps($conn, $b['car_company'])) throw new Exception('ไม่ได้ดูแลรถคันนี้');
$driver = (int)($_POST['driver_id'] ?? 0) ?: null;
if ($driver) {
    $d = $conn->prepare("SELECT id FROM cr_drivers WHERE id = ? AND is_active = 1"); $d->bind_param('i', $driver); $d->execute();
    if (!$d->get_result()->fetch_row()) throw new Exception('ไม่พบคนขับ');
    if ($x = carDriverClash($conn, $driver, $b['start_at'], $b['end_at'], $b['id'])) throw new Exception("คนขับติดงาน {$x['code']} ({$x['plate']}) " . date('d/m H:i', strtotime($x['start_at'])) . '–' . date('H:i', strtotime($x['end_at'])));
}
// ใบที่ขอคนขับ เอาคนขับออกได้ (กลับเป็น "ยังไม่ได้เลือก") — ขึ้นเตือนในงานคนขับให้จัดใหม่
$u = $conn->prepare("UPDATE cr_bookings SET driver_id = ? WHERE id = ? AND status IN ('approved','returning')");
$u->bind_param('ii', $driver, $b['id']); $u->execute();
if ((int)$driver !== (int)$b['driver_id']) carNotifyDriverJob($conn, carGet($conn, $b['id']), $b['driver_name'] ?: null);
echo json_encode(['status' => 'success']);
