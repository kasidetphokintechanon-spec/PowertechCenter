<?php
// api/actions/stock/sendStockSummary.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = trim($_POST['company']    ?? $_GET['company']    ?? '');
$stock_type = trim($_POST['stock_type'] ?? $_GET['stock_type'] ?? '');
if (!in_array($stock_type, ['stationery','maid'])) $stock_type = '';

$msg = buildStockSummaryMessage($conn, $company ?: null, $stock_type ?: null);
sendNotify($conn, $msg, 'stock_summary', $company ?: null);

// บันทึกวันที่ส่งล่าสุด (ป้องกัน schedule ส่งซ้ำ)
$today = date('Y-m-d');
$conn->query("INSERT INTO system_settings (setting_key, setting_value)
              VALUES ('stock_summary_last_sent', '$today')
              ON DUPLICATE KEY UPDATE setting_value = '$today'");

echo json_encode(['status' => 'success', 'message' => 'ส่งสรุป Stock เรียบร้อยแล้ว']);
