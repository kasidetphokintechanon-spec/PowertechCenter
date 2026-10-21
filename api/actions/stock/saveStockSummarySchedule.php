<?php
// api/actions/stock/saveStockSummarySchedule.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status'=>'error','message'=>'Access Denied']); exit;
}
_initStockSummarySchedulesTable($conn);

$id           = intval($_POST['id'] ?? 0);
$name         = trim($_POST['name'] ?? '');
$company      = in_array($_POST['company'] ?? '', ['PTA','PT4','PTE','']) ? ($_POST['company'] ?? '') : '';
$stock_type   = in_array($_POST['stock_type'] ?? '', ['stationery','maid','']) ? ($_POST['stock_type'] ?? '') : '';
$frequency    = in_array($_POST['frequency'] ?? '', ['daily','weekly','monthly']) ? $_POST['frequency'] : 'daily';
$send_time    = preg_match('/^\d{2}:\d{2}$/', $_POST['send_time'] ?? '') ? $_POST['send_time'] : '08:00';
$day_of_week  = ($frequency === 'weekly')  ? max(0, min(6, intval($_POST['day_of_week']  ?? 1))) : null;
$day_of_month = ($frequency === 'monthly') ? max(1, min(31, intval($_POST['day_of_month'] ?? 1))) : null;
$enabled      = intval($_POST['enabled'] ?? 1) ? 1 : 0;

$company_val    = $company    ?: null;
$stock_type_val = $stock_type ?: null;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE stock_summary_schedules SET name=?,company=?,stock_type=?,frequency=?,send_time=?,day_of_week=?,day_of_month=?,enabled=? WHERE id=?");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("sssssiiii", $name,$company_val,$stock_type_val,$frequency,$send_time,$day_of_week,$day_of_month,$enabled,$id);
    $stmt->execute();
    echo json_encode(['status'=>'success','id'=>$id]);
} else {
    $stmt = $conn->prepare("INSERT INTO stock_summary_schedules (name,company,stock_type,frequency,send_time,day_of_week,day_of_month,enabled) VALUES (?,?,?,?,?,?,?,?)");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("sssssiiii",$name,$company_val,$stock_type_val,$frequency,$send_time,$day_of_week,$day_of_month,$enabled);
    $stmt->execute();
    echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
}
