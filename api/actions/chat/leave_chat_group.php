<?php
// api/actions/leave_chat_group.php

$group_id_str = $_POST['group_id'] ?? ''; // e.g., "GROUP_123"
$user_id = $_SESSION['user_id'] ?? '';

if (empty($group_id_str) || empty($user_id)) throw new Exception("Group ID and User ID are required.");

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

$stmt = $conn->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?");
if (!$stmt) throw new Exception("Prepare failed (leave group): " . $conn->error);
$stmt->bind_param("is", $group_id, $user_id);

if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception("Failed to leave group: " . $stmt->error);
?>