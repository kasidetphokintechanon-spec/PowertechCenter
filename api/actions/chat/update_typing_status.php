<?php
// api/actions/update_typing_status.php

$receiver_id = $_POST['receiver_id'] ?? '';
$sender_id = $_SESSION['user_id'] ?? '';

if (empty($sender_id) || empty($receiver_id)) {
    echo json_encode(['status' => 'ignored']);
    exit;
}

// Use DB instead of JSON file
$timestamp = time();
$stmt = $conn->prepare("INSERT INTO chat_typing (user_id, typing_to, timestamp) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE typing_to = VALUES(typing_to), timestamp = VALUES(timestamp)");
$stmt->bind_param("ssi", $sender_id, $receiver_id, $timestamp);
$stmt->execute();

echo json_encode(['status' => 'success']);
?>