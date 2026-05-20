<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';

if (!in_array($role, ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

$raw = $_POST['data'] ?? '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$company = strtoupper(trim($data['company'] ?? $sessionCompany));
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$category = trim($data['category'] ?? '');
$sortOrder = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
$isActive = isset($data['is_active']) && (string)$data['is_active'] === '0' ? 0 : 1;

if ($title === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อรายการ']);
    exit;
}

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) {
    $company = $sessionCompany;
}

if ($company === '' && $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบบริษัทใน Session']);
    exit;
}

try {
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE daily_checklist_items SET company = ?, title = ?, description = ?, category = ?, sort_order = ?, is_active = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("ssssiis", $company, $title, $description, $category, $sortOrder, $isActive, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการสำหรับแก้ไขหรือข้อมูลเหมือนเดิม']);
            exit;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO daily_checklist_items (company, title, description, category, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("ssssii", $company, $title, $description, $category, $sortOrder, $isActive);
        $stmt->execute();
        $id = $stmt->insert_id;
    }

    $stmt2 = $conn->prepare("SELECT id, company, title, description, category, sort_order, is_active, created_at, updated_at FROM daily_checklist_items WHERE id = ?");
    if (!$stmt2) {
        throw new Exception($conn->error);
    }
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    $item = $res->fetch_assoc();

    echo json_encode(['status' => 'success', 'item' => $item]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

