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
$company = strtoupper(trim($_POST['company'] ?? ''));
$name = trim($_POST['name'] ?? '');
$taxId = trim($_POST['tax_id'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$contactPerson = trim($_POST['contact_person'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$isActive = (($_POST['is_active'] ?? '1') === '1') ? 1 : 0;

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) $company = '';

if ($name === '') throw new Exception('กรุณากรอกชื่อผู้จำหน่าย');
if (mb_strlen($name) > 255) throw new Exception('ชื่อผู้จำหน่ายยาวเกินไป');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('รูปแบบอีเมลไม่ถูกต้อง');
if ($taxId !== '' && mb_strlen($taxId) > 50) throw new Exception('เลขผู้เสียภาษียาวเกินไป');
if ($phone !== '' && mb_strlen($phone) > 50) throw new Exception('เบอร์โทรยาวเกินไป');

if ($id > 0) {
    $sql = "UPDATE suppliers
            SET company=?, name=?, tax_id=?, phone=?, email=?, address=?, contact_person=?, notes=?, is_active=?
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("ssssssssii", $company, $name, $taxId, $phone, $email, $address, $contactPerson, $notes, $isActive, $id);
    if (!$stmt->execute()) {
        if (strpos($stmt->error, 'uniq_company_name') !== false) throw new Exception('ชื่อผู้จำหน่ายซ้ำในบริษัทเดียวกัน');
        throw new Exception("Update failed: " . $stmt->error);
    }
} else {
    $sql = "INSERT INTO suppliers (company, name, tax_id, phone, email, address, contact_person, notes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("ssssssssi", $company, $name, $taxId, $phone, $email, $address, $contactPerson, $notes, $isActive);
    if (!$stmt->execute()) {
        if (strpos($stmt->error, 'uniq_company_name') !== false) throw new Exception('ชื่อผู้จำหน่ายซ้ำในบริษัทเดียวกัน');
        throw new Exception("Insert failed: " . $stmt->error);
    }
    $id = $stmt->insert_id;
}

$stmt2 = $conn->prepare("SELECT id, company, name, tax_id, phone, email, address, contact_person, notes, is_active, created_at, updated_at FROM suppliers WHERE id = ?");
if (!$stmt2) throw new Exception("Prepare failed: " . $conn->error);
$stmt2->bind_param("i", $id);
$stmt2->execute();
$supplier = $stmt2->get_result()->fetch_assoc();

echo json_encode(['status' => 'success', 'supplier' => $supplier]);
