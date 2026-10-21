<?php
// api/actions/stock/adjustStock.php — admin adjusts stock balance
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$item_id  = intval($_POST['item_id']  ?? 0);
$company  = trim($_POST['company']    ?? '');
$quantity = intval($_POST['quantity'] ?? 0);   // positive = เพิ่ม, negative = ลด
$notes    = trim($_POST['notes']      ?? '');

if ($item_id <= 0) throw new Exception("item_id ไม่ถูกต้อง");
if ($quantity === 0) throw new Exception("จำนวนต้องไม่เป็น 0");
if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");

$created_by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';

// Store absolute value; sign encoded in notes prefix for audit trail
$stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, notes, created_by) VALUES (?, ?, 'adjusted', ?, 0, ?, ?)");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("isiss", $item_id, $company, $quantity, $notes, $created_by);
$stmt->execute();

echo json_encode(['status' => 'success', 'tx_id' => $conn->insert_id]);
?>
