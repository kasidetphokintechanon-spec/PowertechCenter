<?php
// api/actions/setup/offboardSetup.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

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
    echo json_encode(['status'=>'success','message'=>'Offboard บันทึกแล้ว']);
} else {
    echo json_encode(['status'=>'error','message'=>'ไม่พบ record']);
}
