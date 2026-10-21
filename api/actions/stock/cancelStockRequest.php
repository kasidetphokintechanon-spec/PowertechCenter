<?php
// api/actions/stock/cancelStockRequest.php — user cancels own pending request
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$request_id = intval($_POST['request_id'] ?? 0);
if ($request_id <= 0) throw new Exception("request_id ไม่ถูกต้อง");

$uid  = (string)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';

// Fetch request — รองรับทั้ง pending และ approved
$req_stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id = ? AND status IN ('pending','approved')");
$req_stmt->bind_param("i", $request_id);
$req_stmt->execute();
$req = $req_stmt->get_result()->fetch_assoc();
if (!$req) throw new Exception("คำขอไม่พบหรือไม่อยู่ในสถานะที่ยกเลิกได้");

// Permission check: admin/stock manager สามารถยกเลิก approved ได้ / user ยกเลิกได้เฉพาะ pending ของตัวเอง
$isManager = in_array($role, ['admin','staff']) || !empty($_SESSION['can_manage_stock']);
$isOwner   = (string)$req['requester_id'] === $uid;

if ($req['status'] === 'approved' && !$isManager) {
    throw new Exception("ไม่มีสิทธิ์ยกเลิกคำขอที่อนุมัติแล้ว");
}
if (!$isManager && !$isOwner) {
    throw new Exception("ไม่มีสิทธิ์ยกเลิกคำขอนี้");
}

$items_stmt = $conn->prepare("SELECT sri.item_id, sri.qty_requested, sri.qty_approved, si.name, si.unit FROM stock_request_items sri JOIN stock_items si ON si.id = sri.item_id WHERE sri.request_id = ?");
$items_stmt->bind_param("i", $request_id);
$items_stmt->execute();
$cancelItems = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->begin_transaction();
try {
    // ถ้า approved → คืน stock ก่อน
    if ($req['status'] === 'approved') {
        $canceller  = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้';
        $now = date('Y-m-d H:i:s');
        $ins_return = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, notes, created_by, transaction_date) VALUES (?,?,'returned',?,?,?,?)");
        foreach ($cancelItems as $item) {
            $qty_back = intval($item['qty_approved'] ?? 0);
            if ($qty_back <= 0) continue;
            $note = "คืน stock จากการยกเลิก request #{$request_id} (อนุมัติไว้ {$qty_back} {$item['unit']})";
            $ins_return->bind_param("isissss", $item['item_id'], $req['company'], $qty_back, $note, $canceller, $now);
            $ins_return->execute();
        }
    }

    // Delete items first, then request
    $del_items = $conn->prepare("DELETE FROM stock_request_items WHERE request_id = ?");
    $del_items->bind_param("i", $request_id);
    $del_items->execute();

    $del_req = $conn->prepare("DELETE FROM stock_requests WHERE id = ? AND status IN ('pending','approved')");
    $del_req->bind_param("i", $request_id);
    $del_req->execute();
    if ($del_req->affected_rows === 0) throw new Exception("ยกเลิกไม่ได้");

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

// Notification
require_once __DIR__ . '/../../../includes/notify_helper.php';
$typeLabel  = ($req['stock_type'] === 'stationery') ? '📎 Stationery' : '🧹 Maid Stock';
$dept       = $req['department'] ?? '';
$canceller  = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้';
$itemLines  = array_map(fn($i) => "• {$i['name']}  ×{$i['qty_requested']} {$i['unit']}", $cancelItems);
$itemListStr = implode("\n", $itemLines) ?: '(ไม่มีรายการ)';
$msg = "<b>🚫 ยกเลิกคำขอเบิก Stock — {$typeLabel}</b>\n" .
       "➖➖➖➖➖➖➖➖➖➖\n" .
       "<b>🏢 บริษัท:</b> {$req['company']}\n" .
       "<b>👤 ผู้เบิก:</b> {$req['requester_name']}" . ($dept ? " ({$dept})" : '') . "\n" .
       "<b>🚫 ยกเลิกโดย:</b> {$canceller}\n" .
       "<b>📋 รายการ (" . count($itemLines) . " รายการ):</b>\n{$itemListStr}" .
       "\n\n#StockCancelled #{$request_id}";
sendNotify($conn, $msg, 'stock_cancel', $req['company']);

echo json_encode(['status' => 'success']);
?>
