<?php
// api/actions/stock/stock_ready.php — จัดของเสร็จ รอผู้เบิกมารับ · สิทธิ์ stock.manage
// ผู้เบิกเห็นสถานะ "พร้อมรับ" ในหน้าเบิกของ + หน้าแรก (LINE ส่งเข้ากลุ่ม ไม่ได้ส่งถึงตัวบุคคล)
_initStockFlow($conn);
$r = stockGetRequest($conn, (int)($_POST['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบเบิก');
if ($r['status'] !== 'approved') throw new Exception('ใบนี้ไม่ได้อยู่ระหว่างจัดของ');
$co = stockCompanyScope($r['company']);
if ($co && $co !== $r['company']) throw new Exception('ใบนี้เป็นของบริษัทอื่น');
$by = stockActor();
$st = $conn->prepare("UPDATE stock_requests SET status = 'ready', ready_at = NOW(), ready_by = ? WHERE id = ? AND status = 'approved'");
$st->bind_param('si', $by, $r['id']); $st->execute();
if ($st->affected_rows < 1) throw new Exception('ใบนี้ถูกเปลี่ยนสถานะไปแล้ว');
echo json_encode(['status' => 'success']);
