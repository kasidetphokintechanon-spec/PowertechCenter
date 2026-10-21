<?php
// api/actions/upload_chat_file.php

if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $targetDir = "uploads/chat_files/";
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception("Failed to create chat upload directory.");
        }
    }

    $fileName = basename($file['name']);
    // Sanitize filename
    $safeFileName = preg_replace("/[^a-zA-Z0-9\._-]/", "", $fileName);
    $fileExt = strtolower(pathinfo($safeFileName, PATHINFO_EXTENSION));
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
    if (!in_array($fileExt, $allowed)) {
        throw new Exception("Invalid file type.");
    }

    // ตรวจสอบ MIME type จากเนื้อหาไฟล์จริง (ป้องกันการปลอม extension)
    $allowedMimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain', 'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    if (!in_array($detectedMime, $allowedMimes)) {
        throw new Exception("Invalid file content type.");
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        throw new Exception("File is too large. Maximum size is 10MB.");
    }

    $newFileName = 'chat_' . uniqid() . '.' . $fileExt;
    $targetFilePath = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        // Construct the full URL to return to the client
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); 
        $baseUrl = "$protocol://$host$scriptDir";
        $fileUrl = "$baseUrl/$targetFilePath";

        echo json_encode([
            'status' => 'success', 
            'url' => $fileUrl,
            'filename' => $fileName,
            'size' => $file['size']
        ]);
    } else {
        throw new Exception("Failed to move uploaded file.");
    }
} else {
    throw new Exception("No file uploaded or an error occurred during upload.");
}
?>