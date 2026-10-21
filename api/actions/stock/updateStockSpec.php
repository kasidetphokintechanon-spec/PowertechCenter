<?php
// api/actions/stock/updateStockSpec.php — inline spec (description) update
if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin' && empty($_SESSION['can_manage_stock'])) throw new Exception("Access Denied");

$item_id     = intval($_POST['item_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

if (!$item_id) throw new Exception("ไม่มี item_id");

$stmt = $conn->prepare("UPDATE stock_items SET description=? WHERE id=?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("si", $description, $item_id);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success']);
