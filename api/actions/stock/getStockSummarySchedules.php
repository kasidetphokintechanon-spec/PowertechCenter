<?php
// api/actions/stock/getStockSummarySchedules.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status'=>'error','message'=>'Access Denied']); exit;
}
_initStockSummarySchedulesTable($conn);
$res  = $conn->query("SELECT * FROM stock_summary_schedules ORDER BY id ASC");
$rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['status'=>'success','schedules'=>$rows]);
