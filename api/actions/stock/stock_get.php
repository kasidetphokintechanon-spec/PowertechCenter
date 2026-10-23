<?php
// api/actions/stock/stock_get.php — รายละเอียดใบเบิก · เจ้าของใบดูของตัวเอง · ผู้ดูแลเห็นคงเหลือ/วงเงินแผนก/ประวัติผู้เบิกเพิ่ม
_initStockFlow($conn);
$r = stockGetRequest($conn, (int)($_GET['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบเบิก');
$mgr = stockCanManage($conn);
if (!$mgr && (string)$r['requester_id'] !== (string)$_SESSION['user_id']) throw new Exception('ไม่มีสิทธิ์ดูใบเบิกนี้');
$out = ['status' => 'success', 'request' => stockRow($r), 'can_manage' => $mgr];
if ($mgr) {
    $ids = array_column($r['items'], 'item_id');
    $bal = stockBalances($conn, $ids); $res = stockReserved($conn, $ids, $r['id']);
    foreach ($out['request']['items'] as &$i) { $i['balance'] = $bal[$i['item_id']]; $i['available'] = max(0, $bal[$i['item_id']] - $res[$i['item_id']]); }
    unset($i);
    $out['budget'] = $r['department'] ? stockDeptBudget($conn, $r['company'], $r['department'], $r['stock_type'], $r['id']) : ['limit' => 0, 'used' => 0];
    $h = $conn->prepare("SELECT id FROM stock_requests WHERE requester_id = ? AND id <> ? AND status = 'issued' ORDER BY issued_at DESC LIMIT 5");
    $h->bind_param('si', $r['requester_id'], $r['id']); $h->execute();
    $out['history'] = array_map(fn($x) => stockRow(stockGetRequest($conn, (int)$x[0])), $h->get_result()->fetch_all());
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
