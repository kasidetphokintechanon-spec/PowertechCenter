<?php
// api/actions/stock/importStockItems.php
// Receives JSON array of rows (parsed client-side from CSV/Excel),
// upserts into stock_items. Returns summary: inserted, updated, skipped.
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin' && empty($_SESSION['can_manage_stock'])) throw new Exception("Access Denied");

$rows = json_decode($_POST['rows'] ?? '[]', true);
if (!is_array($rows) || !count($rows)) throw new Exception("ไม่มีข้อมูลที่จะนำเข้า");

$allowed_cats = ['office','it','safety','cleaning','laundry','other'];

$inserted = 0;
$updated  = 0;
$errors   = [];

foreach ($rows as $i => $row) {
    $rowNum     = $i + 2; // row 1 = header
    $item_code  = trim($row['item_code']      ?? '');
    $name       = trim($row['name']           ?? '');
    $unit       = trim($row['unit']           ?? '') ?: 'ชิ้น';
    $category   = trim($row['category']       ?? '');
    $type       = trim($row['type']           ?? '');
    $company    = trim($row['company']        ?? '');
    $price      = max(0, floatval($row['price_per_unit'] ?? 0));
    $min_stock  = max(0, intval($row['min_stock']  ?? 0));
    $max_stock  = max(0, intval($row['max_stock']  ?? 0));
    $description = trim($row['description']  ?? '');

    if (!$item_code) { $errors[] = "แถว {$rowNum}: ไม่มี item_code"; continue; }
    if (!$name)      { $errors[] = "แถว {$rowNum}: ไม่มีชื่อสินค้า [{$item_code}]"; continue; }
    if (!in_array($type, ['stationery','maid'])) { $errors[] = "แถว {$rowNum}: type '{$type}' ไม่ถูกต้อง [{$item_code}]"; continue; }
    if (!in_array($company, ['PTA','PT4','PTE'])) { $errors[] = "แถว {$rowNum}: company '{$company}' ไม่ถูกต้อง [{$item_code}]"; continue; }
    if (!in_array($category, $allowed_cats)) $category = 'other';

    // Check existing
    $chk = $conn->prepare("SELECT id FROM stock_items WHERE item_code=? AND company=?");
    $chk->bind_param("ss", $item_code, $company);
    $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE stock_items SET name=?,unit=?,category=?,type=?,price_per_unit=?,min_stock=?,max_stock=?,description=? WHERE id=?");
        $stmt->bind_param("ssssdiisi", $name, $unit, $category, $type, $price, $min_stock, $max_stock, $description, $existing['id']);
        $stmt->execute();
        $stmt->close();
        $updated++;
    } else {
        $stmt = $conn->prepare("INSERT INTO stock_items (item_code,name,unit,category,type,company,price_per_unit,min_stock,max_stock,description,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
        $stmt->bind_param("ssssssdiis", $item_code, $name, $unit, $category, $type, $company, $price, $min_stock, $max_stock, $description);
        $stmt->execute();
        $stmt->close();
        $inserted++;
    }
}

echo json_encode([
    'status'   => 'success',
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => count($errors),
    'errors'   => array_slice($errors, 0, 20),
]);
?>
