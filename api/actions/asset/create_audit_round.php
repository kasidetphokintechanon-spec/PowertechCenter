<?php
// api/actions/asset/create_audit_round.php
require_once __DIR__ . '/../../..' . '/includes/notify_helper.php';

$name     = trim($_POST['name']     ?? '');
$deadline = trim($_POST['deadline'] ?? '');
$userId   = $_SESSION['user_id']    ?? '';
$role     = strtolower($_SESSION['role'] ?? '');

if (!in_array($role, ['admin','it','technician','manager'])) {
    echo json_encode(['status'=>'error','message'=>'ไม่มีสิทธิ์']); exit;
}
if ($name === '') {
    echo json_encode(['status'=>'error','message'=>'กรุณากรอกชื่อรอบตรวจนับ']); exit;
}

// Ensure tables exist (same DDL as get_audit_confirm.php)
$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL,
    deadline DATE DEFAULT NULL, status ENUM('active','closed') DEFAULT 'active',
    created_by VARCHAR(100) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY, round_id INT NOT NULL,
    employee_id VARCHAR(100) NOT NULL, employee_name VARCHAR(200) NOT NULL,
    token VARCHAR(64) NOT NULL, confirmed_at DATETIME DEFAULT NULL,
    status ENUM('pending','confirmed','partial') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token), INDEX idx_round (round_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_items (
    id INT AUTO_INCREMENT PRIMARY KEY, token_id INT NOT NULL,
    asset_db_id INT NOT NULL, equipment_id VARCHAR(100) DEFAULT NULL,
    asset_name VARCHAR(200) DEFAULT NULL,
    status ENUM('pending','confirmed','missing','transferred') DEFAULT 'pending',
    note TEXT DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL,
    INDEX idx_token (token_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create round
$deadlineVal = ($deadline !== '') ? $deadline : null;
$rStmt = $conn->prepare("INSERT INTO asset_audit_rounds (name, deadline, created_by) VALUES (?,?,?)");
$rStmt->bind_param('sss', $name, $deadlineVal, $userId);
if (!$rStmt->execute()) {
    echo json_encode(['status'=>'error','message'=>'สร้างรอบไม่สำเร็จ: '.$conn->error]); exit;
}
$roundId = $conn->insert_id;

// Find all employees who have assets assigned (borrower_id = employees.id)
$empRes = $conn->query(
    "SELECT DISTINCT a.borrower_id AS emp_id, e.name AS emp_name
     FROM it_assets a
     JOIN employees e ON e.id = a.borrower_id
     WHERE a.borrower_id != ''
       AND a.status NOT IN ('ไม่ใช้งาน','จำหน่ายแล้ว','สูญหาย')
       AND e.employment_status = 'active'"
);

$tokInsert  = $conn->prepare("INSERT INTO asset_audit_tokens (round_id, employee_id, employee_name, token) VALUES (?,?,?,?)");
$itemInsert = $conn->prepare("INSERT INTO asset_audit_items (token_id, asset_db_id, equipment_id, asset_name) VALUES (?,?,?,?)");
$assetFetch = $conn->prepare(
    "SELECT id, equipment_id, name FROM it_assets
     WHERE borrower_id = ? AND status NOT IN ('ไม่ใช้งาน','จำหน่ายแล้ว','สูญหาย')"
);

$baseUrl    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';

$sentCount    = 0;
$skippedCount = 0;

while ($emp = $empRes->fetch_assoc()) {
    $empId   = $emp['emp_id'];
    $empName = $emp['emp_name'];
    $token   = bin2hex(random_bytes(24)); // 48-char hex token

    $tokInsert->bind_param('isss', $roundId, $empId, $empName, $token);
    if (!$tokInsert->execute()) { $skippedCount++; continue; }
    $tokenId = $conn->insert_id;

    // Insert audit items for this employee's assets
    $assetFetch->bind_param('s', $empId);
    $assetFetch->execute();
    $assets = $assetFetch->get_result();
    while ($a = $assets->fetch_assoc()) {
        $itemInsert->bind_param('iiss', $tokenId, $a['id'], $a['equipment_id'], $a['name']);
        $itemInsert->execute();
    }
    $sentCount++;
}

// Determine company for LINE broadcast
$company = $_SESSION['company'] ?? null;
$confirmUrl = $baseUrl . 'asset_confirm.php';
$deadlineText = $deadlineVal ? date('d/m/Y', strtotime($deadlineVal)) : 'ไม่กำหนด';
$lineMsg = "📋 [Powertech IT] {$name}\n\n"
         . "กรุณายืนยันว่าคุณยังครอบครองทรัพย์สินของบริษัทอยู่\n"
         . "กำหนดส่ง: {$deadlineText}\n\n"
         . "🔗 เข้าสู่ระบบ IT แล้วดูการแจ้งเตือนที่หน้าแรก\n"
         . "หรือรับลิงก์ส่วนตัวจากทีม IT";

sendNotify($conn, $lineMsg, 'asset_audit', $company);

echo json_encode([
    'status'        => 'success',
    'round_id'      => $roundId,
    'sent_count'    => $sentCount,
    'skipped_count' => $skippedCount,
]);
