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

$term = trim($_GET['term'] ?? '');
$company = strtoupper(trim($_GET['company'] ?? ''));
$includeInactive = (($_GET['include_inactive'] ?? '0') === '1');
$limit = intval($_GET['limit'] ?? 200);
if ($limit < 1) $limit = 1;
if ($limit > 500) $limit = 500;

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) $company = '';

$where = [];
$params = [];
$types = '';

if (!$includeInactive) {
    $where[] = "is_active = 1";
}

if ($company !== '') {
    $where[] = "company = ?";
    $types .= 's';
    $params[] = $company;
}

if ($term !== '') {
    $where[] = "(name LIKE ? OR tax_id LIKE ? OR phone LIKE ? OR email LIKE ? OR contact_person LIKE ?)";
    $like = '%' . $term . '%';
    $types .= 'sssss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, company, name, tax_id, phone, email, address, contact_person, notes, is_active, created_at, updated_at
        FROM suppliers
        $whereSql
        ORDER BY is_active DESC, name ASC
        LIMIT ?";

$stmt = $conn->prepare($sql);
if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);

$types .= 'i';
$params[] = $limit;

$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode(['status' => 'success', 'suppliers' => $rows]);
