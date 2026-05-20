<?php
// api/actions/update_chat_group_name.php

$group_id_str = $_POST['group_id'] ?? '';
$new_name = $_POST['new_name'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (empty($group_id_str) || empty($new_name) || empty($user_id)) {
    throw new Exception("Missing parameters.");
}

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

// Check membership
$check_stmt = $conn->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?");
$check_stmt->bind_param("is", $group_id, $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows === 0) {
     throw new Exception("You are not a member of this group.");
}

$stmt = $conn->prepare("UPDATE chat_groups SET name = ? WHERE id = ?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("si", $new_name, $group_id);

if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception("Failed to update group name: " . $stmt->error);
?>