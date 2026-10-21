<?php
// api/actions/stock/sendLowStockAlert.php — query low-stock items and send Telegram/LINE notification
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = trim($_POST['company']    ?? $_GET['company']    ?? '');
$stock_type = trim($_POST['stock_type'] ?? $_GET['stock_type'] ?? '');

$where  = ["si.min_stock > 0", "si.is_active = 1"];
$params = [];
$types  = '';

// For the balance JOIN, also filter transactions by company if specified
$txClause = '';
if ($company && $company !== 'All' && in_array($company, ['PTA','PT4','PTE'])) {
    $where[]  = "si.company = ?";
    $params[] = $company;
    $types   .= 's';
    $txClause = "AND st.company = ?";
}
if ($stock_type && in_array($stock_type, ['stationery','maid'])) {
    $where[]  = "si.type = ?";
    $params[] = $stock_type;
    $types   .= 's';
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT si.id, si.item_code, si.name, si.unit, si.company, si.type, si.min_stock,
            COALESCE(SUM(CASE
                WHEN st.type='received' THEN st.quantity
                WHEN st.type='issued'   THEN -st.quantity
                WHEN st.type='adjusted' THEN st.quantity
            END), 0) AS current_balance
        FROM stock_items si
        LEFT JOIN stock_transactions st ON st.item_id = si.id {$txClause}
        {$whereStr}
        GROUP BY si.id
        HAVING current_balance <= si.min_stock
        ORDER BY si.company ASC, current_balance ASC, si.name ASC";

// Prepend company param for JOIN clause if filtering
if ($txClause) {
    array_unshift($params, $company);
    $types = 's' . $types;
}

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($rows) === 0) {
    echo json_encode(['status' => 'success', 'count' => 0, 'message' => 'ไม่มีสินค้าสต็อกต่ำ']);
    exit;
}

$typeMap  = ['stationery' => '📎 เครื่องเขียน', 'maid' => '🧹 แม่บ้าน'];
$lines    = [];
foreach ($rows as $r) {
    $bal   = intval($r['current_balance']);
    $min   = intval($r['min_stock']);
    $icon  = $bal <= 0 ? '🔴' : '🟡';
    $tl    = $typeMap[$r['type']] ?? $r['type'];
    $lines[] = "{$icon} [{$r['company']} · {$tl}] {$r['name']} — คงเหลือ <b>{$bal}</b> {$r['unit']} / ขั้นต่ำ {$min}";
}

$count = count($rows);
$msg   = "<b>⚠️ แจ้งเตือน Stock ต่ำ — {$count} รายการ</b>\n" .
         "➖➖➖➖➖➖➖➖➖➖\n" .
         implode("\n", $lines) . "\n\n" .
         "#LowStockAlert";

sendNotify($conn, $msg, 'stock_low', $company ?: null);

echo json_encode(['status' => 'success', 'count' => $count]);
?>
