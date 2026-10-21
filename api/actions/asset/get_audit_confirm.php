<?php
// api/actions/asset/get_audit_confirm.php
// Public endpoint — no session required, token-based only

$token = trim($_GET['token'] ?? '');
if ($token === '') { echo json_encode(['status'=>'error','message'=>'ไม่พบ token']); exit; }

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_rounds (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(200) NOT NULL,
    deadline    DATE DEFAULT NULL,
    status      ENUM('active','closed') DEFAULT 'active',
    created_by  VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_tokens (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    round_id      INT NOT NULL,
    employee_id   VARCHAR(100) NOT NULL,
    employee_name VARCHAR(200) NOT NULL,
    token         VARCHAR(64) NOT NULL,
    confirmed_at  DATETIME DEFAULT NULL,
    status        ENUM('pending','confirmed','partial') DEFAULT 'pending',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    INDEX idx_round (round_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS asset_audit_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    token_id     INT NOT NULL,
    asset_db_id  INT NOT NULL,
    equipment_id VARCHAR(100) DEFAULT NULL,
    asset_name   VARCHAR(200) DEFAULT NULL,
    status       ENUM('pending','confirmed','missing','transferred') DEFAULT 'pending',
    note         TEXT DEFAULT NULL,
    confirmed_at DATETIME DEFAULT NULL,
    INDEX idx_token (token_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Look up token
$st = $conn->prepare("SELECT t.*, r.name AS round_name, r.deadline, r.status AS round_status
    FROM asset_audit_tokens t
    JOIN asset_audit_rounds r ON r.id = t.round_id
    WHERE t.token = ? LIMIT 1");
$st->bind_param('s', $token);
$st->execute();
$tok = $st->get_result()->fetch_assoc();

if (!$tok) { echo json_encode(['status'=>'error','message'=>'ลิงก์ไม่ถูกต้องหรือหมดอายุแล้ว']); exit; }
if ($tok['round_status'] === 'closed') { echo json_encode(['status'=>'error','message'=>'รอบตรวจนับนี้ปิดแล้ว']); exit; }

// Load items for this token
$itemSt = $conn->prepare("SELECT i.*, a.image_url, a.brand, a.model, a.serial_number, a.location
    FROM asset_audit_items i
    LEFT JOIN it_assets a ON a.id = i.asset_db_id
    WHERE i.token_id = ? ORDER BY i.id ASC");
$itemSt->bind_param('i', $tok['id']);
$itemSt->execute();
$items = $itemSt->get_result()->fetch_all(MYSQLI_ASSOC);

// Normalize image URLs
foreach ($items as &$row) {
    $v = trim((string)($row['image_url'] ?? ''));
    if ($v !== '' && strpos($v, 'uploads/') !== 0) {
        $path = ltrim(parse_url($v, PHP_URL_PATH) ?: $v, '/');
        $pos  = strpos($path, 'uploads/');
        $row['image_url'] = $pos !== false ? substr($path, $pos) : '';
    }
}
unset($row);

echo json_encode([
    'status'        => 'success',
    'token_id'      => $tok['id'],
    'employee_name' => $tok['employee_name'],
    'round_name'    => $tok['round_name'],
    'deadline'      => $tok['deadline'],
    'already_done'  => $tok['status'] !== 'pending',
    'items'         => $items,
]);
