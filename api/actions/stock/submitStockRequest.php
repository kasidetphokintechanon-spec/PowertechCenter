<?php
// api/actions/stock/submitStockRequest.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");

$company    = trim($_POST['company']    ?? ($_SESSION['company'] ?? ''));
$stock_type = trim($_POST['stock_type'] ?? '');
$branch     = trim($_POST['branch']     ?? '');
$notes      = trim($_POST['notes']      ?? '');
$items_json = $_POST['items']           ?? '[]';
$items      = json_decode($items_json, true);

if (!$company || !$stock_type) throw new Exception("company และ stock_type จำเป็นต้องระบุ");
if (!in_array($stock_type, ['stationery','maid'])) throw new Exception("ประเภท stock ไม่ถูกต้อง");
if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
// branch selection removed — no longer required for PTA
if (!is_array($items) || count($items) === 0) throw new Exception("กรุณาเลือกรายการอย่างน้อย 1 รายการ");

// Override requester info from session
$requester_id   = $_SESSION['user_id'];
$requester_name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$department     = $_SESSION['primary_department'] ?? '';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO stock_requests (company, stock_type, branch, requester_id, requester_name, department, notes) VALUES (?,?,?,?,?,?,?)");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $b = ($company === 'PTA') ? $branch : null;
    $stmt->bind_param("sssssss", $company, $stock_type, $b, $requester_id, $requester_name, $department, $notes);
    $stmt->execute();
    $request_id = $conn->insert_id;

    $stmt_item = $conn->prepare("INSERT INTO stock_request_items (request_id, item_id, qty_requested) VALUES (?,?,?)");
    if (!$stmt_item) throw new Exception("Prepare failed items: " . $conn->error);

    foreach ($items as $item) {
        $item_id = intval($item['item_id'] ?? 0);
        $qty     = intval($item['qty']     ?? 0);
        if ($item_id <= 0 || $qty <= 0) continue;
        $stmt_item->bind_param("iii", $request_id, $item_id, $qty);
        $stmt_item->execute();
    }

    $conn->commit();

    // Telegram notification
    $typeLabel = ($stock_type === 'stationery') ? '📎 Stationery' : '🧹 Maid Stock';
    $branchLine = ($company === 'PTA' && $b) ? "\n<b>🏢 สาขา:</b> {$b}" : '';

    // Fetch item names for notification
    $validItems = array_filter($items, fn($i) => intval($i['item_id'] ?? 0) > 0 && intval($i['qty'] ?? 0) > 0);
    $itemLines = [];
    if (!empty($validItems)) {
        $itemIds = array_values(array_map(fn($i) => intval($i['item_id']), $validItems));
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $nameStmt = $conn->prepare("SELECT id, name, unit FROM stock_items WHERE id IN ({$placeholders})");
        $nameStmt->bind_param(str_repeat('i', count($itemIds)), ...$itemIds);
        $nameStmt->execute();
        $nameMap = array_column($nameStmt->get_result()->fetch_all(MYSQLI_ASSOC), null, 'id');
        foreach ($validItems as $item) {
            $iid  = intval($item['item_id']);
            $qty  = intval($item['qty']);
            $name = $nameMap[$iid]['name'] ?? "รายการ #{$iid}";
            $unit = $nameMap[$iid]['unit'] ?? '';
            $itemLines[] = "• {$name}  ×{$qty} {$unit}";
        }
    }
    $itemListStr = implode("\n", $itemLines) ?: '(ไม่มีรายการ)';

    $msg = "<b>🆕 คำขอเบิก Stock — {$typeLabel}</b>\n" .
           "➖➖➖➖➖➖➖➖➖➖\n" .
           "<b>🏢 บริษัท:</b> {$company}{$branchLine}\n" .
           "<b>👤 ผู้เบิก:</b> {$requester_name}\n" .
           "<b>🏬 แผนก:</b> {$department}\n" .
           "<b>📋 รายการที่ขอ (" . count($itemLines) . " รายการ):</b>\n{$itemListStr}" .
           ($notes ? "\n<b>📝 หมายเหตุ:</b> {$notes}" : '') .
           "\n\n#StockRequest #{$request_id}";
    sendNotify($conn, $msg, 'stock_new', $company);

    echo json_encode(['status' => 'success', 'request_id' => $request_id]);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
?>
