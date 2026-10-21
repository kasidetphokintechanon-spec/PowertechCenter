<?php
// api/actions/stock/getStockMonthlyReport.php — monthly qty + standard-cost value (audit-ready)
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = $_GET['company']    ?? ($_SESSION['company'] ?? 'PTA');
$stock_type = $_GET['stock_type'] ?? 'stationery';
$year       = intval($_GET['year']  ?? date('Y'));
$month      = intval($_GET['month'] ?? date('n'));

$period_start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$period_end   = date('Y-m-t 23:59:59', strtotime($period_start));

$typeCode = ($stock_type === 'maid') ? 'MAD' : 'STA';
$report_no = sprintf('STK-RPT-%s-%s-%04d%02d', $company, $typeCode, $year, $month);

$thaiMonths = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
               'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];

$qtyCase = "CASE WHEN st.type IN ('received','returned') THEN st.quantity
                 WHEN st.type='issued'   THEN -st.quantity
                 WHEN st.type='adjusted' THEN st.quantity ELSE 0 END";

$sql = "SELECT
    si.id, si.item_code, si.name, si.unit, si.min_stock, si.max_stock, si.is_active,
    COALESCE(si.price_per_unit, 0) AS price_per_unit,
    COALESCE((
        SELECT SUM({$qtyCase})
        FROM stock_transactions st
        WHERE st.item_id = si.id AND st.company = ? AND st.transaction_date < ?
    ), 0) AS opening_balance,
    COALESCE((
        SELECT SUM(st.quantity) FROM stock_transactions st
        WHERE st.item_id = si.id AND st.company = ? AND st.type IN ('received','returned')
          AND st.transaction_date BETWEEN ? AND ?
    ), 0) AS received,
    COALESCE((
        SELECT SUM(st.quantity) FROM stock_transactions st
        WHERE st.item_id = si.id AND st.company = ? AND st.type = 'issued'
          AND st.transaction_date BETWEEN ? AND ?
    ), 0) AS issued,
    COALESCE((
        SELECT SUM(st.quantity) FROM stock_transactions st
        WHERE st.item_id = si.id AND st.company = ? AND st.type = 'adjusted'
          AND st.transaction_date BETWEEN ? AND ?
    ), 0) AS adjusted
FROM stock_items si
WHERE si.company = ? AND si.type = ?
ORDER BY si.item_code ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

$stmt->bind_param("sssssssssssss",
    $company, $period_start,
    $company, $period_start, $period_end,
    $company, $period_start, $period_end,
    $company, $period_start, $period_end,
    $company, $stock_type
);
if (!$stmt->execute()) throw new Exception("Execute failed: " . $stmt->error);
$result = $stmt->get_result();

$rows = [];
$totals = [
    'opening_balance' => 0, 'opening_value' => 0,
    'received' => 0, 'received_value' => 0,
    'issued' => 0, 'issued_value' => 0,
    'adjusted' => 0, 'adjusted_value' => 0,
    'closing_balance' => 0, 'closing_value' => 0,
];

while ($row = $result->fetch_assoc()) {
    $price = round(floatval($row['price_per_unit']), 2);
    $opening  = intval($row['opening_balance']);
    $received = intval($row['received']);
    $issued   = intval($row['issued']);
    $adjusted = intval($row['adjusted']);
    $closing  = $opening + $received - $issued + $adjusted;

    // มูลค่า audit: ราคามาตรฐาน/หน่วย × จำนวน (กระทบยอดได้เสมอ)
    $row['price_per_unit']  = $price;
    $row['opening_balance'] = $opening;
    $row['received']        = $received;
    $row['issued']          = $issued;
    $row['adjusted']        = $adjusted;
    $row['closing_balance'] = $closing;
    $row['opening_value']   = round($opening  * $price, 2);
    $row['received_value']  = round($received * $price, 2);
    $row['issued_value']    = round($issued   * $price, 2);
    $row['adjusted_value']  = round($adjusted * $price, 2);
    $row['closing_value']   = round($closing  * $price, 2);

    $row['is_low'] = ($closing > 0 && $row['min_stock'] > 0 && $closing <= $row['min_stock']) ? 1 : 0;
    $row['is_oos'] = $closing <= 0 ? 1 : 0;

    foreach (['opening_balance','opening_value','received','received_value','issued','issued_value',
              'adjusted','adjusted_value','closing_balance','closing_value'] as $k) {
        $totals[$k] += $row[$k];
    }
    $rows[] = $row;
}

foreach (['opening_value','received_value','issued_value','adjusted_value','closing_value'] as $k) {
    $totals[$k] = round($totals[$k], 2);
}

$prepared_by = trim($_SESSION['name'] ?? $_SESSION['username'] ?? '');

echo json_encode([
    'report_no'    => $report_no,
    'company'      => $company,
    'stock_type'   => $stock_type,
    'year'         => $year,
    'month'        => $month,
    'period_label' => ($thaiMonths[$month] ?? $month) . ' ' . ($year + 543),
    'prepared_by'  => $prepared_by,
    'generated_at' => date('c'),
    'formula'      => 'ยอดยกมา + รับ − เบิก + ปรับ = คงเหลือ',
    'value_basis'  => 'มูลค่า = ราคามาตรฐาน/หน่วย (price_per_unit) × จำนวน — ใช้กระทบยอด audit',
    'totals'       => $totals,
    'items'        => $rows,
]);
?>
