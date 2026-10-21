<?php
// api/actions/setup/offboardSetup.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
// บันทึกเช็กลิสต์นี้ไปปิดงานใน Task Center + เปลี่ยนป้ายฝั่ง HR ด้วย — ให้เฉพาะทีม IT
requirePermission($conn, 'it_log.access');

$id                 = intval($_POST['id'] ?? 0);
$offboard_checklist = $_POST['offboard_checklist'] ?? '[]';
$offboard_notes     = trim($_POST['offboard_notes'] ?? '');
$offboard_date      = trim($_POST['offboard_date'] ?? date('Y-m-d'));
$offboard_by        = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';

if (!$id) { echo json_encode(['status'=>'error','message'=>'ไม่ระบุ ID']); exit; }
if (!json_decode($offboard_checklist)) $offboard_checklist = '[]';

$stmt = $conn->prepare("UPDATE setup_records SET
    status='offboarded',
    offboard_date=?,
    offboard_notes=?,
    offboard_checklist=?,
    offboard_by=?
    WHERE id=?");
$stmt->bind_param("ssssi", $offboard_date, $offboard_notes, $offboard_checklist, $offboard_by, $id);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    // อัปเดตงาน offboard ใน Task Center ของพนักงานคนนี้ให้ตรงกับเช็กลิสต์ (ครบ = ปิดงาน → ป้ายฝั่ง HR เป็นสีเขียว)
    require_once __DIR__ . '/../../../includes/offboard_task_helper.php';
    $stmtEmp = $conn->prepare("SELECT employee_id FROM setup_records WHERE id = ? LIMIT 1");
    $stmtEmp->bind_param("i", $id);
    $stmtEmp->execute();
    $empRow = $stmtEmp->get_result()->fetch_assoc();
    try {
        syncOffboardTaskFromChecklist($conn, (string)($empRow['employee_id'] ?? ''));
    } catch (Throwable $e) {
        error_log('[Offboard] sync task failed: ' . $e->getMessage());
    }

    echo json_encode(['status'=>'success','message'=>'Offboard บันทึกแล้ว']);
} else {
    echo json_encode(['status'=>'error','message'=>'ไม่พบ record']);
}
