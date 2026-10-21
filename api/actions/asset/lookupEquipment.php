<?php
// api/actions/asset/lookupEquipment.php — ค้นหาทะเบียนพัสดุสำหรับ setup.html
if (!isset($conn)) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No DB']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }

$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
$exact = trim($_GET['exact'] ?? $_POST['exact'] ?? '');
$limit = min(20, max(1, (int)($_GET['limit'] ?? $_POST['limit'] ?? 10)));

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = trim($_SESSION['company'] ?? '');

$sql = "SELECT a.id AS db_id, a.equipment_id, a.name, a.serial_number, a.hostname, a.ip_address,
               a.company, a.borrower, a.borrower_id, a.custodian_employee_id, a.department,
               a.status, a.owning_department, d.dept_name AS owning_department_name
        FROM it_assets a
        LEFT JOIN departments d ON a.owning_department = d.dept_id";

$params = [];
$types = '';

if ($exact !== '') {
    $sql .= " WHERE a.equipment_id = ?";
    $types = 's';
    $params[] = $exact;
} elseif ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " WHERE (a.equipment_id LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.hostname LIKE ?)";
    $types = 'ssss';
    $params = [$like, $like, $like, $like];
} else {
    echo json_encode(['status' => 'success', 'items' => []]);
    exit;
}

if ($role !== 'admin' && $sessionCompany !== '') {
    $sql .= ($exact !== '' || $q !== '') ? ' AND' : ' WHERE';
    $sql .= ' a.company = ?';
    $types .= 's';
    $params[] = $sessionCompany;
}

$sql .= ' ORDER BY a.equipment_id ASC LIMIT ' . $limit;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode(['status' => 'success', 'items' => $items], JSON_UNESCAPED_UNICODE);
