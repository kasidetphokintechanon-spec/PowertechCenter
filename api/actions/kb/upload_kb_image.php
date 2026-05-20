<?php
// api/actions/upload_kb_image.php
if (isset($_FILES['kb_image']) && $_FILES['kb_image']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/kb_images/";
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
            throw new Exception("Failed to create KB image directory.");
        }
    }

    $file = $_FILES['kb_image'];
    $fileName = basename($file['name']);
    $safeFileName = preg_replace("/[^a-zA-Z0-9\._-]/", "", $fileName);
    $fileExt = strtolower(pathinfo($safeFileName, PATHINFO_EXTENSION));
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExt, $allowed)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.");
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        throw new Exception("File is too large. Maximum size is 5MB.");
    }

    $newFileName = uniqid('kb_') . '-' . $safeFileName;
    $targetFilePath = $targetDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $baseUrl = "$protocol://$host$scriptDir";
        $imageUrl = "$baseUrl/$targetFilePath";
        
        echo json_encode(['status' => 'success', 'url' => $imageUrl]);
    } else {
        throw new Exception("Failed to move uploaded file.");
    }
} else {
    throw new Exception("No file uploaded or upload error.");
}
?>