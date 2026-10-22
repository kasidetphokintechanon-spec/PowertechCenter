<?php
// api/actions/loan/loan_decide.php — IT อนุมัติ / ไม่อนุมัติคำขอ · สิทธิ์ loan.manage
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l) throw new Exception('ไม่พบคำขอ');
if ($l['status'] !== 'requested') throw new Exception('คำขอนี้ถูกจัดการไปแล้ว');
$ok = ($_POST['decision'] ?? '') === 'approve';
$reason = trim(strip_tags((string)($_POST['reason'] ?? '')));
if (!$ok && $reason === '') throw new Exception('กรุณาระบุเหตุผลที่ไม่อนุมัติ');
if ($ok) {
    $clash = loanOverlaps($conn, (int)$l['asset_id'], $l['start_date'], $l['end_date'], (int)$l['id']);
    if ($clash && $clash['status'] !== 'requested') throw new Exception("ช่วงวันนี้ชนกับ {$clash['code']} ที่อนุมัติไปแล้ว");
}
$st = $conn->prepare("UPDATE it_loan_requests SET status = ?, decided_by = ?, decided_at = NOW(), reject_reason = ? WHERE id = ? AND status = 'requested'");
$s = $ok ? 'approved' : 'rejected'; $by = loanActor(); $rr = $ok ? null : $reason;
$st->bind_param('sssi', $s, $by, $rr, $l['id']); $st->execute();
logAssetHistory($conn, (int)$l['asset_id'], $ok ? 'LOAN_APPROVED' : 'LOAN_REJECTED', "{$l['code']} " . ($ok ? 'อนุมัติ' : 'ไม่อนุมัติ: ' . $reason) . " โดย {$by}");
loanNotify($conn, $ok ? 'loan_approve' : 'loan_reject', $ok ? '✅ อนุมัติยืมอุปกรณ์' : '🚫 ไม่อนุมัติยืมอุปกรณ์', $l, ['โดย' => $by, 'เหตุผล' => $rr]);
echo json_encode(['status' => 'success']);
