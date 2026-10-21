<?php
// api/actions/setup/getSetupRecords.php — list all records (admin/IT only)
if (!isset($conn)) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

// Auto-create table if not exists (reuse same DDL)
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
    net_user VARCHAR(100) DEFAULT '',
    vpn_user VARCHAR(100) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    gateway VARCHAR(50) DEFAULT '',
    vpn_companies VARCHAR(200) DEFAULT '',
    notes TEXT DEFAULT NULL,
    checklist_states JSON DEFAULT NULL,
    custom_items JSON DEFAULT NULL,
    status ENUM('active','offboarded') DEFAULT 'active',
    offboard_date DATE DEFAULT NULL,
    offboard_notes TEXT DEFAULT NULL,
    offboard_checklist JSON DEFAULT NULL,
    offboard_by VARCHAR(100) DEFAULT NULL,
    created_by VARCHAR(100) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$status  = $_GET['status'] ?? '';   // 'active' | 'offboarded' | ''
$company = $_GET['company'] ?? '';
$search  = trim($_GET['search'] ?? '');

$where = ['1=1'];
$params = [];
$types  = '';

if ($status) { $where[] = 'status=?'; $params[] = $status; $types .= 's'; }
if ($company) { $where[] = 'company=?'; $params[] = $company; $types .= 's'; }
if ($search) {
    $like = '%'.$search.'%';
    $where[] = '(employee_name LIKE ? OR asset_code LIKE ? OR pc_name LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

$sql = "SELECT id, employee_name, employee_id, department, company, asset_code, pc_name,
               tech_name, setup_date, status, offboard_date, offboard_notes, job_type, created_at,
               it_asset_id, checklist_states, custom_items
        FROM setup_records WHERE ".implode(' AND ',$where)." ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ความคืบหน้าเช็กลิสต์ (ทำแล้ว/ข้าม นับว่าจัดการแล้ว) — 25 = จำนวนข้อมาตรฐานใน CHECKS ของ setup.html
foreach ($rows as &$r) {
    $states = json_decode($r['checklist_states'] ?? '{}', true) ?: [];
    $custom = json_decode($r['custom_items'] ?? '[]', true) ?: [];
    $done = count(array_filter($states, fn($v) => in_array($v, ['done', 'skipped'], true)));
    foreach ($custom as $c) if (in_array($c['state'] ?? '', ['done', 'skipped'], true)) $done++;
    $r['progress_done']  = $done;
    $r['progress_total'] = 25 + count($custom);
    unset($r['checklist_states'], $r['custom_items']);
}
unset($r);

echo json_encode(['status'=>'success','records'=>$rows,'total'=>count($rows)]);
