<?php
// api/actions/loan/loan_my.php — คำขอยืมทั้งหมดของคนที่ล็อกอิน (ใหม่สุดก่อน)
_initLoanTables($conn);
$uid = (string)($_SESSION['user_id'] ?? '');
$st = $conn->prepare("SELECT * FROM it_loan_requests WHERE borrower_id = ? ORDER BY FIELD(status,'active','approved','requested') DESC, created_at DESC LIMIT 100");
$st->bind_param('s', $uid); $st->execute();
$rows = array_map('loanRow', $st->get_result()->fetch_all(MYSQLI_ASSOC));
// ประวัติแบบเก่า (ก่อนมีระบบคำขอ) ยังให้เห็น — อ่านอย่างเดียว
$old = $conn->prepare("SELECT equipment_id, equipment_name, lend_date, expected_return_date, return_date, return_condition FROM it_loan_history
    WHERE borrower_id = ? AND lend_date < (SELECT COALESCE(MIN(created_at), NOW()) FROM it_loan_requests) ORDER BY lend_date DESC LIMIT 50");
$old->bind_param('s', $uid); $old->execute();
echo json_encode(['status' => 'success', 'data' => $rows, 'legacy' => $old->get_result()->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE);
