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

$templates = [
    [
        'name' => 'IT Staff',
        'description' => 'เข้าหน้า Admin, บันทึกงาน IT, จัดการพัสดุ/ยืมคืน และ KB',
        'permissions' => ['admin.access_admin', 'it_log.access', 'it_report.access', 'asset.manage', 'loan.manage', 'kb.manage'],
    ],
    [
        'name' => 'HR Staff',
        'description' => 'ดูแลพนักงานและประกาศวันหยุด',
        'permissions' => ['admin.access_admin', 'employee.manage'],
    ],
    [
        'name' => 'Stock Manager',
        'description' => 'ดูแลคลังสินค้าและงานยืมคืน',
        'permissions' => ['admin.access_admin', 'stock.manage', 'loan.manage'],
    ],
    [
        'name' => 'Permission Admin',
        'description' => 'จัดการสิทธิ์ผู้ใช้และแจ้งเตือนระบบ',
        'permissions' => ['admin.access_admin', 'admin.manage_permissions', 'settings.notifications'],
    ],
];

$allowed = array_map(function ($item) {
    return (string)($item['key'] ?? '');
}, getPowertechPermissionDefinitions());
$allowed[] = '*';
$allowedMap = array_fill_keys($allowed, true);

$inserted = 0;
$skipped = 0;

$stmtExists = $conn->prepare("SELECT id FROM permission_roles WHERE name = ? LIMIT 1");
$stmtInsert = $conn->prepare("INSERT INTO permission_roles (name, description, permissions_json) VALUES (?, ?, ?)");
if (!$stmtExists || !$stmtInsert) {
    throw new Exception('Prepare failed: ' . $conn->error);
}

foreach ($templates as $tpl) {
    $name = trim((string)$tpl['name']);
    $description = trim((string)($tpl['description'] ?? ''));
    $perms = [];
    foreach (($tpl['permissions'] ?? []) as $perm) {
        $key = trim((string)$perm);
        if ($key !== '' && isset($allowedMap[$key])) $perms[] = $key;
    }
    $perms = array_values(array_unique($perms));
    $permissionsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);
    if ($permissionsJson === false) {
        $permissionsJson = '[]';
    }

    $stmtExists->bind_param("s", $name);
    $stmtExists->execute();
    $exists = $stmtExists->get_result()->fetch_assoc();
    if ($exists) {
        $skipped++;
        continue;
    }

    $stmtInsert->bind_param("sss", $name, $description, $permissionsJson);
    if (!$stmtInsert->execute()) {
        throw new Exception('Insert failed: ' . $stmtInsert->error);
    }
    $inserted++;
}

echo json_encode([
    'status' => 'success',
    'inserted' => $inserted,
    'skipped' => $skipped,
]);

