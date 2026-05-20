<?php
// api/actions/create_chat_group.php

$group_name = $_POST['group_name'] ?? '';
$member_ids_json = $_POST['member_ids'] ?? '[]';
$member_ids = json_decode($member_ids_json, true);
$creator_id = $_SESSION['user_id'] ?? '';

if (empty($group_name) || !is_array($member_ids) || empty($creator_id)) {
    throw new Exception("Group name and members are required.");
}

$conn->begin_transaction();
try {
    // 1. Create the group
    $stmt = $conn->prepare("INSERT INTO chat_groups (name, creator_id) VALUES (?, ?)");
    if (!$stmt) throw new Exception("Prepare failed (create group): " . $conn->error);
    $stmt->bind_param("ss", $group_name, $creator_id);
    $stmt->execute();
    $new_group_id = $stmt->insert_id;

    // 2. Add members
    $member_stmt = $conn->prepare("INSERT INTO chat_group_members (group_id, user_id) VALUES (?, ?)");
    if (!$member_stmt) throw new Exception("Prepare failed (add members): " . $conn->error);
    
    // Add creator to the group as well
    $all_members = array_unique(array_merge($member_ids, [$creator_id]));

    foreach ($all_members as $user_id) {
        $member_stmt->bind_param("is", $new_group_id, $user_id);
        $member_stmt->execute();
    }

    $conn->commit();

    // Return the new group's data for frontend
    $group_data = [
        'id' => 'GROUP_' . $new_group_id,
        'name' => $group_name,
        'image' => '', // Special marker
        'department' => 'Group Chat',
        'company' => 'GROUP',
        'is_group' => true,
        'last_message_time' => date('Y-m-d H:i:s')
    ];

    echo json_encode(['status' => 'success', 'group' => $group_data]);

} catch (Exception $e) {
    $conn->rollback();
    throw $e;
}
?>