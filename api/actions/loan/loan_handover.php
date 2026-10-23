<?php
// api/actions/loan/loan_handover.php — ส่งมอบของ (approved → active) · เช็กของในชุด + ลายเซ็นผู้รับ · สิทธิ์ loan.manage
// อัปเดต it_assets ให้หน้าอื่นเห็นว่าเครื่องถูกยืม + เขียน it_loan_history (รายงาน/พาสปอร์ตเครื่องเดิมใช้ตารางนี้)
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l) throw new Exception('ไม่พบคำขอ');
if (!inScope($conn, $l['asset_company'])) throw new Exception('คำขอนี้เป็นของบริษัทที่คุณไม่ได้ดูแล');
if ($l['status'] !== 'approved') throw new Exception('ส่งมอบได้เฉพาะคำขอที่อนุมัติแล้ว');
$st = $conn->prepare("SELECT status FROM it_assets WHERE id = ?"); $st->bind_param('i', $l['asset_id']); $st->execute();
$aSt = $st->get_result()->fetch_row()[0] ?? '';
if ($aSt === 'ถูกยืม') throw new Exception('เครื่องนี้ยังอยู่กับผู้ยืมคนก่อน — รับคืนก่อน');
// รายการของในชุดต้องอ่านได้ — อ่านไม่ออกแล้วเงียบ จะกลายเป็นบันทึกว่า "ไม่มีของ" ผิดความจริง
$items = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($items)) throw new Exception('ข้อมูลรายการของในชุดไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$sig = loanSaveSignature($_POST['signature'] ?? '', 'loan-out');
if ($sig === '') throw new Exception('กรุณาให้ผู้รับเซ็นรับของ');
$by = loanActor(); $itemsJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);

$conn->begin_transaction();
try {
    $u = $conn->prepare("UPDATE it_loan_requests SET status = 'active', handover_by = ?, handover_at = NOW(), handover_items = ?, handover_sig = ?, start_date = LEAST(start_date, CURDATE()) WHERE id = ? AND status = 'approved'");
    $u->bind_param('sssi', $by, $itemsJson, $sig, $l['id']); $u->execute();
    if ($u->affected_rows < 1) throw new Exception('คำขอนี้ถูกเปลี่ยนสถานะไปแล้ว');
    $a = $conn->prepare("UPDATE it_assets SET status = 'ถูกยืม', borrower = ?, borrower_id = ?, department = ?, return_date = ?, purpose = ?, checkout_time = NOW() WHERE id = ?");
    $a->bind_param('sssssi', $l['borrower_name'], $l['borrower_id'], $l['department'], $l['end_date'], $l['purpose'], $l['asset_id']); $a->execute();
    $h = $conn->prepare("INSERT INTO it_loan_history (equipment_id, equipment_name, borrower, department, borrower_id, purpose, expected_return_date, approver_name, lend_date, borrower_signature, condition_notes) VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)");
    $note = $l['code'] . ' · ของในชุด: ' . (implode(', ', array_map(fn($i) => ($i['name'] ?? '') . (empty($i['ok']) ? ' (ไม่มี)' : ''), $items)) ?: '-');
    $h->bind_param('ssssssssss', $l['equipment_id'], $l['equipment_name'], $l['borrower_name'], $l['department'], $l['borrower_id'], $l['purpose'], $l['end_date'], $by, $sig, $note); $h->execute();
    $hid = $conn->insert_id;
    $x = $conn->prepare("UPDATE it_loan_requests SET history_id = ? WHERE id = ?"); $x->bind_param('ii', $hid, $l['id']); $x->execute();
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
logAssetHistory($conn, (int)$l['asset_id'], 'LOAN_HANDOVER', "{$l['code']} ส่งมอบให้ {$l['borrower_name']} โดย {$by}");
echo json_encode(['status' => 'success']);
