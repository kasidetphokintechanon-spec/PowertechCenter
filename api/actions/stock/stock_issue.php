<?php
// api/actions/stock/stock_issue.php — จ่ายของ (ผู้เบิกเซ็นรับ) → ตัดสต็อกตอนนี้ · สิทธิ์ stock.manage
_initStockFlow($conn);
$r = stockGetRequest($conn, (int)($_POST['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบเบิก');
if (!in_array($r['status'], ['approved', 'ready'], true)) throw new Exception('จ่ายได้เฉพาะใบที่อนุมัติแล้ว');
$co = stockCompanyScope($r['company']);
if ($co && $co !== $r['company']) throw new Exception('ใบนี้เป็นของบริษัทอื่น');
$sig = stockSaveSignature($_POST['signature'] ?? '');
if ($sig === '') throw new Exception('ให้ผู้รับเซ็นรับของก่อน');
$conn->begin_transaction();
try { stockDoIssue($conn, $r, $sig); $conn->commit(); }
catch (Throwable $e) { $conn->rollback(); throw $e; }
stockCheckLow($conn, array_column($r['items'], 'item_id'), $r['company']);
echo json_encode(['status' => 'success']);
