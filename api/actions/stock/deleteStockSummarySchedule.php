<?php
// api/actions/stock/deleteStockSummarySchedule.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status'=>'error','message'=>'Access Denied']); exit;
}
$id = intval($_POST['id'] ?? 0);
if (!$id) throw new Exception("id ไม่ถูกต้อง");
$stmt = $conn->prepare("DELETE FROM stock_summary_schedules WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
echo json_encode(['status'=>'success']);
