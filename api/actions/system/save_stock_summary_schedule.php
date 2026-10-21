<?php
// api/actions/system/save_stock_summary_schedule.php
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']); exit;
}

$enabled    = ($_POST['enabled'] ?? '0') === '1' ? '1' : '0';
$time       = preg_match('/^\d{2}:\d{2}$/', $_POST['time'] ?? '') ? $_POST['time'] : '08:00';
$company    = in_array($_POST['company'] ?? '', ['PTA','PT4','PTE','']) ? ($_POST['company'] ?? '') : '';
$stock_type = in_array($_POST['stock_type'] ?? '', ['stationery','maid','']) ? ($_POST['stock_type'] ?? '') : '';

$upsert = function($key, $val) use ($conn) {
    $k = $conn->real_escape_string($key);
    $v = $conn->real_escape_string($val);
    $conn->query("INSERT INTO system_settings (setting_key, setting_value)
                  VALUES ('$k', '$v')
                  ON DUPLICATE KEY UPDATE setting_value = '$v'");
};

$upsert('stock_summary_enabled',    $enabled);
$upsert('stock_summary_time',       $time);
$upsert('stock_summary_company',    $company);
$upsert('stock_summary_stock_type', $stock_type);

echo json_encode(['status' => 'success']);
