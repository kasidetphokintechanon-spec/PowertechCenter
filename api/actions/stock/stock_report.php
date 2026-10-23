<?php
// api/actions/stock/stock_report.php — รายงานประจำเดือน (ตัวเดียวแทน 3 ตัวเดิมที่คิดยอดคนละแบบ) · สิทธิ์ stock.manage
// ทุกตัวเลขมาจาก stock_transactions: ยกมา + รับเข้า − จ่ายออก ± ปรับยอด = คงเหลือ
_initStockFlow($conn);
$co = stockCompanyScope((string)($_GET['company'] ?? ''));
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? $_GET['month'] : date('Y-m');
$type = isset(STOCK_TYPE_TH[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$from = $month . '-01 00:00:00';
$to = date('Y-m-d 00:00:00', strtotime($from . ' +1 month'));

$w = 's.is_active = 1 OR EXISTS (SELECT 1 FROM stock_transactions x WHERE x.item_id = s.id AND x.transaction_date >= ?)';
$sql = "SELECT s.id, s.item_code, s.name, s.unit, s.company, s.type, s.category, s.price_per_unit,
        COALESCE(SUM(CASE WHEN t.transaction_date < ? THEN (CASE WHEN t.type = 'issued' THEN -t.quantity ELSE t.quantity END) END), 0) AS opening,
        COALESCE(SUM(CASE WHEN t.transaction_date >= ? AND t.transaction_date < ? AND t.type IN ('received','returned') THEN t.quantity END), 0) AS qty_in,
        COALESCE(SUM(CASE WHEN t.transaction_date >= ? AND t.transaction_date < ? AND t.type IN ('received','returned') THEN t.quantity * t.unit_cost END), 0) AS val_in,
        COALESCE(SUM(CASE WHEN t.transaction_date >= ? AND t.transaction_date < ? AND t.type = 'issued' THEN t.quantity END), 0) AS qty_out,
        COALESCE(SUM(CASE WHEN t.transaction_date >= ? AND t.transaction_date < ? AND t.type = 'issued' THEN t.quantity * t.unit_cost END), 0) AS val_out,
        COALESCE(SUM(CASE WHEN t.transaction_date >= ? AND t.transaction_date < ? AND t.type = 'adjusted' THEN t.quantity END), 0) AS qty_adj
    FROM stock_items s LEFT JOIN stock_transactions t ON t.item_id = s.id AND t.transaction_date < ?
    WHERE ($w)" . ($co ? ' AND s.company = ?' : '') . ($type ? ' AND s.type = ?' : '') . "
    GROUP BY s.id ORDER BY s.company, s.type, s.name";
$p = [$from, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $to, $from];
if ($co) $p[] = $co;
if ($type) $p[] = $type;
$st = $conn->prepare($sql);
$st->bind_param(str_repeat('s', count($p)), ...$p); $st->execute();
$items = [];
foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    foreach (['opening', 'qty_in', 'qty_out', 'qty_adj'] as $k) $r[$k] = (int)$r[$k];
    $r['closing'] = $r['opening'] + $r['qty_in'] - $r['qty_out'] + $r['qty_adj'];
    $r['val_in'] = round((float)$r['val_in'], 2); $r['val_out'] = round((float)$r['val_out'], 2);
    $r['val_closing'] = round($r['closing'] * (float)$r['price_per_unit'], 2);
    if ($r['opening'] || $r['qty_in'] || $r['qty_out'] || $r['qty_adj'] || $r['closing']) $items[] = $r;
}

// ตามแผนก — จากของที่จ่ายจริงในเดือน
$sql = "SELECT COALESCE(NULLIF(r.department, ''), 'ไม่ระบุแผนก') AS department, r.company, COUNT(DISTINCT r.id) AS requests,
        SUM(t.quantity * t.unit_cost) AS value
    FROM stock_transactions t JOIN stock_requests r ON r.id = t.request_id JOIN stock_items s ON s.id = t.item_id
    WHERE t.type = 'issued' AND t.transaction_date >= ? AND t.transaction_date < ?" . ($co ? ' AND t.company = ?' : '') . ($type ? ' AND s.type = ?' : '') . "
    GROUP BY 1, 2 ORDER BY value DESC";
$p = [$from, $to];
if ($co) $p[] = $co;
if ($type) $p[] = $type;
$st = $conn->prepare($sql);
$st->bind_param(str_repeat('s', count($p)), ...$p); $st->execute();
$depts = array_map(fn($r) => ['department' => $r['department'], 'company' => $r['company'], 'requests' => (int)$r['requests'], 'value' => round((float)$r['value'], 2)], $st->get_result()->fetch_all(MYSQLI_ASSOC));

// จำนวนใบเบิกตามสถานะในเดือน
$sql = "SELECT status, COUNT(*) FROM stock_requests WHERE request_date >= ? AND request_date < ?" . ($co ? ' AND company = ?' : '') . ($type ? ' AND stock_type = ?' : '') . " GROUP BY status";
$p = [$from, $to];
if ($co) $p[] = $co;
if ($type) $p[] = $type;
$st = $conn->prepare($sql);
$st->bind_param(str_repeat('s', count($p)), ...$p); $st->execute();
$reqs = [];
foreach ($st->get_result()->fetch_all() as [$s, $n]) $reqs[$s] = (int)$n;

echo json_encode(['status' => 'success', 'month' => $month, 'company' => $co, 'type' => $type, 'items' => $items, 'departments' => $depts, 'requests' => $reqs,
    'totals' => ['val_in' => round(array_sum(array_column($items, 'val_in')), 2), 'val_out' => round(array_sum(array_column($items, 'val_out')), 2), 'val_closing' => round(array_sum(array_column($items, 'val_closing')), 2)]], JSON_UNESCAPED_UNICODE);
