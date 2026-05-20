<?php

$invite_id = (int)($_POST['invite_id'] ?? 0);
$action = $_POST['action_type'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if (empty($invite_id) || empty($user_id) || ($action !== 'accept' && $action !== 'decline')) {
    throw new Exception("Missing parameters.");
}

$stmt = $conn->prepare("SELECT * FROM chat_group_invites WHERE id = ? AND invitee_id = ?");
if (!$stmt) throw new Exception("Prepare failed (get invite): " . $conn->error);
$stmt->bind_param("is", $invite_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$invite = $res->fetch_assoc();

if (!$invite) {
    throw new Exception("Invite not found.");
}

if ($invite['status'] !== 'pending') {
    throw new Exception("Invite already processed.");
}

if (!empty($invite['expired_at']) && strtotime($invite['expired_at']) < time()) {
    $update_expired = $conn->prepare("UPDATE chat_group_invites SET status = 'expired', responded_at = NOW() WHERE id = ?");
    if ($update_expired) {
        $update_expired->bind_param("i", $invite_id);
        $update_expired->execute();
    }
    throw new Exception("Invite expired.");
}

$group_id = (int)$invite['group_id'];
$inviter_id = $invite['inviter_id'];

$conn->begin_transaction();
try {
    if ($action === 'accept') {
        $check_member = $conn->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?");
        if (!$check_member) throw new Exception("Prepare failed (check member): " . $conn->error);
        $check_member->bind_param("is", $group_id, $user_id);
        $check_member->execute();
        $member_res = $check_member->get_result();
        if ($member_res->num_rows === 0) {
            $insert_member = $conn->prepare("INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)");
            if (!$insert_member) throw new Exception("Prepare failed (insert member): " . $conn->error);
            $insert_member->bind_param("is", $group_id, $user_id);
            $insert_member->execute();
        }

        $update_stmt = $conn->prepare("UPDATE chat_group_invites SET status = 'accepted', responded_at = NOW() WHERE id = ?");
        if (!$update_stmt) throw new Exception("Prepare failed (update invite): " . $conn->error);
        $update_stmt->bind_param("i", $invite_id);
        $update_stmt->execute();

        $log_stmt = $conn->prepare("INSERT INTO chat_group_member_logs (group_id, actor_id, target_id, action) VALUES (?, ?, ?, 'invite_accepted')");
        if (!$log_stmt) throw new Exception("Prepare failed (log accept): " . $conn->error);
        $log_stmt->bind_param("iss", $group_id, $user_id, $user_id);
        $log_stmt->execute();

        $log_join = $conn->prepare("INSERT INTO chat_group_member_logs (group_id, actor_id, target_id, action) VALUES (?, ?, ?, 'member_joined')");
        if ($log_join) {
            $log_join->bind_param("iss", $group_id, $user_id, $user_id);
            $log_join->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'result' => 'accepted', 'group_id' => 'GROUP_' . $group_id]);
    } else {
        $update_stmt = $conn->prepare("UPDATE chat_group_invites SET status = 'declined', responded_at = NOW() WHERE id = ?");
        if (!$update_stmt) throw new Exception("Prepare failed (update invite): " . $conn->error);
        $update_stmt->bind_param("i", $invite_id);
        $update_stmt->execute();

        $log_stmt = $conn->prepare("INSERT INTO chat_group_member_logs (group_id, actor_id, target_id, action) VALUES (?, ?, ?, 'invite_declined')");
        if (!$log_stmt) throw new Exception("Prepare failed (log decline): " . $conn->error);
        $log_stmt->bind_param("iss", $group_id, $user_id, $user_id);
        $log_stmt->execute();

        $conn->commit();
        echo json_encode(['status' => 'success', 'result' => 'declined']);
    }
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}

