<?php
// api/actions/stock/saveDeptQuota.php
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company             = trim($_POST['company']             ?? '');
$department          = trim($_POST['department']          ?? '');
$stock_type          = trim($_POST['stock_type']          ?? 'stationery');
$monthly_limit       = intval($_POST['monthly_limit']       ?? 0);
$monthly_value_limit = floatval($_POST['monthly_value_limit'] ?? 0);
$by                  = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';

if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
if (!$department) throw new Exception("department จำเป็นต้องระบุ");
if (!in_array($stock_type, ['stationery','maid'])) throw new Exception("stock_type ไม่ถูกต้อง");
if ($monthly_limit < 0) throw new Exception("วงเงิน qty ต้องไม่ติดลบ");
if ($monthly_value_limit < 0) throw new Exception("วงเงินบาทต้องไม่ติดลบ");

$stmt = $conn->prepare("
    INSERT INTO stock_dept_quotas (company, department, stock_type, monthly_limit, monthly_value_limit, updated_by)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        monthly_limit       = VALUES(monthly_limit),
        monthly_value_limit = VALUES(monthly_value_limit),
        updated_by          = VALUES(updated_by),
        updated_at          = NOW()
");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("sssids", $company, $department, $stock_type, $monthly_limit, $monthly_value_limit, $by);
$stmt->execute();

echo json_encode(['status' => 'success']);
?>
