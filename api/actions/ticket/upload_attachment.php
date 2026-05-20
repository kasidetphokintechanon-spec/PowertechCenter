<?php
// api/actions/upload_attachment.php

$log_id = $_POST['log_id'] ?? '';
if (empty($log_id)) {
    throw new Exception("Log ID is required for attachment.");
}

if (isset($_FILES['attachmentFile']) && $_FILES['attachmentFile']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/attachments/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $original_filename = basename($_FILES['attachmentFile']['name']);
    $safe_original_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "", $original_filename);
    
    $fileExt = strtolower(pathinfo($safe_original_filename, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

    if (in_array($fileExt, $allowed)) {
        $stored_filename = "log_{$log_id}_" . uniqid() . '.' . $fileExt;
        $targetFilePath = $targetDir . $stored_filename;

        if (move_uploaded_file($_FILES['attachmentFile']['tmp_name'], $targetFilePath)) {
            $stmt = $conn->prepare("INSERT INTO it_log_attachments (log_id, original_filename, stored_filename) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare failed (Upload Attachment): " . $conn->error);
            
            $stmt->bind_param("sss", $log_id, $safe_original_filename, $stored_filename);
            if (!$stmt->execute()) {
                error_log("Failed to insert attachment record for $log_id: " . $stmt->error);
            }
        }
    }
}
// The frontend doesn't wait for a response, so just send success.
echo json_encode(['status' => 'success']);
?>