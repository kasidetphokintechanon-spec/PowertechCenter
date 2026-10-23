<?php
// api/actions/stock/stock_quota.php — วงเงินเบิกต่อแผนก/เดือน (เตือน ไม่บล็อก) · GET = รายการทุกแผนก + ใช้ไปเดือนนี้ · POST = บันทึก
// สิทธิ์ stock.manage ($ACTION_PERMS) · ใส่ 0 = ไม่จำกัด
_initStockFlow($conn);
$co = stockPickOne($conn, (string)($_REQUEST['company'] ?? ''));
$type = (string)($_REQUEST['stock_type'] ?? 'stationery');
if (!isset(STOCK_TYPE_TH[$type])) throw new Exception('ประเภทไม่ถูกต้อง');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept = mb_substr(trim(strip_tags((string)($_POST['department'] ?? ''))), 0, 255);
    if ($dept === '') throw new Exception('ไม่ระบุแผนก');
    $limit = max(0, round((float)($_POST['limit'] ?? 0), 2));
    $by = stockActor();
    $st = $conn->prepare("SELECT id FROM stock_dept_quotas WHERE company = ? AND department = ? AND stock_type = ?");
    $st->bind_param('sss', $co, $dept, $type); $st->execute();
    $id = $st->get_result()->fetch_row()[0] ?? null;
    if ($id) { $u = $conn->prepare("UPDATE stock_dept_quotas SET monthly_value_limit = ?, updated_by = ? WHERE id = ?"); $u->bind_param('dsi', $limit, $by, $id); }
    else { $u = $conn->prepare("INSERT INTO stock_dept_quotas (company, department, stock_type, monthly_limit, monthly_value_limit, updated_by) VALUES (?,?,?,0,?,?)"); $u->bind_param('sssds', $co, $dept, $type, $limit, $by); }
    $u->execute();
    echo json_encode(['status' => 'success']);
    return;
}

// แผนกจากตารางแผนก + แผนกที่เคยมีใบเบิก/ตั้งวงเงินไว้ (ชื่อเก่าที่ไม่อยู่ในตารางแล้วก็ยังเห็น)
$names = array_column($conn->query("SELECT dept_name FROM departments ORDER BY sort_order, dept_name")->fetch_all(), 0);
$st = $conn->prepare("SELECT DISTINCT department FROM stock_requests WHERE company = ? AND department <> '' UNION SELECT department FROM stock_dept_quotas WHERE company = ?");
$st->bind_param('ss', $co, $co); $st->execute();
foreach ($st->get_result()->fetch_all() as $r) if (!in_array($r[0], $names, true)) $names[] = $r[0];
$rows = [];
foreach ($names as $d) $rows[] = ['department' => $d] + stockDeptBudget($conn, $co, $d, $type);
echo json_encode(['status' => 'success', 'company' => $co, 'stock_type' => $type, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
