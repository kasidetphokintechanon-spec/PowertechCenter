<?php
// api/actions/loan/loan_cancel.php — ผู้ยืมยกเลิกเอง ได้เฉพาะก่อนรับของ (requested / approved)
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l) throw new Exception('ไม่พบคำขอ');
if ((string)$l['borrower_id'] !== (string)($_SESSION['user_id'] ?? '') && !loanCanManage($conn)) throw new Exception('ยกเลิกได้เฉพาะคำขอของตัวเอง');
if (!in_array($l['status'], ['requested', 'approved'], true)) throw new Exception('ยกเลิกไม่ได้ — รับของไปแล้ว ให้ทำการคืนแทน');
$reason = trim(strip_tags((string)($_POST['reason'] ?? ''))) ?: 'ผู้ยืมยกเลิกเอง';
$st = $conn->prepare("UPDATE it_loan_requests SET status = 'cancelled', reject_reason = ?, decided_by = ?, decided_at = NOW() WHERE id = ? AND status IN ('requested','approved')");
$by = loanActor(); $st->bind_param('ssi', $reason, $by, $l['id']); $st->execute();
logAssetHistory($conn, (int)$l['asset_id'], 'LOAN_CANCEL', "{$l['code']} ยกเลิก: {$reason}");
echo json_encode(['status' => 'success']);
