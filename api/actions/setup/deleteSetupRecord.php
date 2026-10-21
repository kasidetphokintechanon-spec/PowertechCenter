<?php
// api/actions/setup/deleteSetupRecord.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$id = intval($_POST['id'] ?? 0);
if (!$id) { echo json_encode(['status'=>'error','message'=>'ไม่ระบุ ID']); exit; }

// ป้องกันลบ record ที่ offboard แล้ว (มีเอกสารจริง) — ลบได้เฉพาะ active หรือ draft
// ถ้าต้องการลบ offboarded ด้วย ให้ comment บรรทัดนี้ออก
// $stmt = $conn->prepare("DELETE FROM setup_records WHERE id=? AND status='active'");

$stmt = $conn->prepare("DELETE FROM setup_records WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status'=>'success','message'=>'ลบแล้ว']);
} else {
    echo json_encode(['status'=>'error','message'=>'ไม่พบ Record หรือลบไม่ได้']);
}
