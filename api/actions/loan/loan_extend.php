<?php
// api/actions/loan/loan_extend.php — ผู้ยืมขอขยายเวลาคืน (ระหว่างกำลังยืม) · IT อนุมัติใน loan_extend_decide
_initLoanTables($conn);
$l = loanGet($conn, (int)($_POST['id'] ?? 0));
if (!$l || (string)$l['borrower_id'] !== (string)($_SESSION['user_id'] ?? '')) throw new Exception('ไม่พบคำขอของคุณ');
if ($l['status'] !== 'active') throw new Exception('ขอขยายได้เฉพาะของที่กำลังยืมอยู่');
if ($l['extend_status'] === 'pending') throw new Exception('มีคำขอขยายเวลารออนุมัติอยู่แล้ว');
$to = trim((string)($_POST['to'] ?? ''));
$reason = trim(strip_tags((string)($_POST['reason'] ?? '')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to <= max((string)$l['end_date'], date('Y-m-d'))) throw new Exception('วันที่ใหม่ต้องหลังวันกำหนดคืนเดิมและหลังวันนี้');
if ($reason === '') throw new Exception('กรุณาบอกเหตุผล');
$clash = loanOverlaps($conn, (int)$l['asset_id'], date('Y-m-d', strtotime(($l['end_date'] ?: date('Y-m-d')) . ' +1 day')), $to, (int)$l['id']);
if ($clash) throw new Exception('ขยายไม่ได้ — มีคนจองเครื่องนี้ต่อตั้งแต่ ' . date('d/m', strtotime($clash['start_date'])));
$st = $conn->prepare("UPDATE it_loan_requests SET extend_to = ?, extend_reason = ?, extend_status = 'pending' WHERE id = ?");
$st->bind_param('ssi', $to, $reason, $l['id']); $st->execute();
loanNotify($conn, 'loan_new', '⏳ ขอขยายเวลาคืน', $l, ['ขอขยายถึง' => date('d/m/Y', strtotime($to)), 'เหตุผล' => $reason]);
echo json_encode(['status' => 'success']);
