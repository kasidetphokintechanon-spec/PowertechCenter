<?php
// api/actions/stock/getDeptQuota.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$company    = trim($_GET['company']    ?? ($_SESSION['company'] ?? ''));
$department = trim($_GET['department'] ?? ($_SESSION['primary_department'] ?? ''));
$stock_type = trim($_GET['stock_type'] ?? 'stationery');
$month      = trim($_GET['month']      ?? date('Y-m')); // YYYY-MM

// Done reading $_SESSION — release the lock now. stock_management.html's quota tab
// fires this concurrently per department (can be 10-20+), which would otherwise queue
// behind PHP's session lock instead of actually running in parallel.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if (!$company || !$department) {
    echo json_encode(['limit' => 0, 'value_limit' => 0, 'used' => 0, 'value_used' => 0, 'month' => $month]);
    exit;
}

// Quota limit for this dept/company/type
$stmt = $conn->prepare("SELECT monthly_limit, monthly_value_limit FROM stock_dept_quotas WHERE company=? AND department=? AND stock_type=?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("sss", $company, $department, $stock_type);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$limit       = $row ? intval($row['monthly_limit']) : 0;
$value_limit = $row ? floatval($row['monthly_value_limit']) : 0.0;

// Usage: sum of qty_approved and value (qty × price_per_unit) for approved requests this month
$monthStart = $month . '-01';
$monthEnd   = date('Y-m-t', strtotime($monthStart));

$stmt2 = $conn->prepare("
    SELECT
        COALESCE(SUM(sri.qty_approved), 0) AS used,
        COALESCE(SUM(sri.qty_approved * COALESCE(si.price_per_unit, 0)), 0) AS value_used
    FROM stock_requests sr
    JOIN stock_request_items sri ON sri.request_id = sr.id
    JOIN stock_items si ON si.id = sri.item_id
    WHERE sr.company = ?
      AND sr.department = ?
      AND sr.stock_type = ?
      AND sr.status = 'approved'
      AND DATE(sr.approved_at) BETWEEN ? AND ?
");
if (!$stmt2) throw new Exception("Prepare failed: " . $conn->error);
$stmt2->bind_param("sssss", $company, $department, $stock_type, $monthStart, $monthEnd);
$stmt2->execute();
$usedRow    = $stmt2->get_result()->fetch_assoc();
$used       = intval($usedRow['used'] ?? 0);
$value_used = round(floatval($usedRow['value_used'] ?? 0), 2);

echo json_encode([
    'limit'       => $limit,
    'value_limit' => $value_limit,
    'used'        => $used,
    'value_used'  => $value_used,
    'month'       => $month,
]);
?>
