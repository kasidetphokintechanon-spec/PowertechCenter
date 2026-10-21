<?php
// api/actions/stock/returnStock.php — employee returns unused stock back to warehouse
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$item_id    = intval($_POST['item_id']   ?? 0);
$company    = trim($_POST['company']     ?? '');
$quantity   = intval($_POST['quantity']  ?? 0);
$reason     = trim($_POST['reason']      ?? '');
$request_id = intval($_POST['request_id'] ?? 0) ?: null;

if ($item_id <= 0)  throw new Exception("item_id ไม่ถูกต้อง");
if ($quantity <= 0) throw new Exception("จำนวนต้องมากกว่า 0");
if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
if (!$reason) throw new Exception("กรุณาระบุเหตุผลการคืน");
if ($quantity > 99999) throw new Exception("จำนวนคืนเกินกำหนดสูงสุด (99,999 ชิ้น)");

$itemChk = $conn->prepare("SELECT id FROM stock_items WHERE id = ? AND company = ? AND is_active = 1 LIMIT 1");
if (!$itemChk) throw new Exception("Prepare failed: " . $conn->error);
$itemChk->bind_param("is", $item_id, $company);
$itemChk->execute();
if (!$itemChk->get_result()->fetch_assoc()) throw new Exception("ไม่พบสินค้าที่เลือก หรือสินค้าถูกปิดใช้งาน");

$created_by    = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';
$requester_name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$requester_id  = (string)($_SESSION['user_id'] ?? '');

// Fetch current price for cost tracking
$pr = $conn->prepare("SELECT price_per_unit FROM stock_items WHERE id = ?");
$pr->bind_param("i", $item_id);
$pr->execute();
$prRow = $pr->get_result()->fetch_assoc();
$unit_cost = $prRow ? floatval($prRow['price_per_unit']) : 0.0;

// Record as 'returned' type — treated as positive adjustment to balance
$stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, requester_name, requester_id, request_id, notes, created_by) VALUES (?, ?, 'returned', ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("isidssiss", $item_id, $company, $quantity, $unit_cost, $requester_name, $requester_id, $request_id, $reason, $created_by);
$stmt->execute();

echo json_encode(['status' => 'success', 'tx_id' => $conn->insert_id]);
?>
