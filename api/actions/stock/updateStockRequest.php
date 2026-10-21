<?php
// api/actions/stock/updateStockRequest.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$user_id = (string)$_SESSION['user_id'];

$request_id = intval($_POST['request_id'] ?? 0);
$cancel     = ($_POST['cancel'] ?? '0') === '1';
if (!$request_id) throw new Exception("ไม่มี request_id");

// Verify ownership and pending status (column is requester_id, not user_id)
$chk = $conn->prepare("SELECT id FROM stock_requests WHERE id=? AND requester_id=? AND status='pending'");
$chk->bind_param("is", $request_id, $user_id);
$chk->execute();
if (!$chk->get_result()->fetch_assoc()) throw new Exception("ไม่พบคำขอ หรือไม่มีสิทธิ์แก้ไข");
$chk->close();

if ($cancel) {
    // Fetch request + items BEFORE delete (for notification)
    $req_stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id=? AND status='pending'");
    $req_stmt->bind_param("i", $request_id);
    $req_stmt->execute();
    $req = $req_stmt->get_result()->fetch_assoc();
    $req_stmt->close();

    $items_stmt = $conn->prepare("SELECT sri.qty_requested, si.name, si.unit FROM stock_request_items sri JOIN stock_items si ON si.id = sri.item_id WHERE sri.request_id=?");
    $items_stmt->bind_param("i", $request_id);
    $items_stmt->execute();
    $cancelItems = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $items_stmt->close();

    // Hard-delete pending request
    $conn->begin_transaction();
    try {
        $del_items = $conn->prepare("DELETE FROM stock_request_items WHERE request_id=?");
        $del_items->bind_param("i", $request_id);
        $del_items->execute();
        $del_items->close();

        $del_req = $conn->prepare("DELETE FROM stock_requests WHERE id=? AND status='pending'");
        $del_req->bind_param("i", $request_id);
        $del_req->execute();
        $del_req->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

    // Notification
    if ($req) {
        require_once __DIR__ . '/../../../includes/notify_helper.php';
        $typeLabel    = ($req['stock_type'] === 'stationery') ? '📎 Stationery' : '🧹 Maid Stock';
        $dept         = $req['department'] ?? '';
        $canceller    = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้';
        $cancel_reason = trim($_POST['cancel_reason'] ?? '');
        $itemLines    = array_map(fn($i) => "• {$i['name']}  ×{$i['qty_requested']} {$i['unit']}", $cancelItems);
        $itemListStr  = implode("\n", $itemLines) ?: '(ไม่มีรายการ)';
        $msg = "<b>🚫 ยกเลิกคำขอเบิก Stock — {$typeLabel}</b>\n" .
               "➖➖➖➖➖➖➖➖➖➖\n" .
               "<b>🏢 บริษัท:</b> {$req['company']}\n" .
               "<b>👤 ผู้เบิก:</b> {$req['requester_name']}" . ($dept ? " ({$dept})" : '') . "\n" .
               "<b>🚫 ยกเลิกโดย:</b> {$canceller}\n" .
               ($cancel_reason ? "<b>📝 เหตุผล:</b> {$cancel_reason}\n" : '') .
               "<b>📋 รายการ (" . count($itemLines) . " รายการ):</b>\n{$itemListStr}" .
               "\n\n#StockCancelled #{$request_id}";
        sendNotify($conn, $msg, 'stock_cancel', $req['company']);
    }

    echo json_encode(['status' => 'success', 'action' => 'cancelled']);
    exit;
}

// Update items
$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items) || !count($items)) throw new Exception("ไม่มีรายการสินค้า");
$notes = trim($_POST['notes'] ?? '');

$conn->begin_transaction();
try {
    // Remove old items
    $del = $conn->prepare("DELETE FROM stock_request_items WHERE request_id=?");
    $del->bind_param("i", $request_id);
    $del->execute();
    $del->close();

    // Insert new items
    $ins = $conn->prepare("INSERT INTO stock_request_items (request_id, item_id, qty_requested) VALUES (?,?,?)");
    foreach ($items as $it) {
        $item_id = intval($it['item_id']);
        $qty     = max(1, intval($it['qty']));
        $ins->bind_param("iii", $request_id, $item_id, $qty);
        $ins->execute();
    }
    $ins->close();

    // Update notes only (no updated_at — column may not exist)
    $upd = $conn->prepare("UPDATE stock_requests SET notes=? WHERE id=?");
    $upd->bind_param("si", $notes, $request_id);
    $upd->execute();
    $upd->close();

    $conn->commit();
    echo json_encode(['status' => 'success', 'action' => 'updated']);
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
?>
