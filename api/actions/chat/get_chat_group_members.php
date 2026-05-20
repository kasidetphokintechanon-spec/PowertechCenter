<?php

$group_id_str = $_GET['group_id'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? 'user';

if (empty($group_id_str) || empty($user_id)) {
    throw new Exception("Missing parameters.");
}

$group_id = (int)str_replace('GROUP_', '', $group_id_str);

if ($role !== 'admin') {
    $check_stmt = $conn->prepare("SELECT 1 FROM chat_group_members WHERE group_id = ? AND user_id = ?");
    if (!$check_stmt) throw new Exception("Prepare failed (check membership): " . $conn->error);
    $check_stmt->bind_param("is", $group_id, $user_id);
    $check_stmt->execute();
    $res = $check_stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception("You are not a member of this group.");
    }
}

$sql = "SELECT gm.user_id, e.name, e.name_th, e.image, ea.department, ea.company
        FROM chat_group_members gm
        LEFT JOIN employees e ON gm.user_id = e.id
        LEFT JOIN employee_assignments ea ON e.id = ea.employee_id AND ea.is_primary = 1
        WHERE gm.group_id = ?
        ORDER BY e.name ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed (members): " . $conn->error);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

echo json_encode([
    'status' => 'success',
    'group_id' => $group_id_str,
    'members' => $members
]);

