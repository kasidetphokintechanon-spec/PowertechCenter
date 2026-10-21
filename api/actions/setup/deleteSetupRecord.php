<?php
// api/actions/setup/deleteSetupRecord.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

// TISAX: ใบติดตั้งคือหลักฐานว่าใครได้เครื่อง/สิทธิ์อะไร — ลบได้เฉพาะ admin พร้อมเหตุผล และเก็บร่องรอยไว้
if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['status'=>'error','message'=>'ลบใบติดตั้งได้เฉพาะผู้ดูแลระบบ']); exit; }

$id = intval($_POST['id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));
if (!$id) { echo json_encode(['status'=>'error','message'=>'ไม่ระบุ ID']); exit; }
if (mb_strlen($reason) < 3) { echo json_encode(['status'=>'error','message'=>'กรุณาระบุเหตุผลที่ลบ']); exit; }

$old = $conn->prepare("SELECT employee_name, employee_id, asset_code, it_asset_id, setup_date FROM setup_records WHERE id=? LIMIT 1");
$old->bind_param("i", $id);
$old->execute();
$oldRow = $old->get_result()->fetch_assoc();

// ป้องกันลบ record ที่ offboard แล้ว (มีเอกสารจริง) — ลบได้เฉพาะ active หรือ draft
// ถ้าต้องการลบ offboarded ด้วย ให้ comment บรรทัดนี้ออก
// $stmt = $conn->prepare("DELETE FROM setup_records WHERE id=? AND status='active'");

$stmt = $conn->prepare("DELETE FROM setup_records WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    if ($oldRow) {
        $assetDbId = (int)($oldRow['it_asset_id'] ?? 0);
        if (!$assetDbId && $oldRow['asset_code'] !== '') {
            $a = $conn->prepare("SELECT id FROM it_assets WHERE equipment_id = ? LIMIT 1");
            $a->bind_param("s", $oldRow['asset_code']);
            $a->execute();
            $assetDbId = (int)($a->get_result()->fetch_assoc()['id'] ?? 0);
        }
        logAssetHistory($conn, $assetDbId, 'SETUP_DELETE', 'ลบใบติดตั้ง #' . $id . ' ของ ' . $oldRow['employee_name'] . ' (' . $oldRow['employee_id'] . ', ' . $oldRow['setup_date'] . ') · เหตุผล: ' . $reason);
    }
    echo json_encode(['status'=>'success','message'=>'ลบแล้ว']);
} else {
    echo json_encode(['status'=>'error','message'=>'ไม่พบ Record หรือลบไม่ได้']);
}
