<?php
// api/actions/loan/loan_request.php — ขอยืม · ผู้ยืม = คนที่ล็อกอิน (เดิมพิมพ์ชื่อเองได้ → ยืมในชื่อคนอื่นได้)
_initLoanTables($conn);
$uid = (string)($_SESSION['user_id'] ?? '');
$assetId = (int)($_POST['asset_id'] ?? 0);
$start = trim((string)($_POST['start_date'] ?? ''));
$end = trim((string)($_POST['end_date'] ?? ''));
$noDue = !empty($_POST['no_due']);
$purpose = trim(strip_tags((string)($_POST['purpose'] ?? '')));
$contact = trim(strip_tags((string)($_POST['contact'] ?? '')));
$today = date('Y-m-d');

if (!$assetId) throw new Exception('ไม่พบอุปกรณ์');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || $start < $today) throw new Exception('วันที่รับของต้องเป็นวันนี้หรือหลังจากนี้');
if ($noDue) $end = null;
elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start) throw new Exception('วันที่คืนต้องไม่ก่อนวันที่รับ');
if (mb_strlen($purpose) < 3) throw new Exception('กรุณาระบุว่าใช้ทำอะไร');

$st = $conn->prepare("SELECT id, equipment_id, name, company, status, is_loanable FROM it_assets WHERE id = ?");
$st->bind_param('i', $assetId); $st->execute();
$a = $st->get_result()->fetch_assoc();
if (!$a || !(int)$a['is_loanable']) throw new Exception('อุปกรณ์นี้ไม่ได้เปิดให้ยืม');
if (in_array($a['status'], ['ชำรุด', 'รอซ่อม', 'ส่งซ่อม', 'เลิกใช้งาน'], true)) throw new Exception('อุปกรณ์นี้อยู่ระหว่างซ่อมหรือเลิกใช้งาน');

// ข้ามบริษัทต้องมีกติกาอนุญาต
$myCo = (string)($_SESSION['company'] ?? '');
if (($_SESSION['role'] ?? '') !== 'admin' && $myCo !== '' && $a['company'] && $a['company'] !== $myCo) {
    $r = $conn->prepare("SELECT 1 FROM loan_company_rules WHERE from_company = ? AND to_company = ? AND is_allowed = 1");
    $r->bind_param('ss', $myCo, $a['company']); $r->execute();
    if (!$r->get_result()->fetch_row()) throw new Exception("ยืมข้ามบริษัท ({$myCo} → {$a['company']}) ยังไม่ได้รับอนุญาต");
}

$clash = loanOverlaps($conn, $assetId, $start, $end);
if ($clash) throw new Exception('ช่วงวันนี้มีคนจองแล้ว (' . date('d/m', strtotime($clash['start_date'])) . ($clash['end_date'] ? '–' . date('d/m', strtotime($clash['end_date'])) : ' เป็นต้นไป') . ') — เลือกวันอื่นหรือเครื่องอื่น');

// ข้อมูลผู้ยืมจากทะเบียนพนักงาน
$name = loanActor(); $dept = (string)($_SESSION['primary_department'] ?? ''); $co = $myCo;
$e = $conn->prepare("SELECT e.name, e.phone, a.department, a.company FROM employees e LEFT JOIN employee_assignments a ON a.employee_id = e.id AND a.is_primary = 1 WHERE e.id = ? LIMIT 1");
if ($e) { $e->bind_param('s', $uid); $e->execute(); if ($row = $e->get_result()->fetch_assoc()) { $name = $row['name'] ?: $name; $dept = $row['department'] ?: $dept; $co = $row['company'] ?: $co; if ($contact === '') $contact = (string)$row['phone']; } }

$code = loanNextCode($conn);
$ins = $conn->prepare("INSERT INTO it_loan_requests (code, asset_id, equipment_id, equipment_name, asset_company, borrower_id, borrower_name, department, borrower_company, contact, purpose, start_date, end_date)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
$ins->bind_param('sisssssssssss', $code, $assetId, $a['equipment_id'], $a['name'], $a['company'], $uid, $name, $dept, $co, $contact, $purpose, $start, $end);
$ins->execute();
$id = $conn->insert_id;
logAssetHistory($conn, $assetId, 'LOAN_REQUEST', "{$code} ขอยืมโดย {$name} ช่วง {$start}" . ($end ? " ถึง {$end}" : ' ไม่มีกำหนดคืน'));
loanNotify($conn, 'loan_new', '📦 ขอยืมอุปกรณ์', loanGet($conn, $id), ['ใช้ทำ' => $purpose]);
echo json_encode(['status' => 'success', 'id' => $id, 'code' => $code]);
