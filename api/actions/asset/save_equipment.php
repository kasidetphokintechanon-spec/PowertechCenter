<?php
// api/actions/save_equipment.php

if (!function_exists('ensureItAssetsRegistryColumns')) {
    function ensureItAssetsRegistryColumns(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'hostname'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN hostname VARCHAR(255) DEFAULT NULL COMMENT 'PC Name / Hostname' AFTER mac_address");
        }
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'custodian_type'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN custodian_type VARCHAR(20) NOT NULL DEFAULT 'person' COMMENT 'person|department' AFTER custodian_employee_id");
        }
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'custodian_dept_id'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN custodian_dept_id INT(11) DEFAULT NULL COMMENT 'แผนกผู้ถือครอง (ใช้ร่วม)' AFTER custodian_type");
        }
        $res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'useful_life_override'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE it_assets ADD COLUMN useful_life_override TINYINT UNSIGNED DEFAULT NULL COMMENT 'อายุใช้งาน (ปี) override รายชิ้น — NULL = ใช้ default ของ category'");
        }
        $res = $conn->query("SHOW COLUMNS FROM asset_categories LIKE 'default_useful_life'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE asset_categories ADD COLUMN default_useful_life TINYINT UNSIGNED DEFAULT NULL COMMENT 'อายุใช้งาน default (ปี) เช่น NB=5, Printer=3'");
        }
        $done = true;
    }
}

if (!function_exists('resolveDepartmentName')) {
    function resolveDepartmentName(mysqli $conn, ?int $deptId): string {
        if (!$deptId) return '';
        $stmt = $conn->prepare('SELECT dept_name FROM departments WHERE dept_id = ? LIMIT 1');
        if (!$stmt) return '';
        $stmt->bind_param('i', $deptId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return trim($row['dept_name'] ?? '');
    }
}

ensureItAssetsRegistryColumns($conn);

// --- 1. จัดการรูปภาพ (Multiple Images) ---
$existingImagesJson = $_POST['existing_images'] ?? '[]';
$imagesList = json_decode($existingImagesJson, true);
if (!is_array($imagesList)) $imagesList = [];

function normalizeUploadPath($value) {
    $v = trim((string)($value ?? ''));
    if ($v === '') return '';
    if (strpos($v, 'uploads/') === 0) return $v;
    $path = parse_url($v, PHP_URL_PATH);
    if (!$path) $path = $v;
    $path = ltrim($path, '/');
    $pos = strpos($path, 'uploads/');
    if ($pos !== false) return substr($path, $pos);
    return $v;
}

$imagesList = array_values(array_filter(array_map('normalizeUploadPath', $imagesList), function($v) { return $v !== ''; }));

if (isset($_FILES['imageFiles'])) {
    $targetDir = rtrim(str_replace('\\', '/', dirname(dirname(dirname(__DIR__)))), '/') . '/uploads/equipment/';
    if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);

    $allowed     = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize     = 10 * 1024 * 1024; // 10MB
    $useFinfo    = extension_loaded('fileinfo');

    $count = count($_FILES['imageFiles']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['imageFiles']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['imageFiles']['size'][$i] > $maxSize) continue;

        $fileName = basename($_FILES['imageFiles']['name'][$i]);
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowed)) continue;

        if ($useFinfo) {
            $finfo       = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($_FILES['imageFiles']['tmp_name'][$i]);
            if (!in_array($detectedMime, $allowedMimes)) continue;
        }

        $newFileName    = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['equipment_id'] ?: uniqid()) . '-' . uniqid() . '-' . $i . '.' . $fileExt;
        $targetFilePath = $targetDir . $newFileName;

        if (move_uploaded_file($_FILES['imageFiles']['tmp_name'][$i], $targetFilePath)) {
            // เก็บเป็น relative path จาก project root เสมอ
            $imagesList[] = 'uploads/equipment/' . $newFileName;
        }
    }
}

$imagesJson = json_encode(array_values($imagesList), JSON_UNESCAPED_UNICODE);
$primaryImageUrl = $imagesList[0] ?? '';

