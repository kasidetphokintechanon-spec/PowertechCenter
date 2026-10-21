<?php
// api/actions/stock/exportStockReport.php — monthly CSV (standard cost × qty)
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

$date_from = sprintf('%04d-%02d-01 00:00:00', $year, $month);
$date_to   = date('Y-m-t 23:59:59', strtotime($date_from));

$typeCode = ($stock_type === 'maid') ? 'MAD' : 'STA';
$report_no = sprintf('STK-RPT-%s-%s-%04d%02d', $company, $typeCode, $year, $month);
$label = ($stock_type === 'stationery') ? 'Stationery' : 'Maid Stock';
$thaiMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$monthLabel = ($thaiMonths[$month] ?? $month) . ' ' . ($year + 543);

$items_stmt = $conn->prepare("SELECT id, item_code, name, unit, min_stock, COALESCE(price_per_unit,0) AS price_per_unit FROM stock_items WHERE company = ? AND type = ? ORDER BY item_code ASC");
$items_stmt->bind_param("ss", $company, $stock_type);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$open_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type IN ('received','returned') THEN quantity WHEN type='issued' THEN -quantity WHEN type='adjusted' THEN quantity END),0) AS bal FROM stock_transactions WHERE item_id=? AND company=? AND transaction_date < ?");
$tx_stmt = $conn->prepare("SELECT type, SUM(quantity) AS qty FROM stock_transactions WHERE item_id=? AND company=? AND transaction_date BETWEEN ? AND ? GROUP BY type");

$totals = array_fill_keys(['opening_balance','opening_value','received','received_value','issued','issued_value','adjusted','adjusted_value','closing_balance','closing_value'], 0);

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$report_no}.csv\"");
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

fputcsv($out, ["เลขที่รายงาน", $report_no]);
fputcsv($out, ["รายงานสต็อก {$label}", "{$company} — {$monthLabel}"]);
fputcsv($out, ["สูตรกระทบยอด", "ยอดยกมา + รับ − เบิก + ปรับ = คงเหลือ"]);
fputcsv($out, ["ฐานมูลค่า", "ราคามาตรฐาน/หน่วย × จำนวน"]);
fputcsv($out, ["ผู้จัดทำ", trim($_SESSION['name'] ?? $_SESSION['username'] ?? '')]);
fputcsv($out, ["Export เมื่อ", date('d/m/Y H:i')]);
fputcsv($out, []);

fputcsv($out, [
    'รหัสสินค้า', 'ชื่อสินค้า', 'หน่วย', 'ราคา/หน่วย (บาท)',
    'ยอดยกมา', 'มูลค่ายกมา', 'รับเข้า', 'มูลค่ารับ', 'เบิกออก', 'มูลค่าเบิก',
    'ปรับ', 'มูลค่าปรับ', 'คงเหลือ', 'มูลค่าคงเหลือ', 'สถานะ'
]);

foreach ($items as $item) {
    $iid = $item['id'];
    $price = round(floatval($item['price_per_unit']), 2);
    $minStock = intval($item['min_stock'] ?? 0);

    $open_stmt->bind_param("iss", $iid, $company, $date_from);
    $open_stmt->execute();
    $opening = intval($open_stmt->get_result()->fetch_assoc()['bal'] ?? 0);

    $received = $issued = $adjusted = 0;
    $tx_stmt->bind_param("isss", $iid, $company, $date_from, $date_to);
    $tx_stmt->execute();
    foreach ($tx_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $tx) {
        $q = intval($tx['qty']);
        if ($tx['type'] === 'received' || $tx['type'] === 'returned') $received += $q;
        elseif ($tx['type'] === 'issued') $issued += $q;
        elseif ($tx['type'] === 'adjusted') $adjusted += $q;
    }

    $closing = $opening + $received - $issued + $adjusted;
    $openingVal  = round($opening  * $price, 2);
    $receivedVal = round($received * $price, 2);
    $issuedVal   = round($issued   * $price, 2);
    $adjustedVal = round($adjusted * $price, 2);
    $closingVal  = round($closing  * $price, 2);
    $status = $closing <= 0 ? 'หมด' : ($minStock > 0 && $closing <= $minStock ? 'ใกล้หมด' : 'ปกติ');

    fputcsv($out, [
        $item['item_code'], $item['name'], $item['unit'], number_format($price, 2, '.', ''),
        $opening, number_format($openingVal, 2, '.', ''),
        $received, number_format($receivedVal, 2, '.', ''),
        $issued, number_format($issuedVal, 2, '.', ''),
        $adjusted, number_format($adjustedVal, 2, '.', ''),
        $closing, number_format($closingVal, 2, '.', ''),
        $status
    ]);

    $totals['opening_balance'] += $opening;
    $totals['opening_value']   += $openingVal;
    $totals['received']        += $received;
    $totals['received_value']  += $receivedVal;
    $totals['issued']          += $issued;
    $totals['issued_value']    += $issuedVal;
    $totals['adjusted']        += $adjusted;
    $totals['adjusted_value']  += $adjustedVal;
    $totals['closing_balance'] += $closing;
    $totals['closing_value']   += $closingVal;
}

fputcsv($out, []);
fputcsv($out, [
    'รวมทั้งสิ้น', '', '', '',
    $totals['opening_balance'], number_format($totals['opening_value'], 2, '.', ''),
    $totals['received'], number_format($totals['received_value'], 2, '.', ''),
    $totals['issued'], number_format($totals['issued_value'], 2, '.', ''),
    $totals['adjusted'], number_format($totals['adjusted_value'], 2, '.', ''),
    $totals['closing_balance'], number_format($totals['closing_value'], 2, '.', ''),
    ''
]);

fclose($out);
exit;
?>
