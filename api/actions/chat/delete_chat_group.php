<?php
// api/actions/chat/delete_chat_group.php

$group_id_str = $_POST['group_id'] ?? '';
$current_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? 'user';

if (empty($group_id_str) || empty($current_id)) {
    throw new Exception("Missing parameters.");
}

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

// Load group and check owner
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

if (!$isAdmin && !$isOwner) {
    throw new Exception("You are not allowed to delete this group.");
}

$conn->begin_transaction();

try {
    // Delete messages for this group
    $receiver_id = $group_id_str;
    if ($msg_stmt = $conn->prepare("DELETE FROM chat_messages WHERE receiver_id = ?")) {
        $msg_stmt->bind_param("s", $receiver_id);
        $msg_stmt->execute();
    }

    // Delete members
    if ($m_stmt = $conn->prepare("DELETE FROM chat_group_members WHERE group_id = ?")) {
        $m_stmt->bind_param("i", $group_id);
        $m_stmt->execute();
    }

    // Delete pending invites
    if ($inv_stmt = $conn->prepare("DELETE FROM chat_group_invites WHERE group_id = ?")) {
        $inv_stmt->bind_param("i", $group_id);
        $inv_stmt->execute();
    }

    // Delete logs
    if ($log_stmt = $conn->prepare("DELETE FROM chat_group_member_logs WHERE group_id = ?")) {
        $log_stmt->bind_param("i", $group_id);
        $log_stmt->execute();
    }

    // Delete group record
    if ($g_stmt = $conn->prepare("DELETE FROM chat_groups WHERE id = ?")) {
        $g_stmt->bind_param("i", $group_id);
        $g_stmt->execute();
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}

