<?php
// api/actions/add_asset_note.php

$id = $_POST['id'] ?? '';
$note = $_POST['note'] ?? '';
$imagePath = '';

if (isset($_FILES['note_image']) && $_FILES['note_image']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/notes/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
    $fileName = uniqid() . '_' . basename($_FILES['note_image']['name']);
    move_uploaded_file($_FILES['note_image']['tmp_name'], $targetDir . $fileName);
    $imagePath = "$targetDir$fileName";
}

$details = json_encode(['text' => $note, 'image' => $imagePath], JSON_UNESCAPED_UNICODE);
logAssetHistory($conn, $id, 'NOTE', $details);
echo json_encode(['status' => 'success']);
?>