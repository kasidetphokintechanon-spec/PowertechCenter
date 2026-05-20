<?php
// api/actions/get_chat_users.php

$my_role = $_SESSION['role'] ?? 'user';
$my_dept = $_SESSION['primary_department'] ?? '';
$my_id = $_SESSION['user_id'] ?? '';

$users = [];

// Add IT Group for IT staff and Admins
if ($my_role === 'admin' || $my_dept === 'IT') {
    $g_stmt = $conn->prepare("SELECT MAX(timestamp) as last_ts FROM chat_messages WHERE receiver_id = 'GROUP_IT'");
    $g_stmt->execute();
    $group_last_time = $g_stmt->get_result()->fetch_assoc()['last_ts'] ?? null;

    $users[] = [
        'id' => 'GROUP_IT',
        'name' => 'IT Department Group',
        'name_th' => 'กลุ่มแผนก IT',
        'image' => '', // Special marker handled in frontend
        'department' => 'IT',
        'company' => 'ALL',
        'is_group' => true,
        'last_message_time' => $group_last_time
    ];
}

$sql = "SELECT e.id, e.name, e.name_th, e.image, ea.department, ea.company,
        (SELECT MAX(timestamp) FROM chat_messages 
         WHERE (sender_id = ? AND receiver_id = e.id) 
            OR (sender_id = e.id AND receiver_id = ?)
        ) as last_message_time,
        (SELECT COUNT(*) FROM chat_messages 
         WHERE sender_id = e.id AND receiver_id = ? AND is_read < 2
        ) as unread_count
        FROM employees e
        JOIN employee_assignments ea ON e.id = ea.employee_id
        WHERE e.employment_status = 'active' AND ea.is_primary = 1";

// Logic:
// 1. Admin & IT Staff: Can see ALL users from ALL companies.
// 2. General User: Can ONLY see IT staff from ALL companies.
//    BUT they should also see users they have chat history with.
if ($my_role !== 'admin' && $my_dept !== 'IT') {
    // Use HAVING to filter after last_message_time is calculated
    // Show if Department is IT OR if there is a chat history
    $sql .= " HAVING (department = 'IT' OR last_message_time IS NOT NULL)";
}

// Order by Company, then put IT first, then Name
$sql .= " ORDER BY ea.company ASC, CASE WHEN ea.department = 'IT' THEN 0 ELSE 1 END, e.name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    throw new Exception("Database error (get_chat_users): " . $conn->error);
}
$stmt->bind_param("sss", $my_id, $my_id, $my_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Fetch groups the user is a member of
$group_sql = "SELECT g.id, g.name 
              FROM chat_groups g
              JOIN chat_group_members gm ON g.id = gm.group_id
              WHERE gm.user_id = ?";
$group_stmt = $conn->prepare($group_sql);
$group_stmt->bind_param("s", $my_id);
$group_stmt->execute();
$group_result = $group_stmt->get_result();

while ($group_row = $group_result->fetch_assoc()) {
    $group_id_str = 'GROUP_' . $group_row['id'];
    
    $ts_stmt = $conn->prepare("SELECT MAX(timestamp) as last_ts FROM chat_messages WHERE receiver_id = ?");
    $ts_stmt->bind_param("s", $group_id_str);
    $ts_stmt->execute();
    $last_message_time = $ts_stmt->get_result()->fetch_assoc()['last_ts'] ?? null;

    $group_row['id'] = $group_id_str;
    $group_row['is_group'] = true;
    $group_row['last_message_time'] = $last_message_time;
    $users[] = $group_row;
}

// Sort users: Recent chat first
usort($users, function($a, $b) {
    $t1 = $a['last_message_time'] ?? 0;
    $t2 = $b['last_message_time'] ?? 0;
    if ($t1 == $t2) {
        return 0; 
    }
    return ($t1 < $t2) ? 1 : -1;
});

echo json_encode(['status' => 'success', 'users' => $users]);
?>