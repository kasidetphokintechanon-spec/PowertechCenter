<?php
// api/actions/loan/loan_extend_decide.php — IT ตอบคำขอขยายเวลา · สิทธิ์ loan.manage
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l || $l['extend_status'] !== 'pending') throw new Exception('ไม่มีคำขอขยายเวลาที่รออยู่');
if (!inScope($conn, $l['asset_company'])) throw new Exception('คำขอนี้เป็นของบริษัทที่คุณไม่ได้ดูแล');
$ok = ($_POST['decision'] ?? '') === 'approve';
if ($ok) {
    $clash = loanOverlaps($conn, (int)$l['asset_id'], date('Y-m-d', strtotime(($l['end_date'] ?: date('Y-m-d')) . ' +1 day')), $l['extend_to'], (int)$l['id']);
    if ($clash) throw new Exception("ขยายไม่ได้ — ชนกับ {$clash['code']}");
    $st = $conn->prepare("UPDATE it_loan_requests SET end_date = extend_to, extend_status = 'approved' WHERE id = ?");
    $st->bind_param('i', $l['id']); $st->execute();
    $a = $conn->prepare("UPDATE it_assets SET return_date = ? WHERE id = ? AND status = 'ถูกยืม'"); $a->bind_param('si', $l['extend_to'], $l['asset_id']); $a->execute();
    if ($l['history_id']) { $h = $conn->prepare("UPDATE it_loan_history SET expected_return_date = ? WHERE history_id = ?"); $h->bind_param('si', $l['extend_to'], $l['history_id']); $h->execute(); }
} else {
    $st = $conn->prepare("UPDATE it_loan_requests SET extend_status = 'rejected' WHERE id = ?"); $st->bind_param('i', $l['id']); $st->execute();
}
logAssetHistory($conn, (int)$l['asset_id'], 'LOAN_EXTEND', "{$l['code']} ขอขยายถึง {$l['extend_to']}: " . ($ok ? 'อนุมัติ' : 'ไม่อนุมัติ') . ' โดย ' . loanActor());
echo json_encode(['status' => 'success']);
