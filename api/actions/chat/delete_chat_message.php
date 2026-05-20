<?php
// api/actions/delete_chat_message.php

$message_id = $_POST['message_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? 'user';

if (empty($message_id) || empty($user_id)) {
    throw new Exception("Message ID and User ID are required.");
}

$sql = "DELETE FROM chat_messages WHERE message_id = ?";
$params = [$message_id];
$types = "i";

// Security check: Only allow deleting own message, unless user is admin
if ($role !== 'admin') {
    $sql .= " AND sender_id = ?";
    $params[] = $user_id;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed (Delete Message): " . $conn->error);

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else throw new Exception("Execute failed (Delete Message): " . $stmt->error);
?>