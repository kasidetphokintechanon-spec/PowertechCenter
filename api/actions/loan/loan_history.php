<?php
// api/actions/loan/loan_history.php — ประวัติคำขอทั้งหมด (ค้นหา/กรอง/แบ่งหน้า) · สิทธิ์ loan.manage · ไม่มีการลบ (TISAX)
_initLoanTables($conn);
$q = trim((string)($_GET['q'] ?? ''));
$stF = (string)($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1)); $per = 30;
$w = ['1=1']; $p = []; $t = '';
$cos = userCompanies($conn);
$w[] = $cos ? 'asset_company IN (' . implode(',', array_fill(0, count($cos), '?')) . ')' : '1 = 0';
foreach ($cos as $c) { $p[] = $c; $t .= 's'; }
if ($q !== '') { $w[] = '(code LIKE ? OR equipment_id LIKE ? OR equipment_name LIKE ? OR borrower_name LIKE ? OR department LIKE ?)'; $like = "%{$q}%"; array_push($p, $like, $like, $like, $like, $like); $t .= 'sssss'; }
if ($stF !== '') { $w[] = 'status = ?'; $p[] = $stF; $t .= 's'; }
$where = implode(' AND ', $w);
$c = $conn->prepare("SELECT COUNT(*) FROM it_loan_requests WHERE {$where}");
if ($t) $c->bind_param($t, ...$p); $c->execute();
$total = (int)$c->get_result()->fetch_row()[0];
$off = ($page - 1) * $per;
$s = $conn->prepare("SELECT * FROM it_loan_requests WHERE {$where} ORDER BY created_at DESC LIMIT {$per} OFFSET {$off}");
if ($t) $s->bind_param($t, ...$p); $s->execute();
echo json_encode(['status' => 'success', 'data' => array_map('loanRow', $s->get_result()->fetch_all(MYSQLI_ASSOC)), 'total' => $total, 'pages' => max(1, (int)ceil($total / $per))], JSON_UNESCAPED_UNICODE);
