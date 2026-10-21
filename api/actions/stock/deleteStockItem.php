<?php
// api/actions/stock/deleteStockItem.php
// Smart delete: hard-delete if no history, soft-delete (is_active=0) if transactions exist
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin' && empty($_SESSION['can_manage_stock'])) throw new Exception("Access Denied");

$id = intval($_POST['id'] ?? 0);
if (!$id) throw new Exception("ไม่มี id");

// Verify item exists
$chk = $conn->prepare("SELECT id, item_code, name FROM stock_items WHERE id=?");
$chk->bind_param("i", $id);
$chk->execute();
$item = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$item) throw new Exception("ไม่พบรายการ");

// Check for transaction history
$hasHistory = false;
$txChk = $conn->prepare("SELECT 1 FROM stock_transactions WHERE item_id=? LIMIT 1");
$txChk->bind_param("i", $id);
$txChk->execute();
if ($txChk->get_result()->num_rows > 0) $hasHistory = true;
$txChk->close();

if (!$hasHistory) {
    // Check request_items too
    $rqChk = $conn->prepare("SELECT 1 FROM stock_request_items WHERE item_id=? LIMIT 1");
    $rqChk->bind_param("i", $id);
    $rqChk->execute();
    if ($rqChk->get_result()->num_rows > 0) $hasHistory = true;
    $rqChk->close();
}

if ($hasHistory) {
    // Soft delete — preserve history
    $stmt = $conn->prepare("UPDATE stock_items SET is_active=0 WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'action' => 'deactivated', 'message' => "ปิดใช้งาน [{$item['item_code']}] {$item['name']} (มีประวัติการใช้งาน)"]);
} else {
    // Hard delete — no history
    $stmt = $conn->prepare("DELETE FROM stock_items WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'action' => 'deleted', 'message' => "ลบ [{$item['item_code']}] {$item['name']} เรียบร้อย"]);
}
?>
