<?php
// api/actions/stock/getStockBalance.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$company    = $_GET['company']    ?? ($_SESSION['company'] ?? 'PTA');
$stock_type = $_GET['stock_type'] ?? '';
$allCompanies = ($company === 'All');

$where = [];
$params = [];
$types = '';

if (!$allCompanies) {
    $where[] = "si.company = ?";
    $params[] = $company;
    $types .= 's';
}

if (!empty($stock_type)) {
    $where[] = "si.type = ?";
    $params[] = $stock_type;
    $types .= 's';
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// For JOIN: filter transactions by company or leave unfiltered
$txCompanyClause = $allCompanies ? '' : 'AND st.company = ?';

$sql = "SELECT si.id, si.item_code, si.name, si.unit, si.type, si.company, si.min_stock, si.max_stock, si.is_active, si.price_per_unit, si.category, si.description, si.image_url, si.has_expiry,
            COALESCE(SUM(CASE WHEN st.type='received'  THEN st.quantity
                              WHEN st.type='issued'    THEN -st.quantity
                              WHEN st.type='adjusted'  THEN st.quantity
                              WHEN st.type='returned'  THEN st.quantity
                         END), 0) AS current_balance,
            COALESCE(SUM(CASE WHEN st.type='issued'   THEN st.quantity ELSE 0 END), 0) AS total_issued,
            COALESCE(SUM(CASE WHEN st.type='received' THEN st.quantity ELSE 0 END), 0) AS total_received,
            COALESCE(SUM(CASE WHEN st.type='returned' THEN st.quantity ELSE 0 END), 0) AS total_returned,
            COALESCE(SUM(CASE WHEN st.type='issued' THEN st.quantity * COALESCE(st.unit_cost,0) ELSE 0 END), 0) AS total_cost_issued,
            MIN(CASE WHEN st.type='received' AND st.expiry_date IS NOT NULL AND st.expiry_date >= CURDATE() THEN st.expiry_date END) AS nearest_expiry
        FROM stock_items si
        LEFT JOIN stock_transactions st ON st.item_id = si.id {$txCompanyClause}
        {$whereStr}
        GROUP BY si.id
        ORDER BY si.company ASC, si.item_code ASC";

// Prepend company param for JOIN clause if not All
if (!$allCompanies) {
    array_unshift($params, $company);
    $types = 's' . $types;
}

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['is_low'] = ($row['min_stock'] > 0 && $row['current_balance'] <= $row['min_stock']);
    $data[] = $row;
}
echo json_encode($data);
?>
