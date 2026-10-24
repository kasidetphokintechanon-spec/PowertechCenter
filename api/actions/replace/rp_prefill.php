<?php
// api/actions/replace/rp_prefill.php — ข้อมูลตั้งต้นของใบประเมิน: จากงานซ่อม (ticket) หรือรหัสเครื่อง (asset) · สิทธิ์ IT ($ACTION_PERMS)
// ดึงอายุเครื่อง ราคาซื้อ อายุใช้งานตามหมวด และประวัติซ่อมเครื่องนี้ (กี่ครั้ง ค่าซ่อมรวม) — ข้อมูลที่หัวหน้าใช้ตัดสิน
_initReplaceTables($conn);
$tid = trim((string)($_GET['ticket'] ?? ''));
$t = null;
if ($tid !== '') {
    $st = $conn->prepare("SELECT id, company, requester, department, asset_id, problem, solution, status FROM it_logs WHERE id = ?");
    $st->bind_param('s', $tid); $st->execute();
    $t = $st->get_result()->fetch_assoc();
    if (!$t) throw new Exception('ไม่พบงานซ่อม');
    if ($t['company'] && !inScope($conn, $t['company'])) throw new Exception('งานนี้ไม่อยู่ในบริษัทที่คุณดูแล');
    $ex = $conn->prepare("SELECT id, code, status FROM it_replacements WHERE ticket_id = ? AND status <> 'rejected' LIMIT 1");
    $ex->bind_param('s', $tid); $ex->execute();
    // งานนี้มีใบประเมินอยู่แล้ว — เปิดใบเดิม ไม่สร้างซ้ำ
    if ($x = $ex->get_result()->fetch_assoc()) { echo json_encode(['status' => 'success', 'existing' => ['id' => (int)$x['id'], 'code' => $x['code']]]); return; }
}
$a = rpFindAsset($conn, $t ? $t['asset_id'] : ($_GET['asset'] ?? ''));
if ($a && $a['company'] && !inScope($conn, $a['company'])) throw new Exception('เครื่องนี้ไม่อยู่ในบริษัทที่คุณดูแล');
$hist = ['n' => 0, 'cost' => 0];
if ($a) {
    $h = $conn->prepare("SELECT COUNT(*), COALESCE(SUM(repair_cost), 0) FROM it_logs WHERE asset_id IN (?, ?) AND status <> 'Cancelled'");
    $c1 = (string)$a['equipment_id']; $c2 = (string)($a['asset_id'] ?: $a['equipment_id']);
    $h->bind_param('ss', $c1, $c2); $h->execute();
    [$n, $cost] = $h->get_result()->fetch_row(); $hist = ['n' => (int)$n, 'cost' => (float)$cost];
}
// ผู้ใช้เครื่อง: ผู้ถือครองในทะเบียน → ไม่มีก็ใช้ผู้แจ้งซ่อม
$holder = null;
if ($a && $a['custodian_employee_id']) {
    $e = $conn->prepare("SELECT name FROM employees WHERE id = ?"); $cid = (string)$a['custodian_employee_id']; $e->bind_param('s', $cid); $e->execute();
    $holder = $e->get_result()->fetch_row()[0] ?? null;
}
echo json_encode(['status' => 'success', 'prefill' => [
    'ticket_id' => $t['id'] ?? null, 'asset_db_id' => $a ? (int)$a['id'] : null,
    'asset_code' => $a['equipment_id'] ?? ($t['asset_id'] ?? ''), 'asset_name' => $a ? trim($a['name'] . ' ' . ($a['brand'] ?? '') . ' ' . ($a['model'] ?? '')) : '',
    'serial_number' => $a['serial_number'] ?? '', 'company' => $a['company'] ?? ($t['company'] ?? ''), 'user_name' => $holder ?: ($t['requester'] ?? ''), 'department' => $t['department'] ?? '',
    'purchase_date' => $a['purchase_date'] ?? null, 'purchase_price' => $a && (float)$a['purchase_price'] > 0 ? (float)$a['purchase_price'] : null,   // 0 = ไม่ได้กรอกราคา
    'useful_life' => $a ? ((int)($a['useful_life_override'] ?: $a['default_useful_life']) ?: null) : null,
    'problem' => $t['problem'] ?? '', 'findings' => $t ? trim(preg_replace('/^.*(ซ่อมไม่ได้|\(Unrepairable\)):\s*/su', '', (string)$t['solution'])) : '',
    'repair_history' => $hist, 'asset_status' => $a['status'] ?? null]], JSON_UNESCAPED_UNICODE);
