<?php
// api/actions/stock/getRequestItems.php — items for a specific stock request
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$request_id = intval($_GET['request_id'] ?? 0);
if ($request_id <= 0) throw new Exception("request_id ไม่ถูกต้อง");

$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
$uid = (string)$_SESSION['user_id'];

// Done reading $_SESSION — release the lock now. my_stock_history.html fires this
// concurrently per row on the page (up to 12), which would otherwise queue behind
// PHP's session lock instead of actually running in parallel.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// Verify access: approver OR own request
$chk = $conn->prepare("SELECT id, requester_id FROM stock_requests WHERE id = ?");
$chk->bind_param("i", $request_id);
$chk->execute();
$req_row = $chk->get_result()->fetch_assoc();
if (!$req_row) throw new Exception("ไม่พบคำขอ");
if (!$isApprover && $req_row['requester_id'] !== $uid) throw new Exception("Access Denied");

$stmt = $conn->prepare("
    SELECT sri.id, sri.item_id, sri.qty_requested, sri.qty_approved,
           si.name, si.unit, si.item_code,
           COALESCE((
               SELECT SUM(CASE WHEN st.type='received' THEN st.quantity
                               WHEN st.type='issued'   THEN -st.quantity
                               WHEN st.type='adjusted' THEN st.quantity
                          END)
               FROM stock_transactions st
               WHERE st.item_id = si.id
                 AND st.company = (SELECT company FROM stock_requests WHERE id = ?)
           ), 0) AS current_balance
    FROM stock_request_items sri
    JOIN stock_items si ON si.id = sri.item_id
    WHERE sri.request_id = ?
    ORDER BY sri.id ASC
");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("ii", $request_id, $request_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode($data);
?>
