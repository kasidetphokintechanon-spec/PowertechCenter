<?php
// api/actions/get_chat_updates.php

$my_id = $_SESSION['user_id'] ?? '';
if (empty($my_id)) {
    echo json_encode(['status' => 'success', 'messages' => [], 'typing_users' => [], 'read_ids' => []]);
    exit;
}

// 1. Get groups I'm in
$my_groups = [];
$group_stmt = $conn->prepare("SELECT group_id FROM chat_group_members WHERE user_id = ?");
$group_stmt->bind_param("s", $my_id);
$group_stmt->execute();
$group_result = $group_stmt->get_result();
while($row = $group_result->fetch_assoc()) {
    $my_groups[] = 'GROUP_' . $row['group_id'];
}

// 1. Get new unread messages for me
$sql = "SELECT m.*, e.name as sender_name 
        FROM chat_messages m
        LEFT JOIN employees e ON m.sender_id = e.id
        WHERE m.is_read = 0 AND m.sender_id != ? AND (m.receiver_id = ?"; // ดึงเฉพาะที่ยังไม่ Delivered (0)
$params = [$my_id, $my_id];
$types = "ss";
if (!empty($my_groups)) {
    $placeholders = implode(',', array_fill(0, count($my_groups), '?'));
    $sql .= " OR m.receiver_id IN ($placeholders)";
    $params = array_merge($params, $my_groups);
    $types .= str_repeat('s', count($my_groups));
}
$sql .= ") ORDER BY m.timestamp ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);

// After fetching new messages, mark them as delivered (is_read = 1) to prevent loop
if (!empty($messages)) {
    $msg_ids = array_map(function($m) { return intval($m['message_id']); }, $messages);
    if (!empty($msg_ids)) {
        $ids_str = implode(',', $msg_ids);
        $conn->query("UPDATE chat_messages SET is_read = 1 WHERE message_id IN ($ids_str)");
    }
}

// 2. Get read receipts for messages I sent
// เช็คว่าข้อความที่เราส่งไป มีคนอ่านหรือยัง (is_read = 2)
$read_stmt = $conn->prepare("SELECT message_id FROM chat_messages WHERE sender_id = ? AND is_read = 2");
$read_stmt->bind_param("s", $my_id);
$read_stmt->execute();
$read_result = $read_stmt->get_result();
$read_ids = [];
while($row = $read_result->fetch_assoc()) {
    $read_ids[] = $row['message_id'];
}

// ไม่ต้องอัปเดตสถานะซ้ำ เพราะ is_read = 2 คือสถานะปลายทางแล้ว

// --- Get Typing Status (From DB) ---
$typing_users = [];
$now = time();
$stale_threshold = 5; // 5 seconds

// Clean up old records first
$conn->query("DELETE FROM chat_typing WHERE timestamp < " . ($now - $stale_threshold));

// Fetch users typing to me
$stmt_typing = $conn->prepare("SELECT user_id FROM chat_typing WHERE typing_to = ?");
$stmt_typing->bind_param("s", $my_id);
$stmt_typing->execute();
$res_typing = $stmt_typing->get_result();
while($row = $res_typing->fetch_assoc()) {
    $typing_users[] = $row['user_id'];
}

echo json_encode([
    'status' => 'success', 
    'messages' => $messages, 
    'typing_users' => $typing_users,
    'read_ids' => $read_ids
]);
?>