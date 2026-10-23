<?php
// api/actions/stock/stock_submit.php — พนักงานส่งใบเบิก · ผู้เบิก/บริษัท/แผนก จากบัญชีที่ล็อกอิน
// เบิกเกินยอดพร้อมเบิกไม่ได้ (ของที่คนอื่นได้รับอนุมัติไปแล้วถือว่าถูกจอง)
_initStockFlow($conn);
$co = (string)($_SESSION['company'] ?? '');
if (!in_array($co, STOCK_COS, true)) throw new Exception('บัญชีนี้ไม่ได้ระบุบริษัท — ติดต่อ IT');
$type = (string)($_POST['stock_type'] ?? '');
if (!isset(STOCK_TYPE_TH[$type])) throw new Exception('ประเภทไม่ถูกต้อง');
$rows = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($rows)) throw new Exception('ข้อมูลรายการไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$want = [];
foreach ($rows as $r) { $id = (int)($r['item_id'] ?? 0); $q = (int)($r['qty'] ?? 0); if ($id > 0 && $q > 0) $want[$id] = ($want[$id] ?? 0) + $q; }
if (!$want) throw new Exception('ยังไม่ได้เลือกของ');
if (count($want) > 50) throw new Exception('ใบเดียวเบิกได้ไม่เกิน 50 รายการ');
$notes = mb_substr(trim(strip_tags((string)($_POST['notes'] ?? ''))), 0, 500);

$ids = array_keys($want);
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $conn->prepare("SELECT id, name, unit FROM stock_items WHERE id IN ($ph) AND company = ? AND type = ? AND is_active = 1");
$p = array_merge($ids, [$co, $type]);
$st->bind_param(str_repeat('i', count($ids)) . 'ss', ...$p); $st->execute();
$items = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), null, 'id');
if (count($items) !== count($ids)) throw new Exception('มีบางรายการเบิกไม่ได้แล้ว — รีเฟรชหน้าแล้วเลือกใหม่');
$bal = stockBalances($conn, $ids); $res = stockReserved($conn, $ids);
foreach ($want as $id => $q) {
    $avail = max(0, $bal[$id] - $res[$id]);
    if ($q > $avail) throw new Exception("{$items[$id]['name']} เหลือให้เบิก {$avail} {$items[$id]['unit']}");
}

$uid = (string)$_SESSION['user_id'];
$name = $_SESSION['display_name'] ?? $_SESSION['username'] ?? '';
$dept = (string)($_SESSION['primary_department'] ?? '');
$conn->begin_transaction();
try {
    $ins = $conn->prepare("INSERT INTO stock_requests (company, stock_type, requester_id, requester_name, department, notes, status) VALUES (?,?,?,?,?,?,'pending')");
    $ins->bind_param('ssssss', $co, $type, $uid, $name, $dept, $notes); $ins->execute();
    $rid = $conn->insert_id;
    $ii = $conn->prepare("INSERT INTO stock_request_items (request_id, item_id, qty_requested) VALUES (?,?,?)");
    foreach ($want as $id => $q) { $ii->bind_param('iii', $rid, $id, $q); $ii->execute(); }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }

$r = stockGetRequest($conn, $rid);
$b = $dept !== '' ? stockDeptBudget($conn, $co, $dept, $type) : ['limit' => 0, 'used' => 0];
$val = array_sum(array_map(fn($i) => $i['qty_requested'] * $i['price_per_unit'], $r['items']));
$over = $b['limit'] > 0 && $b['used'] + $val > $b['limit'];
stockNotify($conn, 'stock_new', '📦 ใบเบิกใหม่', $r, ['หมายเหตุ' => $notes, 'วงเงินแผนก' => $over ? '⚠️ เกินวงเงินเดือนนี้' : null]);
echo json_encode(['status' => 'success', 'id' => $rid, 'code' => stockCode($rid)]);
