<?php
// api/actions/chat/remove_chat_group_member.php

$group_id_str = $_POST['group_id'] ?? '';
$target_id = $_POST['target_id'] ?? '';
$current_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? 'user';

if (empty($group_id_str) || empty($target_id) || empty($current_id)) {
    throw new Exception("Missing parameters.");
}

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

// Only group creator or admin can remove other members
$creator_id = null;
$stmt = $conn->prepare("SELECT creator_id FROM chat_groups WHERE id = ?");
if (!$stmt) {
    throw new Exception("Prepare failed (find group): " . $conn->error);
}
$stmt->bind_param("i", $group_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $creator_id = (string)$row['creator_id'];
} else {
    throw new Exception("Group not found.");
}

$isAdmin = ($role === 'admin');
$isOwner = ($creator_id !== null && $creator_id === (string)$current_id);

if (!$isAdmin && !$isOwner && $current_id !== $target_id) {
    throw new Exception("You are not allowed to remove this member.");
}

// Do not allow removing last member silently
$count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_group_members WHERE group_id = ?");
if (!$count_stmt) {
    throw new Exception("Prepare failed (count members): " . $conn->error);
}
$count_stmt->bind_param("i", $group_id);
$count_stmt->execute();
$cnt_res = $count_stmt->get_result();
$cnt_row = $cnt_res->fetch_assoc();
$member_count = (int)($cnt_row['cnt'] ?? 0);
if ($member_count <= 1) {
    throw new Exception("Cannot remove the last member. Please delete the group instead.");
}

$del_stmt = $conn->prepare("DELETE FROM chat_group_members WHERE group_id = ? AND user_id = ?");
if (!$del_stmt) {
    throw new Exception("Prepare failed (remove member): " . $conn->error);
}
$del_stmt->bind_param("is", $group_id, $target_id);

if (!$del_stmt->execute()) {
    throw new Exception("Failed to remove member: " . $del_stmt->error);
}

// Optional: log removal
if ($log_stmt = $conn->prepare("INSERT INTO chat_group_member_logs (group_id, actor_id, target_id, action) VALUES (?, ?, ?, 'member_removed')")) {
    $log_stmt->bind_param("iss", $group_id, $current_id, $target_id);
    $log_stmt->execute();
}

echo json_encode(['status' => 'success']);

