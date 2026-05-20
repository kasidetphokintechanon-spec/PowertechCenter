<?php
// api/actions/send_chat_message.php

$receiver_id = $_POST['receiver_id'] ?? '';
$company = $_POST['company'] ?? '';
$message_text = $_POST['message_text'] ?? '';
$sender_id = $_SESSION['user_id'] ?? '';

if (empty($sender_id) || empty($receiver_id) || empty($message_text)) {
    throw new Exception("Missing required chat data.");
}

// Prevent duplicate messages (Debounce 2 seconds)
$dup_check = $conn->prepare("SELECT message_id FROM chat_messages WHERE sender_id = ? AND receiver_id = ? AND message_text = ? AND timestamp >= NOW() - INTERVAL 2 SECOND");
$dup_check->bind_param("sss", $sender_id, $receiver_id, $message_text);
$dup_check->execute();
if ($dup_check->get_result()->num_rows > 0) {
    echo json_encode(['status' => 'success', 'message_id' => 'duplicate']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chat_messages (sender_id, receiver_id, company, message_text) VALUES (?, ?, ?, ?)");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("ssss", $sender_id, $receiver_id, $company, $message_text);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message_id' => $stmt->insert_id]);
} else {
    throw new Exception("Execute failed: " . $stmt->error);
}
?>