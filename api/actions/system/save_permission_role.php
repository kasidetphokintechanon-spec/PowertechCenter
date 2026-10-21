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

$id = intval($_POST['id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$rawPermissions = $_POST['permissions'] ?? '[]';
$permissions = json_decode($rawPermissions, true);
if (!is_array($permissions)) $permissions = [];

if ($name === '') {
    throw new Exception('Role name is required');
}
if (mb_strlen($name, 'UTF-8') > 100) {
    throw new Exception('Role name is too long');
}
if (mb_strlen($description, 'UTF-8') > 255) {
    throw new Exception('Description is too long');
}

$allowed = array_map(function ($item) {
    return (string)($item['key'] ?? '');
}, getPowertechPermissionDefinitions());
$allowed[] = '*';
$allowedMap = array_fill_keys($allowed, true);

$cleanPermissions = [];
foreach ($permissions as $perm) {
    $key = trim((string)$perm);
    if ($key === '' || !isset($allowedMap[$key])) continue;
    $cleanPermissions[] = $key;
}
$cleanPermissions = array_values(array_unique($cleanPermissions));
$permissionsJson = json_encode($cleanPermissions, JSON_UNESCAPED_UNICODE);
if ($permissionsJson === false) {
    throw new Exception('Invalid permissions payload');
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE permission_roles SET name = ?, description = ?, permissions_json = ? WHERE id = ?");
    if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
    $stmt->bind_param("sssi", $name, $description, $permissionsJson, $id);
    if (!$stmt->execute()) throw new Exception('Update failed: ' . $stmt->error);
    echo json_encode([
        'status' => 'success',
        'role' => [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'permissions' => $cleanPermissions
        ]
    ]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO permission_roles (name, description, permissions_json) VALUES (?, ?, ?)");
if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
$stmt->bind_param("sss", $name, $description, $permissionsJson);
if (!$stmt->execute()) throw new Exception('Insert failed: ' . $stmt->error);

echo json_encode([
    'status' => 'success',
    'role' => [
        'id' => intval($stmt->insert_id),
        'name' => $name,
        'description' => $description,
        'permissions' => $cleanPermissions
    ]
]);