$db_id = $_POST['db_id'] ?? '';

$equipment_id = trim($_POST['equipment_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');
$serial_number = trim($_POST['serial_number'] ?? '');
$asset_id = trim($_POST['asset_id'] ?? '');
$location = trim($_POST['location'] ?? '');
$spec_details = $_POST['spec_details'] ?? '';
$ip_address = trim($_POST['ip_address'] ?? '');
$mac_address = trim($_POST['mac_address'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$supplier = trim($_POST['supplier'] ?? '');
$purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
$warranty_expires_on = !empty($_POST['warranty_expires_on']) ? $_POST['warranty_expires_on'] : NULL;
$purchase_price = !empty($_POST['purchase_price']) ? $_POST['purchase_price'] : 0;
$po_number = trim($_POST['po_number'] ?? '');
$invoice_number = trim($_POST['invoice_number'] ?? '');
$accessories = $_POST['accessories'] ?? '[]';
$status = in_array($_POST['status'] ?? '', ['ใช้งานปกติ','ถูกยืม','รออนุมัติ','ชำรุด','รอซ่อม','เลิกใช้งาน'], true) ? $_POST['status'] : 'ใช้งานปกติ';
$is_loanable = isset($_POST['is_loanable']) ? 1 : 0;
$useful_life_override = !empty($_POST['useful_life_override']) ? (int)$_POST['useful_life_override'] : NULL;
$company = trim($_POST['company'] ?? '');
$borrower = trim($_POST['borrower'] ?? '');
$parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : NULL;

$department_id = !empty($_POST['owning_department']) ? (int)$_POST['owning_department'] : NULL;
$category_id = !empty($_POST['asset_category']) ? (int)$_POST['asset_category'] : NULL;

$custodian_employee_id = trim($_POST['custodian_employee_id'] ?? '');
$borrower_id = $custodian_employee_id !== '' ? $custodian_employee_id : trim($_POST['borrower_id'] ?? '');
$custodian_department = trim($_POST['custodian_department'] ?? '');
$custodian_type = in_array($_POST['custodian_type'] ?? '', ['person', 'department'], true) ? $_POST['custodian_type'] : 'person';
$custodian_dept_id = !empty($_POST['custodian_dept_id']) ? (int)$_POST['custodian_dept_id'] : null;

if ($custodian_type === 'department') {
    $custDeptId = $custodian_dept_id ?: $department_id;
    if (!$custDeptId) throw new Exception('กรุณาเลือกแผนกผู้ถือครอง (ใช้ร่วมกัน)');
    $deptName = resolveDepartmentName($conn, $custDeptId);
    if ($deptName === '') throw new Exception('ไม่พบชื่อแผนกผู้ถือครอง');
    $borrower = 'แผนก ' . $deptName . ' (ใช้ร่วมกัน)';
    $custodian_department = $deptName;
    $borrower_id = '';
    $custodian_employee_id = '';
    $custodian_dept_id = $custDeptId;
} else {
    $custodian_dept_id = null;
    if ($borrower === '') {
        $borrower_id = '';
        $custodian_employee_id = '';
        $custodian_department = '';
    }
}

// --- Server-side validation (Inspector checklist) ---
if ($name === '') throw new Exception('กรุณาระบุชื่ออุปกรณ์');
if ($company === '') throw new Exception('กรุณาเลือกบริษัท');
if (!$department_id) throw new Exception('กรุณาเลือกแผนก');
if (!$category_id) throw new Exception('กรุณาเลือกหมวดหมู่');
if ($location === '') throw new Exception('กรุณาระบุ Location');

$cat_abbr = '';
if ($category_id) {
    $catStmt = $conn->prepare('SELECT cat_abbr FROM asset_categories WHERE cat_id = ? LIMIT 1');
    if ($catStmt) {
        $catStmt->bind_param('i', $category_id);
        $catStmt->execute();
        $catRow = $catStmt->get_result()->fetch_assoc();
        $cat_abbr = strtoupper(trim($catRow['cat_abbr'] ?? ''));
    }
}

if (in_array($cat_abbr, ['NB', 'PC', 'SV'], true)) {
    if ($serial_number === '') throw new Exception('Notebook/PC/Server ต้องระบุ Serial Number');
    if ($hostname === '') throw new Exception('Notebook/PC/Server ต้องระบุ PC Name / Hostname');
}
if ($cat_abbr === 'NT' && $ip_address === '') {
    throw new Exception('Network ต้องระบุ IP Address');
}

if (!empty($equipment_id)) {
    $checkSql = "SELECT id FROM it_assets WHERE equipment_id = ? AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkId = !empty($db_id) ? (int)$db_id : 0;
    $checkStmt->bind_param("si", $equipment_id, $checkId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        throw new Exception("รหัสพัสดุ (Asset ID) '$equipment_id' มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น");
    }
}

if (!empty($db_id)) {
    $old_res = $conn->query("SELECT spec_details FROM it_assets WHERE id = " . intval($db_id));
    $old_data = $old_res ? $old_res->fetch_assoc() : null;

    $sql = "UPDATE it_assets SET equipment_id=?, name=?, brand=?, model=?, serial_number=?, asset_id=?, location=?, spec_details=?, ip_address=?, mac_address=?, hostname=?, supplier=?, purchase_date=?, warranty_expires_on=?, purchase_price=?, po_number=?, invoice_number=?, accessories=?, status=?, is_loanable=?, company=?, owning_department=?, asset_category=?, image_url=?, images=?, borrower=?, borrower_id=?, department=?, custodian_employee_id=?, custodian_type=?, custodian_dept_id=?, parent_id=?, useful_life_override=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Update Asset): " . $conn->error);
    $stmt->bind_param(
        "ssssssssssssssdssssissssssssssiiii",
        $equipment_id, $name, $brand, $model, $serial_number, $asset_id, $location, $spec_details,
        $ip_address, $mac_address, $hostname, $supplier, $purchase_date, $warranty_expires_on, $purchase_price,
        $po_number, $invoice_number, $accessories, $status, $is_loanable, $company, $department_id, $category_id,
        $primaryImageUrl, $imagesJson, $borrower, $borrower_id, $custodian_department, $custodian_employee_id, $custodian_type, $custodian_dept_id, $parent_id, $useful_life_override, $db_id
    );

    if ($stmt->execute()) {
        if ($old_data && ($old_data['spec_details'] ?? '') !== $spec_details) {
            logAssetHistory($conn, $db_id, 'SPEC_CHANGE', "อัปเกรด/เปลี่ยนสเปค: " . $spec_details);
        } else {
            logAssetHistory($conn, $db_id, 'UPDATE', "แก้ไขข้อมูลพัสดุ: $name");
        }
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("SQL Error: " . $stmt->error);
    }
} else {
    $sql = "INSERT INTO it_assets (equipment_id, name, brand, model, serial_number, asset_id, location, spec_details, ip_address, mac_address, hostname, supplier, purchase_date, warranty_expires_on, purchase_price, po_number, invoice_number, accessories, status, is_loanable, company, owning_department, asset_category, image_url, images, borrower, borrower_id, department, custodian_employee_id, custodian_type, custodian_dept_id, parent_id, useful_life_override) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Insert Asset): " . $conn->error);
    $stmt->bind_param(
        "ssssssssssssssdssssissssssssssiii",
        $equipment_id, $name, $brand, $model, $serial_number, $asset_id, $location, $spec_details,
        $ip_address, $mac_address, $hostname, $supplier, $purchase_date, $warranty_expires_on, $purchase_price,
        $po_number, $invoice_number, $accessories, $status, $is_loanable, $company, $department_id, $category_id,
        $primaryImageUrl, $imagesJson, $borrower, $borrower_id, $custodian_department, $custodian_employee_id, $custodian_type, $custodian_dept_id, $parent_id, $useful_life_override
    );

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        logAssetHistory($conn, $newId, 'CREATE', "เพิ่มพัสดุใหม่: $name");
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("SQL Error: " . $stmt->error);
    }
}
?>
