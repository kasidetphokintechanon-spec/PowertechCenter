<?php
// api/actions/stock/exportStockCatalog.php
// Exports stock_items catalog as CSV for external editing and re-import
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = $_GET['company']    ?? '';
$stock_type = $_GET['stock_type'] ?? '';
$mode       = $_GET['mode']       ?? 'data'; // 'data' = current items, 'template' = empty template

$where  = [];
$params = [];
$types  = '';

if ($company && $company !== 'All' && in_array($company, ['PTA','PT4','PTE'])) {
    $where[]  = "company = ?";
    $params[] = $company;
    $types   .= 's';
}
if ($stock_type && in_array($stock_type, ['stationery','maid'])) {
    $where[]  = "type = ?";
    $params[] = $stock_type;
    $types   .= 's';
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$co_label  = $company ?: 'ALL';
$type_label = $stock_type ?: 'all';
$filename  = "StockCatalog_{$co_label}_{$type_label}_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}\"");

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
// Header row — matches import spec
fputcsv($out, [
    'item_code',        // รหัสสินค้า *
    'name',             // ชื่อสินค้า *
    'unit',             // หน่วย (ชิ้น/แผ่น/กล่อง ฯลฯ)
    'category',         // office / it / safety / cleaning / laundry / other
    'type',             // stationery / maid *
    'company',          // PTA / PT4 / PTE *
    'price_per_unit',   // ราคา/หน่วย (บาท)
    'min_stock',        // สต็อกขั้นต่ำ
    'max_stock',        // สต็อกสูงสุด
    'description',      // คำอธิบาย/สเปค
]);

if ($mode === 'template') {
    // Example row
    fputcsv($out, ['540-00-001', 'ปากกาลูกลื่น สีน้ำเงิน', 'แท่ง', 'office', 'stationery', $co_label !== 'ALL' ? $co_label : 'PTE', '5.00', '50', '200', 'ปากกา 0.5mm']);
    fputcsv($out, ['540-00-002', 'กระดาษ A4 80g', 'รีม', 'office', 'stationery', $co_label !== 'ALL' ? $co_label : 'PTE', '120.00', '5', '20', '']);
} else {
    $stmt = $conn->prepare("SELECT item_code, name, unit, category, type, company, price_per_unit, min_stock, max_stock, description FROM stock_items $whereStr ORDER BY company ASC, type ASC, item_code ASC");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['item_code'],
            $row['name'],
            $row['unit'],
            $row['category'],
            $row['type'],
            $row['company'],
            number_format((float)$row['price_per_unit'], 2, '.', ''),
            $row['min_stock'],
            $row['max_stock'],
            $row['description'] ?? '',
        ]);
    }
}

fclose($out);
exit;
?>
