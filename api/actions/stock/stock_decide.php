<?php
// api/actions/stock/stock_decide.php — อนุมัติ (ปรับจำนวนได้) / ไม่อนุมัติ · สิทธิ์ stock.manage
// issue_now=1 + ลายเซ็น = ผู้เบิกยืนอยู่ตรงหน้า → อนุมัติและจ่ายเลยในครั้งเดียว
_initStockFlow($conn);
$r = stockGetRequest($conn, (int)($_POST['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบเบิก');
if ($r['status'] !== 'pending') throw new Exception('ใบนี้ถูกจัดการไปแล้ว');
if (!inScope($conn, $r['company'])) throw new Exception('ใบนี้เป็นของบริษัทที่คุณไม่ได้ดูแล');
$ok = ($_POST['decision'] ?? '') === 'approve';
$by = stockActor();

if (!$ok) {
    $reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500);
    if ($reason === '') throw new Exception('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
    $st = $conn->prepare("UPDATE stock_requests SET status = 'rejected', deny_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
    $st->bind_param('ssi', $reason, $by, $r['id']); $st->execute();
    if ($st->affected_rows < 1) throw new Exception('ใบนี้ถูกจัดการไปแล้ว');
    stockNotify($conn, 'stock_reject', '🚫 ไม่อนุมัติใบเบิก', $r, ['โดย' => $by, 'เหตุผล' => $reason]);
    echo json_encode(['status' => 'success']);
    return;
}

// จำนวนที่ให้: {row_id: qty} ไม่ส่งมา = ให้ตามที่ขอ · ไม่เกินที่ขอ และไม่เกินพร้อมเบิก
$qty = json_decode((string)($_POST['qty'] ?? '{}'), true);
if (!is_array($qty)) throw new Exception('ข้อมูลจำนวนไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$ids = array_column($r['items'], 'item_id');
$bal = stockBalances($conn, $ids); $res = stockReserved($conn, $ids, $r['id']);
$give = 0;
foreach ($r['items'] as &$i) {
    $q = array_key_exists((string)$i['row_id'], $qty) ? (int)$qty[(string)$i['row_id']] : (int)$i['qty_requested'];
    $q = max(0, min($q, (int)$i['qty_requested']));
    $avail = max(0, $bal[(int)$i['item_id']] - $res[(int)$i['item_id']]);
    if ($q > $avail) throw new Exception("{$i['name']} พร้อมเบิก {$avail} {$i['unit']} — ลดจำนวนที่ให้");
    $i['qty_approved'] = $q; $give += $q;
}
unset($i);
if ($give === 0) throw new Exception('ทุกรายการเป็น 0 — ถ้าไม่ให้เลย ให้กด "ไม่อนุมัติ" พร้อมเหตุผล');
$now = !empty($_POST['issue_now']);
$sig = '';
if ($now) { $sig = stockSaveSignature($_POST['signature'] ?? ''); if ($sig === '') throw new Exception('ให้ผู้รับเซ็นรับของก่อน'); }

$conn->begin_transaction();
try {
    $u = $conn->prepare("UPDATE stock_request_items SET qty_approved = ? WHERE id = ?");
    foreach ($r['items'] as $i) { $u->bind_param('ii', $i['qty_approved'], $i['row_id']); $u->execute(); }
    $st = $conn->prepare("UPDATE stock_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
    $st->bind_param('si', $by, $r['id']); $st->execute();
    if ($st->affected_rows < 1) throw new Exception('ใบนี้ถูกจัดการไปแล้ว');
    $r['status'] = 'approved';
    if ($now) { stockDoIssue($conn, $r, $sig); $r['status'] = 'issued'; }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }

$cut = array_filter($r['items'], fn($i) => (int)$i['qty_approved'] < (int)$i['qty_requested']);
stockNotify($conn, 'stock_approve', $now ? '✅ อนุมัติและจ่ายของแล้ว' : '✅ อนุมัติใบเบิก — กำลังจัดของ', $r, ['โดย' => $by, 'ปรับจำนวน' => $cut ? count($cut) . ' รายการ' : null]);
if ($now) stockCheckLow($conn, $ids, $r['company']);
echo json_encode(['status' => 'success', 'issued' => $now]);
