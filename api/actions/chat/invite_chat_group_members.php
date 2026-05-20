<?php

$group_id_str = $_POST['group_id'] ?? '';
$invitee_ids_json = $_POST['invitee_ids'] ?? '[]';
$invitee_ids = json_decode($invitee_ids_json, true);
$inviter_id = $_SESSION['user_id'] ?? '';

if (empty($group_id_str) || empty($inviter_id) || !is_array($invitee_ids) || empty($invitee_ids)) {
    throw new Exception("Missing parameters.");
}

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

$role_stmt = $conn->prepare("SELECT g.creator_id, gm.user_id FROM chat_groups g LEFT JOIN chat_group_members gm ON g.id = gm.group_id WHERE g.id = ?");
if (!$role_stmt) throw new Exception("Prepare failed (check group): " . $conn->error);
$role_stmt->bind_param("i", $group_id);
$role_stmt->execute();
$role_res = $role_stmt->get_result();
$is_member = false;
$is_owner = false;
while ($row = $role_res->fetch_assoc()) {
    if ((string)$row['user_id'] === (string)$inviter_id) {
        $is_member = true;
    }
    if ((string)$row['creator_id'] === (string)$inviter_id) {
        $is_owner = true;
    }
}
if (!$is_member || !$is_owner) {
    throw new Exception("You are not allowed to invite members to this group.");
}

$stmt_current = $conn->prepare("SELECT COUNT(*) as cnt FROM chat_group_members WHERE group_id = ?");
if (!$stmt_current) throw new Exception("Prepare failed (count members): " . $conn->error);
$stmt_current->bind_param("i", $group_id);
$stmt_current->execute();
$current_cnt = 0;
if ($res = $stmt_current->get_result()) {
    if ($row = $res->fetch_assoc()) {
        $current_cnt = (int)$row['cnt'];
    }
}

$max_members = 200;
$group_info = $conn->query("SELECT name FROM chat_groups WHERE id = " . intval($group_id));
if ($group_info && $info_row = $group_info->fetch_assoc()) {
}

$available_slots = $max_members - $current_cnt;
if ($available_slots <= 0) {
    throw new Exception("Group is full.");
}

$invitee_ids = array_values(array_unique(array_map('strval', $invitee_ids)));

$placeholders = implode(',', array_fill(0, count($invitee_ids), '?'));
$types = str_repeat('s', count($invitee_ids));

$member_check_sql = "SELECT user_id FROM chat_group_members WHERE group_id = ? AND user_id IN ($placeholders)";
$member_check_stmt = $conn->prepare($member_check_sql);
if (!$member_check_stmt) throw new Exception("Prepare failed (check members): " . $conn->error);
$member_check_params = array_merge([$group_id], $invitee_ids);
$member_check_stmt->bind_param("i" . $types, ...$member_check_params);
$member_check_stmt->execute();
$member_res = $member_check_stmt->get_result();
$already_members = [];
while ($row = $member_res->fetch_assoc()) {
    $already_members[] = (string)$row['user_id'];
}

$pending_check_sql = "SELECT invitee_id FROM chat_group_invites WHERE group_id = ? AND status = 'pending' AND invitee_id IN ($placeholders)";
$pending_check_stmt = $conn->prepare($pending_check_sql);
if (!$pending_check_stmt) throw new Exception("Prepare failed (check invites): " . $conn->error);
$pending_check_params = array_merge([$group_id], $invitee_ids);
$pending_check_stmt->bind_param("i" . $types, ...$pending_check_params);
$pending_check_stmt->execute();
$pending_res = $pending_check_stmt->get_result();
$already_pending = [];
while ($row = $pending_res->fetch_assoc()) {
    $already_pending[] = (string)$row['invitee_id'];
}

$to_invite = [];
foreach ($invitee_ids as $uid) {
    if (in_array($uid, $already_members, true)) {
        continue;
    }
    if (in_array($uid, $already_pending, true)) {
        continue;
    }
    $to_invite[] = $uid;
}

if (empty($to_invite)) {
    echo json_encode(['status' => 'success', 'createdInvites' => [], 'skipped' => $invitee_ids]);
    exit;
}

if (count($to_invite) > $available_slots) {
    $to_invite = array_slice($to_invite, 0, $available_slots);
}

$conn->begin_transaction();
try {
    $invite_stmt = $conn->prepare("INSERT INTO chat_group_invites (group_id, inviter_id, invitee_id) VALUES (?, ?, ?)");
    if (!$invite_stmt) throw new Exception("Prepare failed (insert invites): " . $conn->error);

    $log_stmt = $conn->prepare("INSERT INTO chat_group_member_logs (group_id, actor_id, target_id, action) VALUES (?, ?, ?, 'invite_sent')");
    if (!$log_stmt) throw new Exception("Prepare failed (insert logs): " . $conn->error);

    $created = [];
    foreach ($to_invite as $uid) {
        $invite_stmt->bind_param("iss", $group_id, $inviter_id, $uid);
        $invite_stmt->execute();
        $invite_id = $invite_stmt->insert_id;
        $log_stmt->bind_param("iss", $group_id, $inviter_id, $uid);
        $log_stmt->execute();
        $created[] = ['inviteId' => $invite_id, 'inviteeId' => $uid, 'status' => 'pending'];
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'createdInvites' => $created]);
} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}

