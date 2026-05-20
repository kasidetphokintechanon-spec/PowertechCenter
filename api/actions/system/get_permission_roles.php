<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$check = $conn->query("SHOW TABLES LIKE 'permission_roles'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(['status' => 'success', 'roles' => []]);
    exit;
}

$res = $conn->query("SELECT id, name, description, permissions_json FROM permission_roles ORDER BY id ASC");
$roles = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $list = [];
        if (!empty($row['permissions_json'])) {
            $decoded = json_decode($row['permissions_json'], true);
            if (is_array($decoded)) $list = $decoded;
        }
        $roles[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'permissions' => $list
        ];
    }
}

echo json_encode(['status' => 'success', 'roles' => $roles]);
