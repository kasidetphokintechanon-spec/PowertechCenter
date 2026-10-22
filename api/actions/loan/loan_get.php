<?php
// api/actions/loan/loan_get.php — รายละเอียดคำขอ + ของในชุดของเครื่อง + ประวัติการยืมเครื่องนี้ (หน้าส่งมอบ/รับคืน)
// เจ้าของคำขอดูของตัวเองได้ · IT (loan.manage) ดูได้ทุกใบ
_initLoanTables($conn);
$l = loanGet($conn, (int)($_GET['id'] ?? 0));
if (!$l) throw new Exception('ไม่พบคำขอ');
$mine = (string)$l['borrower_id'] === (string)($_SESSION['user_id'] ?? '');
if (!$mine && !loanCanManage($conn)) throw new Exception('ไม่มีสิทธิ์ดูคำขอนี้');
$st = $conn->prepare("SELECT accessories, image_url, status, location FROM it_assets WHERE id = ?");
$st->bind_param('i', $l['asset_id']); $st->execute();
$a = $st->get_result()->fetch_assoc() ?: [];
$hist = [];
if (!$mine || loanCanManage($conn)) {
    $h = $conn->prepare("SELECT code, borrower_name, department, start_date, end_date, status, return_condition, return_notes FROM it_loan_requests WHERE asset_id = ? AND id <> ? ORDER BY start_date DESC LIMIT 8");
    $h->bind_param('ii', $l['asset_id'], $l['id']); $h->execute();
    $hist = $h->get_result()->fetch_all(MYSQLI_ASSOC);
}
echo json_encode(['status' => 'success', 'loan' => loanRow($l), 'accessories' => json_decode($a['accessories'] ?? '[]', true) ?: [],
    'asset_status' => $a['status'] ?? '', 'history' => $hist], JSON_UNESCAPED_UNICODE);
