<?php
// api/actions/stock/getStockRequests.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));

$status     = $_GET['status']     ?? 'pending';   // pending | approved | rejected | all | my
$company    = $_GET['company']    ?? '';
$stock_type = $_GET['stock_type'] ?? '';
$my_only    = ($_GET['my'] ?? '0') === '1' || $status === 'my';

// Non-approvers can only see their own requests
if (!$isApprover) $my_only = true;

$where = [];
$params = [];
$types = '';

if ($my_only) {
    $uid = (string)$_SESSION['user_id'];
    $where[] = "r.requester_id = ?";
    $params[] = $uid;
    $types .= 's';
}

if ($status !== 'all' && $status !== 'my') {
    $where[] = "r.status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($company) && $company !== 'All') {
    $where[] = "r.company = ?";
    $params[] = $company;
    $types .= 's';
} elseif (!$isApprover) {
    // Non-approver filtered to their company
    $c = $_SESSION['company'] ?? '';
    if ($c) { $where[] = "r.company = ?"; $params[] = $c; $types .= 's'; }
}

if (!empty($stock_type)) {
    $where[] = "r.stock_type = ?";
    $params[] = $stock_type;
    $types .= 's';
}

$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
if (!empty($dateFrom)) {
    $where[] = "DATE(r.request_date) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if (!empty($dateTo)) {
    $where[] = "DATE(r.request_date) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT r.*,
            GROUP_CONCAT(CONCAT(si.name, ' (x', sri.qty_requested, IF(sri.qty_approved IS NOT NULL, CONCAT(' → x', sri.qty_approved), ''), ')') SEPARATOR ', ') AS items_summary,
            COUNT(sri.id) AS item_count
        FROM stock_requests r
        LEFT JOIN stock_request_items sri ON sri.request_id = r.id
        LEFT JOIN stock_items si ON si.id = sri.item_id
        {$whereStr}
        GROUP BY r.id
        ORDER BY r.request_date DESC
        LIMIT 500";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
    if (!$result) throw new Exception("Query failed: " . $conn->error);
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
?>
