<?php
// api/actions/stock/stock_board.php — บอร์ดผู้ดูแล: ใบที่ยังไม่ปิด + ที่ปิดวันนี้ + ตัวเลขสรุป · สิทธิ์ stock.manage ($ACTION_PERMS)
_initStockFlow($conn);
$cos = scopeCompanies($conn, (string)($_GET['company'] ?? ''));
[$cw, $cp] = scopeSql('company', $cos);
$w = "(status IN ('pending','approved','ready') OR DATE(COALESCE(issued_at, approved_at)) = CURDATE())";
$st = $conn->prepare("SELECT id FROM stock_requests WHERE {$w}{$cw} ORDER BY request_date");
if ($cp) $st->bind_param(str_repeat('s', count($cp)), ...$cp);
$st->execute();
$rows = [];
foreach ($st->get_result()->fetch_all() as $r) $rows[] = stockRow(stockGetRequest($conn, (int)$r[0]));

// ของใกล้หมด (พร้อมเบิก ≤ จุดสั่งซื้อ) · มูลค่าที่จ่ายเดือนนี้
$st = $conn->prepare("SELECT id, min_stock FROM stock_items WHERE is_active = 1 AND min_stock > 0{$cw}");
if ($cp) $st->bind_param(str_repeat('s', count($cp)), ...$cp);
$st->execute();
$mins = $st->get_result()->fetch_all();
$ids = array_column($mins, 0);
$bal = stockBalances($conn, $ids); $res = stockReserved($conn, $ids);
$low = 0;
foreach ($mins as [$id, $min]) if ($bal[(int)$id] - $res[(int)$id] <= (int)$min) $low++;
$st = $conn->prepare("SELECT COALESCE(SUM(quantity * unit_cost), 0) FROM stock_transactions WHERE type = 'issued' AND DATE_FORMAT(transaction_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m'){$cw}");
if ($cp) $st->bind_param(str_repeat('s', count($cp)), ...$cp);
$st->execute();
$month = round((float)$st->get_result()->fetch_row()[0], 2);
echo json_encode(['status' => 'success', 'data' => $rows, 'low' => $low, 'month_value' => $month, 'companies' => userCompanies($conn), 'is_admin' => ($_SESSION['role'] ?? '') === 'admin'], JSON_UNESCAPED_UNICODE);
