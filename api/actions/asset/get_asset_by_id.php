<?php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
    exit;
}

$sql = "SELECT a.*, a.id AS db_id,
            a.equipment_id AS equipment_id,
            a.name        AS name,
            a.serial_number,
            a.brand, a.model,
            a.status,
            a.image_url,
            a.images,
            a.location,
            a.department,
            a.company,
            a.warranty_expires_on,
            a.purchase_date,
            a.borrower,
            c.cat_name AS category_name,
            d.dept_name AS owning_department_name,
            e.name AS custodian_name
        FROM it_assets a
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
        LEFT JOIN departments d ON a.owning_department = d.dept_id
        LEFT JOIN employees e ON a.custodian_employee_id = e.id
        WHERE a.id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode(['status' => 'error', 'message' => $conn->error]); exit; }
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบพัสดุนี้ในระบบ']);
    exit;
}

$norm = function($v) {
    $v = trim((string)($v ?? ''));
    if ($v === '' || strpos($v, 'uploads/') === 0) return $v;
    $path = ltrim(parse_url($v, PHP_URL_PATH) ?: $v, '/');
    $pos = strpos($path, 'uploads/');
    return $pos !== false ? substr($path, $pos) : $v;
};

$row['image_url'] = $norm($row['image_url']);
if (!empty($row['images'])) {
    $imgs = json_decode($row['images'], true);
    $row['images'] = is_array($imgs) ? array_values(array_filter(array_map($norm, $imgs))) : [];
} else {
    $row['images'] = [];
}

$histLimit = max(1, min(50, intval($_GET['history_limit'] ?? 10)));

$cstmt = $conn->prepare("SELECT COUNT(*) as total FROM asset_history WHERE asset_db_id = ? AND event_type <> 'ACCESS'");
$cstmt->bind_param("i", $id);
$cstmt->execute();
$histTotal = intval($cstmt->get_result()->fetch_assoc()['total']);

$hstmt = $conn->prepare("SELECT history_id, event_type, details, user_name, timestamp FROM asset_history WHERE asset_db_id = ? AND event_type <> 'ACCESS' ORDER BY timestamp DESC, history_id DESC LIMIT ?");
$hstmt->bind_param("ii", $id, $histLimit);
$hstmt->execute();
$hres = $hstmt->get_result();
$history = [];
while ($h = $hres->fetch_assoc()) $history[] = $h;

// ดึง ticket ล่าสุดของอุปกรณ์นี้
$latestTicket = null;
if (!empty($row['equipment_id'])) {
    $tStmt = $conn->prepare("SELECT id, status, problem, solution, date, completed_date, servicedBy FROM it_logs WHERE asset_id = ? ORDER BY date DESC, id DESC LIMIT 1");
    $tStmt->bind_param('s', $row['equipment_id']);
    $tStmt->execute();
    $latestTicket = $tStmt->get_result()->fetch_assoc();
}

echo json_encode(['status' => 'success', 'asset' => $row, 'history' => $history, 'history_total' => $histTotal, 'latest_ticket' => $latestTicket], JSON_UNESCAPED_UNICODE);
