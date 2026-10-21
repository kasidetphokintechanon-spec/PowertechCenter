<?php
// api/actions/stock/receiveStock.php — admin records incoming stock
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$item_id     = intval($_POST['item_id']    ?? 0);
$company     = trim($_POST['company']      ?? '');
$quantity    = intval($_POST['quantity']   ?? 0);
$unit_cost   = floatval($_POST['unit_cost'] ?? 0);
$notes       = trim($_POST['notes']        ?? '');
$supplier    = trim($_POST['supplier']     ?? '');
$expiry_date = trim($_POST['expiry_date']  ?? '') ?: null;

if ($item_id <= 0)  throw new Exception("item_id ไม่ถูกต้อง");
if ($quantity <= 0) throw new Exception("จำนวนต้องมากกว่า 0");
if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");

$created_by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';

// 1. Get current balance + price for weighted average calculation
$balRes = $conn->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type='received' THEN quantity
                         WHEN type='issued'   THEN -quantity
                         WHEN type='adjusted' THEN quantity
                    END), 0) AS current_balance
    FROM stock_transactions
    WHERE item_id = ? AND company = ?
");
if (!$balRes) throw new Exception("Prepare failed: " . $conn->error);
$balRes->bind_param("is", $item_id, $company);
$balRes->execute();
$balRow = $balRes->get_result()->fetch_assoc();
$current_balance = max(0, intval($balRow['current_balance'] ?? 0));

$priceRes = $conn->prepare("SELECT price_per_unit FROM stock_items WHERE id = ?");
if (!$priceRes) throw new Exception("Prepare failed: " . $conn->error);
$priceRes->bind_param("i", $item_id);
$priceRes->execute();
$priceRow = $priceRes->get_result()->fetch_assoc();
$old_price = floatval($priceRow['price_per_unit'] ?? 0);

// 2. Weighted average: (old_balance × old_price + new_qty × new_price) / (old_balance + new_qty)
if ($unit_cost > 0) {
    $total_qty   = $current_balance + $quantity;
    $new_price   = ($total_qty > 0)
        ? round(($current_balance * $old_price + $quantity * $unit_cost) / $total_qty, 2)
        : $unit_cost;
} else {
    // ไม่ได้ระบุราคา — คงราคาเดิมไว้
    $new_price = $old_price;
}

// 3. Record transaction (with supplier + expiry_date)
$stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, notes, created_by, supplier, expiry_date) VALUES (?, ?, 'received', ?, ?, ?, ?, ?, ?)");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("isidssss", $item_id, $company, $quantity, $unit_cost, $notes, $created_by, $supplier, $expiry_date);
$stmt->execute();
$tx_id = $conn->insert_id;

// 4. Update catalog price_per_unit with new weighted average
if ($unit_cost > 0) {
    $upd = $conn->prepare("UPDATE stock_items SET price_per_unit = ? WHERE id = ?");
    if (!$upd) throw new Exception("Prepare failed: " . $conn->error);
    $upd->bind_param("di", $new_price, $item_id);
    $upd->execute();
}

echo json_encode([
    'status'    => 'success',
    'tx_id'     => $tx_id,
    'old_price' => $old_price,
    'new_price' => $new_price,
    'method'    => 'weighted_average',
]);
?>
