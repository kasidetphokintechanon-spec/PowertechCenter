<?php
// api/actions/loan/loan_board.php — บอร์ด IT: คำขอที่ยังไม่ปิด + ที่คืน/ปิดวันนี้ + ตัวเลขสรุป · สิทธิ์ loan.manage ($ACTION_PERMS)
_initLoanTables($conn);
$role = $_SESSION['role'] ?? '';
$co = (string)($_SESSION['company'] ?? '');
$byCo = $role !== 'admin' && $co !== '';

$st = $conn->prepare("SELECT * FROM it_loan_requests WHERE (status IN ('requested','approved','active') OR DATE(COALESCE(return_at, decided_at)) = CURDATE())"
    . ($byCo ? ' AND asset_company = ?' : '') . " ORDER BY start_date, created_at");
if ($byCo) $st->bind_param('s', $co);
$st->execute();
$rows = array_map('loanRow', $st->get_result()->fetch_all(MYSQLI_ASSOC));

// อัตราการใช้ 30 วัน = วันที่เครื่องถูกยืม ÷ (จำนวนเครื่องให้ยืม × 30)
$st = $conn->prepare("SELECT COUNT(*) FROM it_assets WHERE is_loanable = 1 AND status NOT IN ('เลิกใช้งาน','จำหน่ายแล้ว','สูญหาย')" . ($byCo ? ' AND company = ?' : ''));
if ($byCo) $st->bind_param('s', $co);
$st->execute();
$assets = (int)$st->get_result()->fetch_row()[0];
$st = $conn->prepare("SELECT COALESCE(SUM(DATEDIFF(LEAST(COALESCE(DATE(return_at), CURDATE()), CURDATE()), GREATEST(DATE(handover_at), DATE_SUB(CURDATE(), INTERVAL 30 DAY))) + 1), 0)
    FROM it_loan_requests WHERE handover_at IS NOT NULL AND COALESCE(return_at, NOW()) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)" . ($byCo ? ' AND asset_company = ?' : ''));
if ($byCo) $st->bind_param('s', $co);
$st->execute();
$used = (int)$st->get_result()->fetch_row()[0];

echo json_encode(['status' => 'success', 'data' => $rows, 'today' => date('Y-m-d'),
    'util' => $assets ? min(100, (int)round($used / ($assets * 30) * 100)) : null, 'assets' => $assets], JSON_UNESCAPED_UNICODE);
