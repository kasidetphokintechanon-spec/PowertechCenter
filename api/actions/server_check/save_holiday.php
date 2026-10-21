<?php
// api/actions/server_check/save_holiday.php
$_role = $_SESSION['role'] ?? 'guest';
$_dept = $_SESSION['primary_department'] ?? '';
if (!isset($_SESSION['user_id']) || ($_role !== 'admin' && $_dept !== 'IT')) {
    echo json_encode(['status'=>'error','message'=>'Unauthorized — IT only']); exit;
}

$date    = preg_replace('/[^0-9\-]/', '', $_POST['holiday_date'] ?? '');
$name    = trim($_POST['name'] ?? '');
$company = trim($_POST['company'] ?? '') ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { echo json_encode(['status'=>'error','message'=>'invalid date']); exit; }
if (!$name) { echo json_encode(['status'=>'error','message'=>'name required']); exit; }

$stmt = $conn->prepare(
    "INSERT INTO company_holidays (holiday_date, name, company)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE name=VALUES(name)"
);
$stmt->bind_param('sss', $date, $name, $company);
$stmt->execute();
if (!$conn->insert_id) {
    $sel = $conn->prepare("SELECT id FROM company_holidays WHERE holiday_date=? AND (company IS NULL OR company=?)");
    $sel->bind_param('ss', $date, $company);
    $sel->execute();
    $id = (int)($sel->get_result()->fetch_row()[0] ?? 0);
} else {
    $id = $conn->insert_id;
}

echo json_encode(['status' => 'success', 'id' => $id]);
