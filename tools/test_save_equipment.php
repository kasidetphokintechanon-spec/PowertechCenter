<?php
/**
 * Integration test: save_equipment bind_param + UPDATE
 * Run: C:\xampp\php\php.exe tools\test_save_equipment.php
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'powertech_system');

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

function logAssetHistory($conn, $assetDbId, $eventType, $details, $userName = 'System') {}

$row = $conn->query("SELECT a.*, c.cat_abbr FROM it_assets a LEFT JOIN asset_categories c ON a.asset_category=c.cat_id WHERE c.cat_abbr='NB' ORDER BY a.id DESC LIMIT 1")->fetch_assoc();
if (!$row) {
    echo "[SKIP] no NB asset to test UPDATE\n";
    exit(0);
}

$id = (int)$row['id'];
$hostname = 'LOADTEST-' . date('His');
$serial = $row['serial_number'] ?: 'SN-LOADTEST';
$name = $row['name'] ?: 'Load Test NB';
$equipment_id = $row['equipment_id'];
$brand = $row['brand'] ?? '';
$model = $row['model'] ?? '';
$asset_id = $row['asset_id'] ?? '';
$location = $row['location'] ?: 'IT Room';
$spec_details = $row['spec_details'] ?? '';
$ip_address = $row['ip_address'] ?? '';
$mac_address = $row['mac_address'] ?? '';
$supplier = $row['supplier'] ?? '';
$purchase_date = $row['purchase_date'];
$warranty_expires_on = $row['warranty_expires_on'];
$purchase_price = (float)($row['purchase_price'] ?? 0);
$po_number = $row['po_number'] ?? '';
$invoice_number = $row['invoice_number'] ?? '';
$accessories = $row['accessories'] ?? '[]';
$status = $row['status'] ?? 'ใช้งานปกติ';
$is_loanable = (int)($row['is_loanable'] ?? 0);
$company = $row['company'] ?: 'PTA';
$department_id = (int)$row['owning_department'];
$category_id = (int)$row['asset_category'];
$primaryImageUrl = $row['image_url'] ?? '';
$imagesJson = $row['images'] ?? '[]';
$borrower = $row['borrower'] ?? '';
$borrower_id = $row['borrower_id'] ?? '';
$custodian_department = $row['department'] ?? '';
$custodian_employee_id = $row['custodian_employee_id'] ?? '';
$parent_id = !empty($row['parent_id']) ? (int)$row['parent_id'] : null;

$sql = "UPDATE it_assets SET equipment_id=?, name=?, brand=?, model=?, serial_number=?, asset_id=?, location=?, spec_details=?, ip_address=?, mac_address=?, hostname=?, supplier=?, purchase_date=?, warranty_expires_on=?, purchase_price=?, po_number=?, invoice_number=?, accessories=?, status=?, is_loanable=?, company=?, owning_department=?, asset_category=?, image_url=?, images=?, borrower=?, borrower_id=?, department=?, custodian_employee_id=?, parent_id=? WHERE id=?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "[FAIL] prepare: {$conn->error}\n";
    exit(1);
}
$types = "ssssssssssssssdssssisssiissssii";
$stmt->bind_param(
    $types,
    $equipment_id, $name, $brand, $model, $serial, $asset_id, $location, $spec_details,
    $ip_address, $mac_address, $hostname, $supplier, $purchase_date, $warranty_expires_on, $purchase_price,
    $po_number, $invoice_number, $accessories, $status, $is_loanable, $company, $department_id, $category_id,
    $primaryImageUrl, $imagesJson, $borrower, $borrower_id, $custodian_department, $custodian_employee_id, $parent_id, $id
);

if (!$stmt->execute()) {
    echo "[FAIL] execute: {$stmt->error}\n";
    exit(1);
}

$chk = $conn->query("SELECT hostname, serial_number FROM it_assets WHERE id = $id")->fetch_assoc();
if (($chk['hostname'] ?? '') === $hostname) {
    echo "[PASS] save_equipment UPDATE bind + execute (id=$id, hostname=$hostname)\n";
} else {
    echo "[FAIL] hostname readback mismatch\n";
    exit(1);
}

// restore hostname
$conn->query("UPDATE it_assets SET hostname = " . ($row['hostname'] ? "'" . $conn->real_escape_string($row['hostname']) . "'" : "NULL") . " WHERE id = $id");
echo "[PASS] restored original hostname\n";
exit(0);
