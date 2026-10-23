<?php
// api/actions/stock/stock_my.php — ใบเบิกของฉัน (ล่าสุด 60 ใบ)
_initStockFlow($conn);
$uid = (string)$_SESSION['user_id'];
$st = $conn->prepare("SELECT id FROM stock_requests WHERE requester_id = ? ORDER BY FIELD(status,'pending','approved','ready') DESC, id DESC LIMIT 60");
$st->bind_param('s', $uid); $st->execute();
$rows = [];
foreach ($st->get_result()->fetch_all() as $r) $rows[] = stockRow(stockGetRequest($conn, (int)$r[0]));
echo json_encode(['status' => 'success', 'data' => $rows], JSON_UNESCAPED_UNICODE);
