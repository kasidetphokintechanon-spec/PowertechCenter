<?php
require_once __DIR__ . '/../config.php';

$allow = getenv('PTC_ALLOW_TEST_SEED') ?: '';
$pw = getenv('PTC_TEST_PASSWORD') ?: '';
if ($allow !== '1') {
    fwrite(STDERR, "PTC_ALLOW_TEST_SEED=1 is required.\n");
    exit(2);
}
if ($pw === '') {
    fwrite(STDERR, "PTC_TEST_PASSWORD is required.\n");
    exit(2);
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    fwrite(STDERR, "DB connection failed: {$conn->connect_error}\n");
    exit(1);
}
$conn->set_charset("utf8mb4");

$accounts = [
    [
        'id' => 'TST_ADMIN',
        'name' => 'Test Admin',
        'username' => 'tst_admin',
        'role' => 'admin',
        'assignments' => [
            ['company' => 'PTA', 'department' => 'IT', 'email' => 'tst_admin@pta.local', 'is_primary' => 1],
            ['company' => 'PT4', 'department' => 'IT', 'email' => 'tst_admin@pt4.local', 'is_primary' => 0],
            ['company' => 'PTE', 'department' => 'IT', 'email' => 'tst_admin@pte.local', 'is_primary' => 0]
        ]
    ],
    [
        'id' => 'TST_STAFF_PTA',
        'name' => 'Test Staff PTA',
        'username' => 'tst_staff_pta',
        'role' => 'staff',
        'assignments' => [
            ['company' => 'PTA', 'department' => 'IT', 'email' => 'tst_staff_pta@pta.local', 'is_primary' => 1]
        ]
    ],
    [
        'id' => 'TST_STAFF_PT4',
        'name' => 'Test Staff PT4',
        'username' => 'tst_staff_pt4',
        'role' => 'staff',
        'assignments' => [
            ['company' => 'PT4', 'department' => 'IT', 'email' => 'tst_staff_pt4@pt4.local', 'is_primary' => 1]
        ]
    ],
    [
        'id' => 'TST_STAFF_PTE',
        'name' => 'Test Staff PTE',
        'username' => 'tst_staff_pte',
        'role' => 'staff',
        'assignments' => [
            ['company' => 'PTE', 'department' => 'IT', 'email' => 'tst_staff_pte@pte.local', 'is_primary' => 1]
        ]
    ],
    [
        'id' => 'TST_USER_PTA',
        'name' => 'Test User PTA',
        'username' => 'tst_user_pta',
        'role' => 'user',
        'assignments' => [
            ['company' => 'PTA', 'department' => 'IT', 'email' => 'tst_user_pta@pta.local', 'is_primary' => 1]
        ]
    ],
    [
        'id' => 'TST_USER_PT4',
        'name' => 'Test User PT4',
        'username' => 'tst_user_pt4',
        'role' => 'user',
        'assignments' => [
            ['company' => 'PT4', 'department' => 'IT', 'email' => 'tst_user_pt4@pt4.local', 'is_primary' => 1]
        ]
    ],
    [
        'id' => 'TST_USER_PTE',
        'name' => 'Test User PTE',
        'username' => 'tst_user_pte',
        'role' => 'user',
        'assignments' => [
            ['company' => 'PTE', 'department' => 'IT', 'email' => 'tst_user_pte@pte.local', 'is_primary' => 1]
        ]
    ]
];

$passwordHash = password_hash($pw, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    fwrite(STDERR, "Failed to hash password.\n");
    exit(1);
}

$conn->begin_transaction();
try {
    foreach ($accounts as $acc) {
        $stmt = $conn->prepare("INSERT INTO employees (id, name, username, password, role, employment_status) VALUES (?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE name=VALUES(name), username=VALUES(username), password=VALUES(password), role=VALUES(role), employment_status='active'");
        if (!$stmt) throw new Exception("Prepare failed (employees): " . $conn->error);
        $stmt->bind_param("sssss", $acc['id'], $acc['name'], $acc['username'], $passwordHash, $acc['role']);
        if (!$stmt->execute()) throw new Exception("Execute failed (employees): " . $stmt->error);

        $stmtDel = $conn->prepare("DELETE FROM employee_assignments WHERE employee_id = ?");
        if (!$stmtDel) throw new Exception("Prepare failed (assignments delete): " . $conn->error);
        $stmtDel->bind_param("s", $acc['id']);
        if (!$stmtDel->execute()) throw new Exception("Execute failed (assignments delete): " . $stmtDel->error);

        $stmtIns = $conn->prepare("INSERT INTO employee_assignments (employee_id, company, department, email, is_primary) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtIns) throw new Exception("Prepare failed (assignments insert): " . $conn->error);
        foreach ($acc['assignments'] as $a) {
            $isPrimary = (int)$a['is_primary'];
            $stmtIns->bind_param("ssssi", $acc['id'], $a['company'], $a['department'], $a['email'], $isPrimary);
            if (!$stmtIns->execute()) throw new Exception("Execute failed (assignments insert): " . $stmtIns->error);
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Seeded test users successfully.\n";
echo "Usernames:\n";
foreach ($accounts as $acc) {
    echo "- {$acc['username']} ({$acc['role']})\n";
}
?>

