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

$roleId = intval($_POST['id'] ?? 0);
if ($roleId <= 0) {
    throw new Exception('Missing role id');
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS permission_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description VARCHAR(255) DEFAULT NULL,
        permissions_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$conn->query(
    "CREATE TABLE IF NOT EXISTS user_permission_roles (
        user_id VARCHAR(50) NOT NULL,
        role_id INT NOT NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, role_id),
        KEY idx_upr_role (role_id),
        KEY idx_upr_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$conn->begin_transaction();
try {
    $stmtDetach = $conn->prepare("DELETE FROM user_permission_roles WHERE role_id = ?");
    if ($stmtDetach) {
        $stmtDetach->bind_param("i", $roleId);
        $stmtDetach->execute();
    }

    $stmtDelete = $conn->prepare("DELETE FROM permission_roles WHERE id = ?");
    if (!$stmtDelete) throw new Exception('Prepare failed: ' . $conn->error);
    $stmtDelete->bind_param("i", $roleId);
    if (!$stmtDelete->execute()) throw new Exception('Delete failed: ' . $stmtDelete->error);
    if ($stmtDelete->affected_rows === 0) throw new Exception('Role not found');

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

