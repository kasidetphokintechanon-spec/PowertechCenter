<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$targetUser = $_GET['user_id'] ?? '';
$sessionUser = $_SESSION['user_id'];

if ($targetUser === '' || $targetUser === null) {
    $targetUser = $sessionUser;
}

if ($targetUser !== $sessionUser && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$stmtEmp = $conn->prepare("SELECT id, role, name, name_th FROM employees WHERE id = ? LIMIT 1");
if (!$stmtEmp) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}
$stmtEmp->bind_param("s", $targetUser);
$stmtEmp->execute();
$resEmp = $stmtEmp->get_result();
$emp = $resEmp->fetch_assoc();
if (!$emp) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$baseRole = $emp['role'] ?? 'user';
$effective = getEffectivePermissionsForUserId($conn, $targetUser, $baseRole);

$check = $conn->query("SHOW TABLES LIKE 'user_permission_roles'");
$assignedRoles = [];
if ($check && $check->num_rows > 0) {
    $stmtRoles = $conn->prepare("SELECT upr.role_id FROM user_permission_roles upr WHERE upr.user_id = ?");
    if ($stmtRoles) {
        $stmtRoles->bind_param("s", $targetUser);
        $stmtRoles->execute();
        $resRoles = $stmtRoles->get_result();
        while ($row = $resRoles->fetch_assoc()) {
            $assignedRoles[] = (int)$row['role_id'];
        }
    }
}

$check2 = $conn->query("SHOW TABLES LIKE 'user_permissions'");
$custom = [];
if ($check2 && $check2->num_rows > 0) {
    $stmtPerm = $conn->prepare("SELECT permission_key, is_granted FROM user_permissions WHERE user_id = ?");
    if ($stmtPerm) {
        $stmtPerm->bind_param("s", $targetUser);
        $stmtPerm->execute();
        $resPerm = $stmtPerm->get_result();
        while ($row = $resPerm->fetch_assoc()) {
            $custom[] = [
                'key' => $row['permission_key'],
                'is_granted' => (int)$row['is_granted'] === 1
            ];
        }
    }
}

echo json_encode([
    'status' => 'success',
    'user' => [
        'id' => $emp['id'],
        'name' => $emp['name'],
        'name_th' => $emp['name_th'],
        'base_role' => $baseRole
    ],
    'effective_permissions' => $effective,
    'assigned_role_ids' => $assignedRoles,
    'custom_permissions' => $custom
]);
