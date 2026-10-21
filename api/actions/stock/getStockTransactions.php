<?php
// api/actions/stock/getStockTransactions.php — transaction log with audit fields
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = $_GET['company']    ?? ($_SESSION['company'] ?? 'PTA');
$stock_type = $_GET['stock_type'] ?? '';
$date_from  = $_GET['date_from']  ?? '';
$date_to    = $_GET['date_to']    ?? '';
$tx_type    = $_GET['tx_type']    ?? '';
$item_id    = intval($_GET['item_id'] ?? 0);

$where  = ["st.company = ?"];
$params = [$company];
$types  = 's';

if (!empty($stock_type)) {
    $where[] = "si.type = ?";
    $params[] = $stock_type;
    $types .= 's';
}
if (!empty($date_from)) {
    $where[] = "st.transaction_date >= ?";
    $params[] = $date_from . ' 00:00:00';
    $types .= 's';
}
if (!empty($date_to)) {
    $where[] = "st.transaction_date <= ?";
    $params[] = $date_to . ' 23:59:59';
    $types .= 's';
}
if (!empty($tx_type)) {
    $where[] = "st.type = ?";
    $params[] = $tx_type;
    $types .= 's';
}
if ($item_id > 0) {
    $where[] = "st.item_id = ?";
    $params[] = $item_id;
    $types .= 'i';
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT st.id, st.type, st.quantity, st.transaction_date, st.requester_name,
               st.notes, st.created_by, st.request_id, st.unit_cost,
               si.name AS item_name, si.item_code, si.unit, si.type AS item_type,
               COALESCE(si.price_per_unit, 0) AS price_per_unit,
               sr.approved_by, sr.status AS request_status
        FROM stock_transactions st
        JOIN stock_items si ON si.id = st.item_id
        LEFT JOIN stock_requests sr ON sr.id = st.request_id
        {$whereStr}
        ORDER BY st.transaction_date DESC
        LIMIT 2000";

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$sum_value = 0.0;
while ($row = $result->fetch_assoc()) {
    $price = floatval($row['price_per_unit']);
    $unitCost = (isset($row['unit_cost']) && floatval($row['unit_cost']) > 0)
        ? floatval($row['unit_cost'])
        : $price;
    $qty = intval($row['quantity']);
    $lineValue = round($qty * $unitCost, 2);
    if ($row['type'] === 'issued') {
        $lineValue = round(-$lineValue, 2);
    } elseif ($row['type'] === 'adjusted' && $qty < 0) {
        $lineValue = round($qty * $unitCost, 2);
    }
    $row['unit_cost'] = round($unitCost, 2);
    $row['line_value'] = $lineValue;
    $row['doc_ref'] = $row['request_id']
        ? ('REQ-' . str_pad($row['request_id'], 5, '0', STR_PAD_LEFT))
        : ('TX-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT));
    $sum_value += $lineValue;
    $rows[] = $row;
}

$typeCode = ($stock_type === 'maid') ? 'MAD' : (($stock_type === 'stationery') ? 'STA' : 'ALL');
$periodKey = ($date_from && $date_to)
    ? str_replace('-', '', substr($date_from, 0, 7))
    : date('Ym');

echo json_encode([
    'report_no'    => sprintf('STK-TXL-%s-%s-%s', $company, $typeCode, $periodKey),
    'prepared_by'  => trim($_SESSION['name'] ?? $_SESSION['username'] ?? ''),
    'generated_at' => date('c'),
    'sum_value'    => round($sum_value, 2),
    'items'        => $rows,
]);
?>
