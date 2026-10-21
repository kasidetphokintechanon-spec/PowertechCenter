<?php
// api/actions/setup/saveSetupRecord.php
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

require_once __DIR__ . '/../../../includes/setup_asset_sync.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS setup_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(200) DEFAULT '',
    employee_id VARCHAR(50) DEFAULT '',
    department VARCHAR(100) DEFAULT '',
    company VARCHAR(20) DEFAULT '',
    asset_code VARCHAR(50) DEFAULT '',
    serial_number VARCHAR(100) DEFAULT '',
    pc_name VARCHAR(100) DEFAULT '',
    tech_name VARCHAR(100) DEFAULT '',
    job_type VARCHAR(50) DEFAULT '',
    setup_date DATE DEFAULT NULL,
    ip_address VARCHAR(50) DEFAULT '',
    gateway VARCHAR(50) DEFAULT '',
    company_access JSON DEFAULT NULL,
    printer_buildings JSON DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    net_password VARCHAR(100) DEFAULT '',
    checklist_states JSON DEFAULT NULL,
    custom_items JSON DEFAULT NULL,
    granted_systems JSON DEFAULT NULL,
    status ENUM('active','offboarded') DEFAULT 'active',
    offboard_date DATE DEFAULT NULL,
    offboard_notes TEXT DEFAULT NULL,
    offboard_checklist JSON DEFAULT NULL,
    offboard_by VARCHAR(100) DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Add columns if table already exists without them
$conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS company_access JSON DEFAULT NULL");
$conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS printer_buildings JSON DEFAULT NULL");
$conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS net_password VARCHAR(100) DEFAULT '' AFTER gateway");
$conn->query("ALTER TABLE setup_records ADD COLUMN IF NOT EXISTS granted_systems JSON DEFAULT NULL");

$id             = intval($_POST['id'] ?? 0);
$employee_name  = trim($_POST['employee_name'] ?? '');
$employee_id    = trim($_POST['employee_id'] ?? '');
$department     = trim($_POST['department'] ?? '');
$company        = trim($_POST['company'] ?? '');
$asset_code     = trim($_POST['asset_code'] ?? '');
$serial_number  = trim($_POST['serial_number'] ?? '');
$pc_name        = trim($_POST['pc_name'] ?? '');
$tech_name      = trim($_POST['tech_name'] ?? $_SESSION['display_name'] ?? $_SESSION['username'] ?? '');
$job_type       = trim($_POST['job_type'] ?? '');
$setup_date     = trim($_POST['setup_date'] ?? date('Y-m-d'));
$ip_address     = trim($_POST['ip_address'] ?? '');
$gateway        = trim($_POST['gateway'] ?? '');
$net_password   = ''; // TISAX: ไม่เก็บรหัสผ่านผู้ใช้เป็นข้อความธรรมดา (ช่องนี้เลิกใช้แล้ว)
$company_access    = $_POST['company_access'] ?? '[]';
$printer_buildings = $_POST['printer_buildings'] ?? '[]';
$notes             = trim($_POST['notes'] ?? '');
$checklist_states = $_POST['checklist_states'] ?? '{}';
$custom_items   = $_POST['custom_items'] ?? '[]';
$granted_systems = $_POST['granted_systems'] ?? '{}';

// Validate JSON
if (!json_decode($checklist_states))  $checklist_states  = '{}';
if (!json_decode($custom_items))      $custom_items      = '[]';
if (!json_decode($company_access))    $company_access    = '[]';
if (!json_decode($printer_buildings)) $printer_buildings = '[]';
if (!json_decode($granted_systems))   $granted_systems   = '{}';

$setup_date_val = $setup_date ?: null;

if ($id > 0) {
    // UPDATE
    $stmt = $conn->prepare("UPDATE setup_records SET
        employee_name=?, employee_id=?, department=?, company=?,
        asset_code=?, serial_number=?, pc_name=?, tech_name=?,
        job_type=?, setup_date=?, ip_address=?, gateway=?, net_password=?,
        company_access=?, printer_buildings=?, notes=?, checklist_states=?, custom_items=?, granted_systems=?
        WHERE id=?");
    $stmt->bind_param("sssssssssssssssssssi",
        $employee_name, $employee_id, $department, $company,
        $asset_code, $serial_number, $pc_name, $tech_name,
        $job_type, $setup_date_val, $ip_address, $gateway, $net_password,
        $company_access, $printer_buildings, $notes, $checklist_states, $custom_items, $granted_systems, $id);
    $stmt->execute();
    $registrySync = syncSetupToItAsset($conn, [
        'setup_record_id' => $id,
        'asset_code' => $asset_code,
        'serial_number' => $serial_number,
        'pc_name' => $pc_name,
        'ip_address' => $ip_address,
        'employee_name' => $employee_name,
        'employee_id' => $employee_id,
        'department' => $department,
        'company' => $company,
        'job_type' => $job_type,
    ]);
    echo json_encode(['status'=>'success','id'=>$id,'message'=>'อัปเดตแล้ว','registry_sync'=>$registrySync]);
} else {
    // INSERT
    $created_by = $_SESSION['username'] ?? '';
    $stmt = $conn->prepare("INSERT INTO setup_records
        (employee_name, employee_id, department, company,
         asset_code, serial_number, pc_name, tech_name,
         job_type, setup_date, ip_address, gateway, net_password,
         company_access, printer_buildings, notes, checklist_states, custom_items, granted_systems, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssssssssssssssss",
        $employee_name, $employee_id, $department, $company,
        $asset_code, $serial_number, $pc_name, $tech_name,
        $job_type, $setup_date_val, $ip_address, $gateway, $net_password,
        $company_access, $printer_buildings, $notes, $checklist_states, $custom_items, $granted_systems, $created_by);
    $stmt->execute();
    $newId = (int)$conn->insert_id;
    $registrySync = syncSetupToItAsset($conn, [
        'setup_record_id' => $newId,
        'asset_code' => $asset_code,
        'serial_number' => $serial_number,
        'pc_name' => $pc_name,
        'ip_address' => $ip_address,
        'employee_name' => $employee_name,
        'employee_id' => $employee_id,
        'department' => $department,
        'company' => $company,
        'job_type' => $job_type,
    ]);
    echo json_encode(['status'=>'success','id'=>$newId,'message'=>'บันทึกแล้ว','registry_sync'=>$registrySync]);
}
