<?php
// api/actions/get_chat_history.php

$other_user_id = $_GET['other_user_id'] ?? '';
$company = $_GET['company'] ?? '';
$my_id = $_SESSION['user_id'] ?? '';

if (empty($my_id) || empty($other_user_id)) {
    throw new Exception("Missing parameters for chat history.");
}

// For simplicity, we are not strictly enforcing company check on history view,
// but it's good practice for multi-tenant systems.
// The frontend logic already separates users by company.

$stmt = $conn->prepare("SELECT * FROM chat_messages 
                        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                        ORDER BY timestamp ASC");
if (strpos($other_user_id, 'GROUP_') === 0) {
    // It's a group chat
    $stmt = $conn->prepare("SELECT m.*, e.name as sender_name 
                            FROM chat_messages m
                            LEFT JOIN employees e ON m.sender_id = e.id
                            WHERE m.receiver_id = ?
                            ORDER BY m.timestamp ASC");
    if (!$stmt) throw new Exception("Prepare failed (Get Group History): " . $conn->error);
    $stmt->bind_param("s", $other_user_id);
} else {
    // It's a one-on-one chat (existing logic)
    $stmt = $conn->prepare("SELECT * FROM chat_messages WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY timestamp ASC");
    if (!$stmt) throw new Exception("Prepare failed (Get History): " . $conn->error);
    $stmt->bind_param("ssss", $my_id, $other_user_id, $other_user_id, $my_id);
}

// Mark messages from this user to me as read
if (strpos($other_user_id, 'GROUP_') !== 0) {
    // เปลี่ยนสถานะเป็น 2 (Read) เมื่อเปิดดูประวัติ
    $update_stmt = $conn->prepare("UPDATE chat_messages SET is_read = 2 WHERE sender_id = ? AND receiver_id = ? AND is_read < 2");
    if ($update_stmt) {
        $update_stmt->bind_param("ss", $other_user_id, $my_id);
        $update_stmt->execute();
    }
}

$stmt->execute();
$result = $stmt->get_result();
$messages = [];
if ($result) {
    $messages = $result->fetch_all(MYSQLI_ASSOC);
}

echo json_encode(['status' => 'success', 'messages' => $messages]);
?>