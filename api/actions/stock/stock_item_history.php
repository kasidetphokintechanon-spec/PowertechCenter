<?php
// api/actions/stock/stock_item_history.php — ประวัติเข้า-ออกของสินค้า 1 รายการ (ล่าสุด 100) พร้อมยอดสะสม · สิทธิ์ stock.manage
_initStockFlow($conn);
$id = (int)($_GET['item_id'] ?? 0);
$st = $conn->prepare("SELECT id, name, unit, company FROM stock_items WHERE id = ?");
$st->bind_param('i', $id); $st->execute();
$item = $st->get_result()->fetch_assoc();
if (!$item) throw new Exception('ไม่พบสินค้า');
if (!inScope($conn, $item['company'])) throw new Exception('สินค้านี้เป็นของบริษัทที่คุณไม่ได้ดูแล');
$st = $conn->prepare("SELECT id, type, quantity, unit_cost, transaction_date, requester_name, request_id, notes, supplier, expiry_date, created_by
    FROM stock_transactions WHERE item_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 100");
$st->bind_param('i', $id); $st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$run = stockBalances($conn, [$id])[$id];
foreach ($rows as &$r) {
    $r['change'] = $r['type'] === 'issued' ? -(int)$r['quantity'] : (int)$r['quantity'];
    $r['after'] = $run; $run -= $r['change'];
    $r['request_code'] = $r['request_id'] ? stockCode((int)$r['request_id']) : null;
}
unset($r);
echo json_encode(['status' => 'success', 'item' => $item, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
