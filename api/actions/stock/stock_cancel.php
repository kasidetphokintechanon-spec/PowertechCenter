<?php
// api/actions/stock/stock_cancel.php — ยกเลิกใบเบิกก่อนรับของ · เจ้าของใบ หรือผู้ดูแลสต็อก · ของที่จองไว้คืนกลับเป็นพร้อมเบิก
_initStockFlow($conn);
$r = stockGetRequest($conn, (int)($_POST['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบเบิก');
$mine = (string)$r['requester_id'] === (string)$_SESSION['user_id'];
if (!$mine && !stockCanManage($conn)) throw new Exception('ยกเลิกได้เฉพาะใบเบิกของตัวเอง');
if (!in_array($r['status'], STOCK_OPEN, true)) throw new Exception('ใบนี้ปิดไปแล้ว ยกเลิกไม่ได้');
$reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 500) ?: ($mine ? 'ผู้เบิกยกเลิกเอง' : 'ผู้ดูแลยกเลิก');
$by = stockActor();
$note = "{$reason} — {$by}";
$st = $conn->prepare("UPDATE stock_requests SET status = 'cancelled', closed_reason = ? WHERE id = ? AND status IN ('pending','approved','ready')");
$st->bind_param('si', $note, $r['id']); $st->execute();
if ($st->affected_rows < 1) throw new Exception('ใบนี้ถูกเปลี่ยนสถานะไปแล้ว');
stockNotify($conn, 'stock_cancel', '↩️ ยกเลิกใบเบิก', $r, ['โดย' => $by, 'เหตุผล' => $reason]);
echo json_encode(['status' => 'success']);
