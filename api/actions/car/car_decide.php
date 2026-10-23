<?php
// api/actions/car/car_decide.php — อนุมัติ (+เลือกคนขับ) / ไม่อนุมัติ · ผู้ดูแลรถของบริษัทเจ้าของรถ · สิทธิ์ car.manage
_initCarTables($conn);
$b = carGet($conn, (int)($_POST['id'] ?? 0));
if (!$b || $b['status'] !== 'pending') throw new Exception('ไม่มีใบจองที่รออนุมัติ');
if (!carKeeps($conn, $b['car_company'])) throw new Exception('ไม่ได้ดูแลรถคันนี้');
$ok = ($_POST['decision'] ?? '') === 'approve';
$by = carActor();
if ($ok) {
    $driver = (int)($_POST['driver_id'] ?? 0) ?: null;
    if ($driver) {
        $d = $conn->prepare("SELECT id FROM cr_drivers WHERE id = ? AND is_active = 1"); $d->bind_param('i', $driver); $d->execute();
        if (!$d->get_result()->fetch_row()) throw new Exception('ไม่พบคนขับ');
        if ($x = carDriverClash($conn, $driver, $b['start_at'], $b['end_at'], $b['id'])) throw new Exception("คนขับติดงาน {$x['code']} ({$x['plate']}) " . date('d/m H:i', strtotime($x['start_at'])) . '–' . date('H:i', strtotime($x['end_at'])));
    }
    $u = $conn->prepare("UPDATE cr_bookings SET status = 'approved', driver_id = ?, decided_at = NOW(), decided_by = ? WHERE id = ? AND status = 'pending'");
    $u->bind_param('isi', $driver, $by, $b['id']);
} else {
    $reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500);
    if ($reason === '') throw new Exception('ใส่เหตุผลที่ไม่อนุมัติ (ผู้จองจะเห็น)');
    $u = $conn->prepare("UPDATE cr_bookings SET status = 'rejected', decided_at = NOW(), decided_by = ?, cancel_reason = ? WHERE id = ? AND status = 'pending'");
    $u->bind_param('ssi', $by, $reason, $b['id']);
}
$u->execute();
if ($u->affected_rows < 1) throw new Exception('ใบจองนี้ถูกเปลี่ยนไปแล้ว');
echo json_encode(['status' => 'success']);
