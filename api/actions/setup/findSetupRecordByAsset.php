<?php
// api/actions/setup/findSetupRecordByAsset.php
// หา setup_records ล่าสุดของ asset_code นี้
// - ใช้โดย setup.html เพื่อกันสร้างซ้ำ (ดูค่า found_active)
// - ใช้โดย admin.html เพื่อโชว์ badge ว่าเช็คลิสต์แล้วหรือยัง (ดูค่า found + record_status)
//
// Self-heal: เทียบ custodian ปัจจุบันใน it_assets (source of truth เสมอ ไม่เชื่อค่าที่ client
// ส่งมา) กับ employee_id/department ที่บันทึกไว้ใน record — คนละสูตรกันตาม custodian_type:
//   - person:     เทียบ employee_id ตรงกันไหม
//   - department: เทียบชื่อแผนกตรงกันไหม (เผื่อ PC/Notebook ส่วนกลางถูกโอนข้ามแผนก)
// ถ้าไม่ตรง (ทรัพย์สินถูกโอนไปเจ้าของ/แผนกอื่นแล้วแต่ record เก่ายังไม่ถูกปิด) จะ auto-flip
// เป็น offboarded ให้ทันที เงียบๆ ไม่แจ้งเตือนใคร (คนละบริบทกับพนักงานลาออกจริง)
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

// endpoint นี้ query setup_records ด้วย asset_code บ่อยมาก (ทุกครั้งที่เปิด asset popup/setup.html)
// ไม่มี index มาก่อนเลยเป็น full table scan ทุกครั้ง — เพิ่ม index กันช้าลงเรื่อยๆ ตามข้อมูลที่โต
$idxRes = $conn->query("SHOW INDEX FROM setup_records WHERE Key_name = 'idx_asset_code_updated'");
if (!$idxRes || intval($idxRes->num_rows) === 0) {
    $conn->query("ALTER TABLE setup_records ADD INDEX idx_asset_code_updated (asset_code, updated_at)");
}

$assetCode = trim($_GET['asset_code'] ?? $_POST['asset_code'] ?? '');
if ($assetCode === '') { echo json_encode(['status'=>'error','message'=>'ไม่ระบุ asset_code']); exit; }

// custodian ปัจจุบันจริงของทรัพย์สินนี้
$custodianType     = 'person';
$currentEmployeeId = '';
$currentDepartment = '';
$stmtAsset = $conn->prepare("SELECT custodian_employee_id, custodian_type, department FROM it_assets WHERE equipment_id = ? LIMIT 1");
if ($stmtAsset) {
    $stmtAsset->bind_param("s", $assetCode);
    $stmtAsset->execute();
    $assetRow = $stmtAsset->get_result()->fetch_assoc();
    if ($assetRow) {
        $custodianType = ($assetRow['custodian_type'] ?? 'person') === 'department' ? 'department' : 'person';
        if ($custodianType === 'person') {
            $currentEmployeeId = trim((string)($assetRow['custodian_employee_id'] ?? ''));
        } else {
            $currentDepartment = trim((string)($assetRow['department'] ?? ''));
        }
    }
}

$stmt = $conn->prepare("SELECT id, status, employee_id, department FROM setup_records WHERE asset_code = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->bind_param("s", $assetCode);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['status'=>'success','found'=>false,'found_active'=>false]);
    exit;
}

$recordEmployeeId = trim((string)($row['employee_id'] ?? ''));
$recordDepartment = trim((string)($row['department'] ?? ''));

if ($custodianType === 'person') {
    $isSameCustodian = ($currentEmployeeId !== '' && $recordEmployeeId === $currentEmployeeId);
} else {
    // department-owned: record เดิมต้องไม่ผูกกับพนักงานคนใดคนหนึ่ง และชื่อแผนกต้องตรงกัน
    $isSameCustodian = ($recordEmployeeId === '' && $currentDepartment !== '' && strcasecmp($recordDepartment, $currentDepartment) === 0);
}

if ($row['status'] === 'active' && !$isSameCustodian) {
    $autoNote = 'ปิดอัตโนมัติ: ทรัพย์สินถูกโอนไปยังผู้ถือครองอื่นแล้ว';
    $autoBy   = 'ระบบ (auto-close จากการโอนย้ายทรัพย์สิน)';
    $stmtFix = $conn->prepare("UPDATE setup_records SET status='offboarded', offboard_date=CURDATE(), offboard_notes=?, offboard_by=? WHERE id=?");
    $stmtFix->bind_param("ssi", $autoNote, $autoBy, $row['id']);
    $stmtFix->execute();
    $row['status'] = 'offboarded';
}

echo json_encode([
    'status'        => 'success',
    'found'         => true,
    'found_active'  => $row['status'] === 'active',
    'id'            => (int)$row['id'],
    'record_status' => $row['status'],
]);
