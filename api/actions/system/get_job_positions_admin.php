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

require_once __DIR__ . '/../../../includes/job_position_levels.php';
ensureJobPositionLevelColumn($conn);

$result = $conn->query("SELECT id, name, sort_order, org_level, is_active, created_at, updated_at
                        FROM job_positions
                        WHERE is_active = 1
                        ORDER BY sort_order ASC, name ASC");
$positions = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        // org_level = ระดับในผังองค์กร (ที่ HR ตั้ง หรือเดาจากชื่อ), org_level_set = HR ตั้งเองหรือยัง
        $r['org_level_set'] = $r['org_level'] !== null ? 1 : 0;
        $r['org_level'] = effectiveOrgLevel($r['org_level'], (string)$r['name']);
        $positions[] = $r;
    }
}

echo json_encode(['status' => 'success', 'positions' => $positions]);
