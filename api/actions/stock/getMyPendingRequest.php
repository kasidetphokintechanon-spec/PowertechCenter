<?php
// api/actions/stock/getMyPendingRequest.php
// Returns the current user's latest pending stock request with full item details
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$user_id    = $_SESSION['user_id'];
$company    = $_GET['company']    ?? '';
$stock_type = $_GET['stock_type'] ?? '';

if (!$company || !$stock_type) throw new Exception("ต้องระบุ company และ stock_type");

// Get latest pending request
$stmt = $conn->prepare("SELECT id, notes, request_date, branch FROM stock_requests WHERE requester_id=? AND company=? AND stock_type=? AND status='pending' ORDER BY id DESC LIMIT 1");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("sss", $user_id, $company, $stock_type);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) { echo json_encode(null); exit; }

// Get items for that request
$stmt2 = $conn->prepare("SELECT ri.item_id, ri.qty_requested, si.item_code, si.name, si.unit, si.price_per_unit FROM stock_request_items ri JOIN stock_items si ON si.id=ri.item_id WHERE ri.request_id=?");
$stmt2->bind_param("i", $req['id']);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

echo json_encode([
    'id'           => $req['id'],
    'notes'        => $req['notes'],
    'request_date' => $req['request_date'],
    'branch'       => $req['branch'],
    'items'        => $items,
]);
?>
