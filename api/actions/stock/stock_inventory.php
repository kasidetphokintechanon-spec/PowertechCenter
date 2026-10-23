<?php
// api/actions/stock/stock_inventory.php — คลังสินค้า: คงเหลือ / จองไว้ / พร้อมเบิก / รับเข้าล่าสุด · สิทธิ์ stock.manage
_initStockFlow($conn);
$cos = scopeCompanies($conn, (string)($_GET['company'] ?? ''));
[$cw, $cp] = scopeSql('company', $cos);
$all = !empty($_GET['inactive']);
$st = $conn->prepare("SELECT id, item_code, name, unit, price_per_unit, min_stock, max_stock, type, company, is_active, category, description, image_url, has_expiry, pack_unit, pack_size
    FROM stock_items WHERE 1=1{$cw}" . ($all ? '' : ' AND is_active = 1') . " ORDER BY company, type, category, name");
if ($cp) $st->bind_param(str_repeat('s', count($cp)), ...$cp);
$st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$ids = array_column($items, 'id');
$bal = stockBalances($conn, $ids); $res = stockReserved($conn, $ids);
$last = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $q = $conn->prepare("SELECT item_id, MAX(transaction_date) FROM stock_transactions WHERE type = 'received' AND item_id IN ($ph) GROUP BY item_id");
    $q->bind_param(str_repeat('i', count($ids)), ...array_map('intval', $ids)); $q->execute();
    foreach ($q->get_result()->fetch_all() as $x) $last[(int)$x[0]] = $x[1];
}
foreach ($items as &$i) {
    $id = (int)$i['id'];
    foreach (['id', 'min_stock', 'max_stock', 'is_active', 'has_expiry', 'pack_size'] as $k) $i[$k] = (int)$i[$k];
    $i['price_per_unit'] = (float)$i['price_per_unit'];
    $i['balance'] = $bal[$id]; $i['reserved'] = $res[$id]; $i['available'] = max(0, $bal[$id] - $res[$id]);
    $i['last_received'] = $last[$id] ?? null;
}
unset($i);
echo json_encode(['status' => 'success', 'items' => $items, 'companies' => userCompanies($conn), 'is_admin' => ($_SESSION['role'] ?? '') === 'admin'], JSON_UNESCAPED_UNICODE);
