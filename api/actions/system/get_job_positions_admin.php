<?php
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

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

$result = $conn->query("SELECT id, name, sort_order, is_active, created_at, updated_at
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
