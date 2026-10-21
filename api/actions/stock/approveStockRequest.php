<?php
// api/actions/stock/approveStockRequest.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$request_id  = intval($_POST['request_id'] ?? 0);
$qty_overrides_json = $_POST['qty_overrides'] ?? '{}'; // {item_id: qty_approved}
$qty_overrides = json_decode($qty_overrides_json, true) ?? [];

if ($request_id <= 0) throw new Exception("request_id ไม่ถูกต้อง");

$conn->begin_transaction();
// ── Lock row ก่อน เพื่อป้องกัน 2 admin approve พร้อมกัน (race condition) ──
$req_stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
if (!$req_stmt) throw new Exception("Prepare failed: " . $conn->error);
$req_stmt->bind_param("i", $request_id);
$req_stmt->execute();
$req = $req_stmt->get_result()->fetch_assoc();
if (!$req) { $conn->rollback(); throw new Exception("คำขอไม่พบหรือไม่อยู่ในสถานะรอดำเนินการ"); }

// Fetch request items
$items_stmt = $conn->prepare("SELECT sri.*, si.name, si.unit FROM stock_request_items sri JOIN stock_items si ON si.id = sri.item_id WHERE sri.request_id = ?");
if (!$items_stmt) throw new Exception("Prepare items failed: " . $conn->error);
$items_stmt->bind_param("i", $request_id);
$items_stmt->execute();
$items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
try {
    $approver = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
    $now = date('Y-m-d H:i:s');

    // Update request status
    $upd = $conn->prepare("UPDATE stock_requests SET status='approved', approved_by=?, approved_at=? WHERE id=?");
    if (!$upd) throw new Exception("Prepare update failed: " . $conn->error);
    $upd->bind_param("ssi", $approver, $now, $request_id);
    $upd->execute();

    // Record issued transactions + update qty_approved per item
    $upd_item  = $conn->prepare("UPDATE stock_request_items SET qty_approved=? WHERE id=?");
    $ins_tx    = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, branch, requester_name, requester_id, request_id, notes, created_by) VALUES (?,?,'issued',?,?,?,?,?,?,?,?)");
    $get_price = $conn->prepare("SELECT price_per_unit FROM stock_items WHERE id = ?");

    $bal_stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN type='received' THEN quantity WHEN type='issued' THEN -quantity WHEN type='adjusted' THEN quantity WHEN type='returned' THEN quantity END),0) AS bal FROM stock_transactions WHERE item_id=? AND company=?");

    $approvedLines = [];
    foreach ($items as $item) {
        $approved_qty = isset($qty_overrides[$item['item_id']]) ? intval($qty_overrides[$item['item_id']]) : intval($item['qty_requested']);
        if ($approved_qty < 0) $approved_qty = 0;

        // ── ตรวจสอบยอดคงเหลือก่อน issue — ป้องกัน stock ติดลบ ──
        if ($approved_qty > 0) {
            $bal_stmt->bind_param("is", $item['item_id'], $req['company']);
            $bal_stmt->execute();
            $current_bal = intval($bal_stmt->get_result()->fetch_assoc()['bal'] ?? 0);
            if ($approved_qty > $current_bal) {
                $approved_qty = $current_bal; // cap ไม่ให้เกินของที่มี
            }
        }

        $upd_item->bind_param("ii", $approved_qty, $item['id']);
        $upd_item->execute();

        if ($approved_qty > 0) {
            // Fetch current weighted-average price for COGS tracking
            $get_price->bind_param("i", $item['item_id']);
            $get_price->execute();
            $pr = $get_price->get_result()->fetch_assoc();
            $unit_cost_issued = $pr ? floatval($pr['price_per_unit']) : 0.0;

            $note = "Approved request #{$request_id}";
            $ins_tx->bind_param("isidsssiss", $item['item_id'], $req['company'], $approved_qty, $unit_cost_issued, $req['branch'], $req['requester_name'], $req['requester_id'], $request_id, $note, $approver);
            $ins_tx->execute();

            $adjNote = ($approved_qty != intval($item['qty_requested'])) ? " (ขอ {$item['qty_requested']})" : '';
            $approvedLines[] = "• {$item['name']}  ×{$approved_qty} {$item['unit']}{$adjNote}";
        } else {
            $approvedLines[] = "• {$item['name']}  ✖ ไม่อนุมัติ";
        }
    }

    $conn->commit();

    // Telegram: approval notification
    $typeLabel = ($req['stock_type'] === 'stationery') ? '📎 Stationery' : '🧹 Maid Stock';
    $dept = $req['department'] ?? '';
    $itemListStr = implode("\n", $approvedLines) ?: '(ไม่มีรายการ)';
    $msg = "<b>✅ อนุมัติเบิก Stock — {$typeLabel}</b>\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "<b>🏢 บริษัท:</b> {$req['company']}\n" .
           "<b>👤 ผู้เบิก:</b> {$req['requester_name']}" . ($dept ? " ({$dept})" : '') . "\n" .
           "<b>✔️ อนุมัติโดย:</b> {$approver}\n" .
           "<b>📦 รายการที่อนุมัติ (" . count($approvedLines) . " รายการ):</b>\n{$itemListStr}" .
           "\n\n#StockApproved #{$request_id}";
    sendNotify($conn, $msg, 'stock_approve', $req['company']);

    // Auto low-stock check: find items that dropped to/below min after this issue
    $issuedIds = array_column($items, 'item_id');
    if (!empty($issuedIds)) {
        $placeholders = implode(',', array_fill(0, count($issuedIds), '?'));
        $lowSql = "SELECT si.name, si.unit, si.min_stock,
                       COALESCE(SUM(CASE
                           WHEN st.type='received' THEN st.quantity
                           WHEN st.type='issued'   THEN -st.quantity
                           WHEN st.type='adjusted' THEN st.quantity
                           WHEN st.type='returned' THEN st.quantity
                       END), 0) AS current_balance
                   FROM stock_items si
                   LEFT JOIN stock_transactions st ON st.item_id = si.id AND st.company = ?
                   WHERE si.id IN ({$placeholders}) AND si.min_stock > 0
                   GROUP BY si.id
                   HAVING current_balance <= si.min_stock";
        $lowStmt = $conn->prepare($lowSql);
        if ($lowStmt) {
            $bindTypes = 's' . str_repeat('i', count($issuedIds));
            $bindParams = array_merge([$req['company']], $issuedIds);
            $lowStmt->bind_param($bindTypes, ...$bindParams);
            $lowStmt->execute();
            $lowItems = $lowStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            if (!empty($lowItems)) {
                $alertLines = array_map(function($r) {
                    $bal = intval($r['current_balance']);
                    $icon = $bal <= 0 ? '🔴' : '🟡';
                    return "{$icon} {$r['name']} — คงเหลือ <b>{$bal}</b> {$r['unit']} / ขั้นต่ำ {$r['min_stock']}";
                }, $lowItems);
                $alertMsg = "<b>⚠️ แจ้งเตือน Stock ต่ำ (หลังอนุมัติ #{$request_id})</b>\n" .
                            "➖➖➖➖➖➖➖➖➖➖\n" .
                            implode("\n", $alertLines) . "\n#LowStockAlert";
                sendNotify($conn, $alertMsg, 'stock_low', $req['company']);
            }
        }
    }

    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
?>
