<?php
// api/actions/stock/stock_catalog.php — ของที่เบิกได้ของบริษัทตัวเอง + ยอดพร้อมเบิก + วงเงินแผนกเดือนนี้
// บริษัทมาจากบัญชีที่ล็อกอินเท่านั้น (เดิมรับจากหน้าเว็บ → เบิกของบริษัทอื่นได้)
_initStockFlow($conn);
$co = (string)($_SESSION['company'] ?? '');
if (!in_array($co, STOCK_COS, true)) throw new Exception('บัญชีนี้ไม่ได้ระบุบริษัท — ติดต่อ IT');
$st = $conn->prepare("SELECT id, item_code, name, unit, price_per_unit, type, category, description, image_url, pack_unit, pack_size, min_stock
    FROM stock_items WHERE company = ? AND is_active = 1 ORDER BY type, category, name");
$st->bind_param('s', $co); $st->execute();
$items = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$ids = array_column($items, 'id');
$bal = stockBalances($conn, $ids);
$res = stockReserved($conn, $ids);
foreach ($items as &$i) {
    $id = (int)$i['id'];
    $i['id'] = $id; $i['pack_size'] = (int)$i['pack_size']; $i['min_stock'] = (int)$i['min_stock']; $i['price_per_unit'] = (float)$i['price_per_unit'];
    $i['available'] = max(0, $bal[$id] - $res[$id]);
}
unset($i);
$dept = (string)($_SESSION['primary_department'] ?? '');
$budget = [];
foreach (array_keys(STOCK_TYPE_TH) as $t) $budget[$t] = $dept !== '' ? stockDeptBudget($conn, $co, $dept, $t) : ['limit' => 0, 'used' => 0];
echo json_encode(['status' => 'success', 'company' => $co, 'department' => $dept, 'items' => $items, 'budget' => $budget], JSON_UNESCAPED_UNICODE);
