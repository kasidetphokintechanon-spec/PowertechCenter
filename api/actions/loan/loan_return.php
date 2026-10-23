<?php
// api/actions/loan/loan_return.php — รับคืน (active → returned) · สิทธิ์ loan.manage
// TISAX: ต้องยืนยัน "ล้างข้อมูลผู้ยืมแล้ว" ก่อนเครื่องกลับเข้าคลัง · เสีย → เครื่องเป็น "ชำรุด" (ส่งซ่อมต่อจากหน้า)
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l) throw new Exception('ไม่พบคำขอ');
if (!inScope($conn, $l['asset_company'])) throw new Exception('คำขอนี้เป็นของบริษัทที่คุณไม่ได้ดูแล');
if ($l['status'] !== 'active') throw new Exception('รับคืนได้เฉพาะของที่กำลังยืม');
$cond = (string)($_POST['condition'] ?? '');
if (!in_array($cond, ['ok', 'minor', 'broken'], true)) throw new Exception('กรุณาเลือกสภาพเครื่อง');
if (empty($_POST['wiped'])) throw new Exception('ต้องล้างข้อมูลผู้ยืมก่อนรับคืน (TISAX)');
// รายการของในชุดต้องอ่านได้ — อ่านไม่ออกแล้วเงียบ จะกลายเป็นบันทึกว่า "ไม่มีของ" ผิดความจริง
$items = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($items)) throw new Exception('ข้อมูลรายการของในชุดไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$notes = trim(strip_tags((string)($_POST['notes'] ?? '')));
$missing = array_values(array_filter(array_map(fn($i) => empty($i['ok']) ? ($i['name'] ?? '') : '', $items)));
if ($missing && $notes === '') throw new Exception('ของขาด: ' . implode(', ', $missing) . ' — กรุณาบันทึกหมายเหตุ');
$sig = loanSaveSignature($_POST['signature'] ?? '', 'loan-in');
if ($sig === '') throw new Exception('กรุณาให้ผู้คืนเซ็น');
$condTh = ['ok' => 'ปกติ', 'minor' => 'มีรอย / ชำรุดเล็กน้อย', 'broken' => 'เสีย ต้องซ่อม'][$cond];
$by = loanActor(); $itemsJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE);
$fullNotes = trim(($missing ? 'ของขาด: ' . implode(', ', $missing) . ' · ' : '') . $notes, ' ·');

$conn->begin_transaction();
try {
    $u = $conn->prepare("UPDATE it_loan_requests SET status = 'returned', return_by = ?, return_at = NOW(), return_items = ?, return_condition = ?, return_notes = ?, return_sig = ?, wiped = 1, extend_status = IF(extend_status = 'pending', 'rejected', extend_status) WHERE id = ? AND status = 'active'");
    $u->bind_param('sssssi', $by, $itemsJson, $cond, $fullNotes, $sig, $l['id']); $u->execute();
    if ($u->affected_rows < 1) throw new Exception('คำขอนี้ถูกเปลี่ยนสถานะไปแล้ว');
    $newSt = $cond === 'broken' ? 'ชำรุด' : 'ใช้งานปกติ';
    $a = $conn->prepare("UPDATE it_assets SET status = ?, borrower = NULL, borrower_id = NULL, department = NULL, return_date = NULL, purpose = NULL WHERE id = ?");
    $a->bind_param('si', $newSt, $l['asset_id']); $a->execute();
    if ($l['history_id']) {
        $h = $conn->prepare("UPDATE it_loan_history SET return_date = NOW(), return_receiver_name = ?, return_approver_signature = ?, return_condition = ?, return_notes = ? WHERE history_id = ?");
        $h->bind_param('ssssi', $by, $sig, $condTh, $fullNotes, $l['history_id']); $h->execute();
    }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
logAssetHistory($conn, (int)$l['asset_id'], 'LOAN_RETURNED', "{$l['code']} รับคืนจาก {$l['borrower_name']} โดย {$by} · สภาพ: {$condTh} · ล้างข้อมูลแล้ว" . ($fullNotes ? " · {$fullNotes}" : ''));
loanNotify($conn, 'loan_return', '🔄 คืนอุปกรณ์แล้ว', $l, ['รับคืนโดย' => $by, 'สภาพ' => $condTh, 'หมายเหตุ' => $fullNotes]);
echo json_encode(['status' => 'success', 'broken' => $cond === 'broken']);
