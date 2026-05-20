<?php
// api/actions/upload_company_logo.php

// ตรวจสอบสิทธิ์ Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    throw new Exception("Access denied.");
}

$company = $_POST['company'] ?? '';
$valid_companies = ['PTA', 'PT4', 'PTE', 'DIR', 'APP'];

if (!in_array($company, $valid_companies)) {
    throw new Exception("Invalid company code.");
}

if (isset($_FILES['logoFile']) && $_FILES['logoFile']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/logos/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = $company === 'DIR'
        ? 'directory_app_icon.png'
        : ($company === 'APP' ? 'powertech_app_icon.png' : strtolower($company) . "_logo.png");
    $targetFilePath = $targetDir . $fileName;

    // ลบไฟล์เดิมถ้ามี (เพื่อให้แน่ใจว่า Browser ไม่ cache รูปเก่า)
    if (file_exists($targetFilePath)) {
        unlink($targetFilePath);
    }

    if (move_uploaded_file($_FILES['logoFile']['tmp_name'], $targetFilePath)) {
        echo json_encode(['status' => 'success', 'url' => $targetFilePath . '?t=' . time()]);
    } else {
        throw new Exception("Sorry, there was an error uploading your file.");
    }
} else {
    throw new Exception("No file uploaded or upload error.");
}
?>
