<?php
// api/actions/stock/getStockItems.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$company  = $_GET['company']  ?? ($_SESSION['company'] ?? 'PTA');
$type     = $_GET['type'] ?? $_GET['stock_type'] ?? 'stationery';
if (!in_array($type, ['stationery', 'maid'], true)) $type = 'stationery';
$for_admin = ($_GET['for_admin'] ?? '0') === '1';

$role = $_SESSION['role'] ?? 'guest';
$dept = $_SESSION['primary_department'] ?? '';
$isApprover = ($role === 'admin' || $role === 'staff' || strtoupper(trim($dept)) === 'GA' || !empty($_SESSION['can_manage_stock']));

// If not admin context, only return active items with their current balance
$stmt = $conn->prepare(
    "SELECT si.*,
        COALESCE((
            SELECT SUM(CASE WHEN st.type='received' THEN st.quantity
                            WHEN st.type='issued'   THEN -st.quantity
                            WHEN st.type='adjusted' THEN st.quantity
                            WHEN st.type='returned' THEN st.quantity
                       END)
            FROM stock_transactions st
            WHERE st.item_id = si.id AND st.company = ?
        ), 0) AS current_balance
     FROM stock_items si
     WHERE si.company = ? AND si.type = ?
     " . ($for_admin && $isApprover ? "" : "AND si.is_active = 1") . "
     ORDER BY si.item_code ASC"
);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("sss", $company, $company, $type);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
echo json_encode($items);
?>
