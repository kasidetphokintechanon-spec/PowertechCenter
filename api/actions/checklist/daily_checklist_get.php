<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$company = $_GET['company'] ?? $sessionCompany;
$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) {
    $company = $sessionCompany;
}

if ($company === '' && $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบบริษัทใน Session']);
    exit;
}

$params = [];
$types = '';

$whereItems = ["i.is_active = 1"];
if ($company && $company !== 'all') {
    $whereItems[] = "(i.company = ? OR i.company = '')";
    $params[] = $company;
    $types .= 's';
}

$whereSql = implode(' AND ', $whereItems);

$sql = "SELECT i.id, i.company, i.title, i.description, i.category, i.sort_order,
               l.id AS log_id, l.checklist_date, l.user_id, l.user_name, l.checked_at, l.notes
        FROM daily_checklist_items i
        LEFT JOIN daily_checklist_logs l
          ON l.checklist_item_id = i.id
         AND l.checklist_date = ?
        WHERE $whereSql
        ORDER BY i.company, i.sort_order, i.id";

array_unshift($params, $date);
$types = 's' . $types;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $status = $row['log_id'] ? 'done' : 'pending';
    $items[] = [
        'id' => (int)$row['id'],
        'company' => $row['company'],
        'title' => $row['title'],
        'description' => $row['description'],
        'category' => $row['category'],
        'sort_order' => (int)$row['sort_order'],
        'status' => $status,
        'log_id' => $row['log_id'] ? (int)$row['log_id'] : null,
        'checked_at' => $row['checked_at'],
        'checked_by' => $row['user_name'] ?: $row['user_id'],
        'notes' => $row['notes']
    ];
}

echo json_encode([
    'status' => 'success',
    'date' => $date,
    'company' => $company ?: $sessionCompany,
    'user' => [
        'id' => $userId,
        'name' => $userName,
        'role' => $role
    ],
    'items' => $items
]);
