<?php
// api/actions/setup/getSetupRecord.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { echo json_encode(['status'=>'error','message'=>'ไม่ระบุ ID']); exit; }

$stmt = $conn->prepare("SELECT * FROM setup_records WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) { echo json_encode(['status'=>'error','message'=>'ไม่พบข้อมูล']); exit; }

// Decode JSON fields
$row['checklist_states']   = json_decode($row['checklist_states']   ?? '{}', true) ?: [];
$row['custom_items']       = json_decode($row['custom_items']       ?? '[]', true) ?: [];
$row['company_access']     = json_decode($row['company_access']     ?? '[]', true) ?: [];
$row['printer_buildings']  = json_decode($row['printer_buildings']  ?? '[]', true) ?: [];
$row['offboard_checklist'] = json_decode($row['offboard_checklist'] ?? '[]', true) ?: [];
$row['granted_systems']    = json_decode($row['granted_systems']    ?? '{}', true) ?: [];
unset($row['net_password']); // TISAX: ไม่ส่งรหัสผ่านออกจาก server (เลิกเก็บแล้ว แต่ใบเก่าอาจยังมีค้าง)

echo json_encode(['status'=>'success','record'=>$row]);
