<?php

$conn->query(
    "CREATE TABLE IF NOT EXISTS job_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 999,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_position_name (name),
        KEY idx_sort_order (sort_order),
        KEY idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$seed = [
    'Managing Director',
    'Executive Director and General Manager For Administration Division',
    'Digital Transformation Analyst Manager',
    'Senior Manager',
    'Manager',
    'Manager (Acting)',
    'Plan Manager',
    'Assistant Manger',
    'Head of General Administratoin Division',
    'Head of Operations',
    'Head of Testing',
    'Senior Supervisor',
    'Supervisor (Lv3)',
    'Supervisor (Lv2)',
    'Supervisor (Lv1)',
    'Senior Officer',
    'Officer',
    'Programmer',
    'Senior Programmer',
    'Engineer(Lv3)',
    'Engineer(Lv2)',
    'Engineer(Lv1)',
    'Senior Technician',
    'Senior Springer',
    'Springer',
    'Staff',
    'Factory and Mainteanace'
];

$checkSeed = $conn->query("SELECT COUNT(*) AS c FROM job_positions");
$seedCount = ($checkSeed && ($rowSeed = $checkSeed->fetch_assoc())) ? intval($rowSeed['c']) : 0;
if ($seedCount === 0) {
    $stmtSeed = $conn->prepare("INSERT IGNORE INTO job_positions (name, sort_order, is_active) VALUES (?, ?, 1)");
    if ($stmtSeed) {
        foreach ($seed as $idx => $name) {
            $order = $idx + 1;
            $stmtSeed->bind_param("si", $name, $order);
            $stmtSeed->execute();
        }
    }
}

$conn->query("INSERT IGNORE INTO job_positions (name, sort_order, is_active)
              SELECT DISTINCT TRIM(position), 999, 1
              FROM employees
              WHERE position IS NOT NULL AND TRIM(position) <> ''");

$result = $conn->query("SELECT id, name, sort_order, is_active
                        FROM job_positions
                        WHERE is_active = 1
                        ORDER BY sort_order ASC, name ASC");
$positions = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $positions[] = $r;
    }
}

echo json_encode(['status' => 'success', 'positions' => $positions]);
