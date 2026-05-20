<?php
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(10) NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL,
        tax_id VARCHAR(50) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        contact_person VARCHAR(255) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_company_name (company, name),
        KEY idx_name (name),
        KEY idx_company (company),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) throw new Exception('Missing supplier id');

$stmt = $conn->prepare("UPDATE suppliers SET is_active = 0 WHERE id = ?");
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
$stmt->bind_param("i", $id);
if (!$stmt->execute()) throw new Exception("Delete failed: " . $stmt->error);

echo json_encode(['status' => 'success']);
