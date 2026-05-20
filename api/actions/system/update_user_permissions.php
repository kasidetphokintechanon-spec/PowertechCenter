<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin' && !hasPermission($conn, 'admin.manage_permissions')) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

if (!permissionTablesExist($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Permission tables not initialized']);
    exit;
}

$targetUser = $_POST['user_id'] ?? '';
$rawRoles = $_POST['role_ids'] ?? '[]';
$rawPerms = $_POST['permissions'] ?? '[]';

$roleIds = json_decode($rawRoles, true);
$permKeys = json_decode($rawPerms, true);

if (!is_string($targetUser) || $targetUser === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid user id']);
    exit;
}
if (!is_array($roleIds)) $roleIds = [];
if (!is_array($permKeys)) $permKeys = [];

$stmtEmp = $conn->prepare("SELECT id FROM employees WHERE id = ? LIMIT 1");
if (!$stmtEmp) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}
$stmtEmp->bind_param("s", $targetUser);
$stmtEmp->execute();
$resEmp = $stmtEmp->get_result();
if (!$resEmp->fetch_assoc()) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), function($v) { return $v > 0; })));
$permKeys = array_values(array_unique(array_filter(array_map('strval', $permKeys), function($v) { return $v !== ''; })));

$conn->begin_transaction();
try {
    $stmtDelRoles = $conn->prepare("DELETE FROM user_permission_roles WHERE user_id = ?");
    if ($stmtDelRoles) {
        $stmtDelRoles->bind_param("s", $targetUser);
        $stmtDelRoles->execute();
    }
    if (!empty($roleIds)) {
        $stmtInsRole = $conn->prepare("INSERT INTO user_permission_roles (user_id, role_id) VALUES (?, ?)");
        if ($stmtInsRole) {
            foreach ($roleIds as $rid) {
                $stmtInsRole->bind_param("si", $targetUser, $rid);
                $stmtInsRole->execute();
            }
        }
    }

    $stmtDelPerms = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ?");
    if ($stmtDelPerms) {
        $stmtDelPerms->bind_param("s", $targetUser);
        $stmtDelPerms->execute();
    }
    if (!empty($permKeys)) {
        $stmtInsPerm = $conn->prepare("INSERT INTO user_permissions (user_id, permission_key, is_granted) VALUES (?, ?, 1)");
        if ($stmtInsPerm) {
            foreach ($permKeys as $pk) {
                $stmtInsPerm->bind_param("ss", $targetUser, $pk);
                $stmtInsPerm->execute();
            }
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
