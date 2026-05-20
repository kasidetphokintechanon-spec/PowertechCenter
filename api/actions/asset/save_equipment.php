<?php
// api/actions/save_equipment.php

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

// จัดการไฟล์ใหม่ที่อัปโหลด
if (isset($_FILES['imageFiles'])) {
    $targetDir = "uploads/equipment/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
    
    $count = count($_FILES['imageFiles']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['imageFiles']['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES['imageFiles']['name'][$i]);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($fileExt, $allowed)) {
                $newFileName = ($_POST['equipment_id'] ?: uniqid()) . '-' . uniqid() . '-' . $i . '.' . $fileExt;
                $targetFilePath = $targetDir . $newFileName;

                if (move_uploaded_file($_FILES['imageFiles']['tmp_name'][$i], $targetFilePath)) {
                    $imagesList[] = $targetFilePath;
                }
            }
        }
    }
}

// เตรียมข้อมูลรูปภาพสำหรับบันทึก
$imagesJson = json_encode(array_values($imagesList), JSON_UNESCAPED_UNICODE);
$primaryImageUrl = $imagesList[0] ?? ''; // รูปแรกเป็นรูปหลัก

// --- 3. เตรียมข้อมูลสำหรับบันทึก (Prepare Data) ---
$db_id = $_POST['db_id'] ?? '';

// รับค่าจาก Form
$equipment_id = $_POST['equipment_id'] ?? '';
$name = $_POST['name'] ?? '';
$brand = $_POST['brand'] ?? '';
$model = $_POST['model'] ?? '';
$serial_number = $_POST['serial_number'] ?? '';
$asset_id = $_POST['asset_id'] ?? '';
$location = $_POST['location'] ?? '';
$spec_details = $_POST['spec_details'] ?? '';
$ip_address = $_POST['ip_address'] ?? '';
$mac_address = $_POST['mac_address'] ?? '';
$supplier = $_POST['supplier'] ?? '';
$purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
$warranty_expires_on = !empty($_POST['warranty_expires_on']) ? $_POST['warranty_expires_on'] : NULL;
$purchase_price = !empty($_POST['purchase_price']) ? $_POST['purchase_price'] : 0;
$po_number = $_POST['po_number'] ?? '';
$invoice_number = $_POST['invoice_number'] ?? '';
$accessories = $_POST['accessories'] ?? '[]';
$status = $_POST['status'] ?? 'ใช้งานปกติ';
$is_loanable = isset($_POST['is_loanable']) ? 1 : 0;
$company = $_POST['company'] ?? '';
$borrower = $_POST['borrower'] ?? '';
$parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;

// รับค่า ID ของ Department และ Category เพื่อบันทึกโดยตรง
// ใช้ NULL ถ้าค่าที่ส่งมาเป็น empty string เพื่อความปลอดภัยของ Foreign Key
$department_id = !empty($_POST['owning_department']) ? $_POST['owning_department'] : NULL;
$category_id = !empty($_POST['asset_category']) ? $_POST['asset_category'] : NULL;

// --- Check Duplicate Equipment ID ---
if (!empty($equipment_id)) {
    $checkSql = "SELECT id FROM it_assets WHERE equipment_id = ? AND id != ?";
    $checkStmt = $conn->prepare($checkSql);
    // db_id might be empty string if insert, so use 0 for comparison
    $checkId = !empty($db_id) ? $db_id : 0;
    $checkStmt->bind_param("si", $equipment_id, $checkId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        throw new Exception("รหัสพัสดุ (Asset ID) '$equipment_id' มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น");
    }
}

// --- 4. บันทึกลงฐานข้อมูล (Insert / Update) ---
if (!empty($db_id)) {
    // กรณีแก้ไข (Update)
    // ดึงข้อมูลเดิมเพื่อเปรียบเทียบสเปค
    $old_res = $conn->query("SELECT spec_details FROM it_assets WHERE id = " . intval($db_id));
    $old_data = $old_res ? $old_res->fetch_assoc() : null;

    $sql = "UPDATE it_assets SET equipment_id=?, name=?, brand=?, model=?, serial_number=?, asset_id=?, location=?, spec_details=?, ip_address=?, mac_address=?, supplier=?, purchase_date=?, warranty_expires_on=?, purchase_price=?, po_number=?, invoice_number=?, accessories=?, status=?, is_loanable=?, company=?, owning_department=?, asset_category=?, image_url=?, images=?, borrower=?, parent_id=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Update Asset): " . $conn->error);
    $stmt->bind_param("sssssssssssssdssssissssssii", $equipment_id, $name, $brand, $model, $serial_number, $asset_id, $location, $spec_details, $ip_address, $mac_address, $supplier, $purchase_date, $warranty_expires_on, $purchase_price, $po_number, $invoice_number, $accessories, $status, $is_loanable, $company, $department_id, $category_id, $primaryImageUrl, $imagesJson, $borrower, $parent_id, $db_id);
    
    if ($stmt->execute()) {
        if ($old_data && $old_data['spec_details'] !== $spec_details) {
            logAssetHistory($conn, $db_id, 'SPEC_CHANGE', "อัปเกรด/เปลี่ยนสเปค: " . $spec_details);
        } else {
            logAssetHistory($conn, $db_id, 'UPDATE', "แก้ไขข้อมูลพัสดุ: $name");
        }
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("SQL Error: " . $stmt->error);
    }
} else {
    // กรณีเพิ่มใหม่ (Insert)
    $sql = "INSERT INTO it_assets (equipment_id, name, brand, model, serial_number, asset_id, location, spec_details, ip_address, mac_address, supplier, purchase_date, warranty_expires_on, purchase_price, po_number, invoice_number, accessories, status, is_loanable, company, owning_department, asset_category, image_url, images, borrower, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Insert Asset): " . $conn->error);
    $stmt->bind_param("sssssssssssssdssssissssssi", $equipment_id, $name, $brand, $model, $serial_number, $asset_id, $location, $spec_details, $ip_address, $mac_address, $supplier, $purchase_date, $warranty_expires_on, $purchase_price, $po_number, $invoice_number, $accessories, $status, $is_loanable, $company, $department_id, $category_id, $primaryImageUrl, $imagesJson, $borrower, $parent_id);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        logAssetHistory($conn, $newId, 'CREATE', "เพิ่มพัสดุใหม่: $name");
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception("SQL Error: " . $stmt->error);
    }
}
?>
