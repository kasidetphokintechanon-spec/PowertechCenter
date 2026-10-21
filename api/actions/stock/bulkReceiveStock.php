<?php
// api/actions/stock/bulkReceiveStock.php — admin records multiple incoming stock items at once
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = trim($_POST['company']  ?? '');
$items_json = $_POST['items']         ?? '[]';
$items      = json_decode($items_json, true);

if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
if (!is_array($items) || count($items) === 0) throw new Exception("ไม่มีรายการที่จะบันทึก");

$created_by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, notes, created_by) VALUES (?, ?, 'received', ?, ?, ?, ?)");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

    $count = 0;
    foreach ($items as $item) {
        $item_id   = intval($item['item_id']   ?? 0);
        $qty       = intval($item['qty']        ?? 0);
        $unit_cost = floatval($item['unit_cost'] ?? 0);
        $notes     = trim($item['notes']        ?? '');
        if ($item_id <= 0 || $qty <= 0) continue;
        $stmt->bind_param("isidss", $item_id, $company, $qty, $unit_cost, $notes, $created_by);
        $stmt->execute();
        $count++;
    }

    if ($count === 0) throw new Exception("ไม่มีรายการที่ valid (item_id > 0 และ qty > 0)");

    $conn->commit();
    echo json_encode(['status' => 'success', 'received' => $count]);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
?>
