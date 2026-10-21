<?php
// api/actions/stock/denyStockRequest.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$request_id  = intval($_POST['request_id'] ?? 0);
$deny_reason = trim($_POST['deny_reason'] ?? '');
if ($request_id <= 0) throw new Exception("request_id ไม่ถูกต้อง");

$req_stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id = ? AND status = 'pending'");
if (!$req_stmt) throw new Exception("Prepare failed: " . $conn->error);
$req_stmt->bind_param("i", $request_id);
$req_stmt->execute();
$req = $req_stmt->get_result()->fetch_assoc();
if (!$req) throw new Exception("คำขอไม่พบหรือไม่อยู่ในสถานะรอดำเนินการ");

$approver = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
$now = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE stock_requests SET status='rejected', deny_reason=?, approved_by=?, approved_at=? WHERE id=?");
if (!$upd) throw new Exception("Prepare update failed: " . $conn->error);
$upd->bind_param("sssi", $deny_reason, $approver, $now, $request_id);
$upd->execute();

// Fetch items for notification
$items_stmt = $conn->prepare("SELECT sri.qty_requested, si.name, si.unit FROM stock_request_items sri JOIN stock_items si ON si.id = sri.item_id WHERE sri.request_id = ?");
$items_stmt->bind_param("i", $request_id);
$items_stmt->execute();
$denyItems = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemLines = array_map(fn($i) => "• {$i['name']}  ×{$i['qty_requested']} {$i['unit']}", $denyItems);
$itemListStr = implode("\n", $itemLines) ?: '(ไม่มีรายการ)';

// Telegram notification
$typeLabel = ($req['stock_type'] === 'stationery') ? '📎 Stationery' : '🧹 Maid Stock';
$dept = $req['department'] ?? '';
$reasonLine = $deny_reason ? "\n<b>📝 เหตุผล:</b> {$deny_reason}" : '';
$msg = "<b>❌ ไม่อนุมัติเบิก Stock — {$typeLabel}</b>\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>🏢 บริษัท:</b> {$req['company']}\n" .
       "<b>👤 ผู้เบิก:</b> {$req['requester_name']}" . ($dept ? " ({$dept})" : '') . "\n" .
       "<b>✖️ ปฏิเสธโดย:</b> {$approver}{$reasonLine}\n" .
       "<b>📋 รายการ (" . count($itemLines) . " รายการ):</b>\n{$itemListStr}" .
       "\n\n#StockRejected #{$request_id}";
sendNotify($conn, $msg, 'stock_reject', $req['company']);

echo json_encode(['status' => 'success']);
?>
