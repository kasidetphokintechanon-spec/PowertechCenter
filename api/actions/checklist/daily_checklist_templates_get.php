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

$company = $_GET['company'] ?? $sessionCompany;
$showInactive = ($_GET['show_inactive'] ?? '0') === '1';

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) {
    $company = $sessionCompany;
}

$where = [];
$params = [];
$types = '';

if ($company && $company !== 'all') {
    $where[] = "company = ?";
    $params[] = $company;
    $types .= 's';
}

if (!$showInactive) {
    $where[] = "is_active = 1";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT id, company, title, description, category, sort_order, is_active, created_at, updated_at
        FROM daily_checklist_items
        $whereSql
        ORDER BY company, sort_order, id";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode(['status' => 'success', 'items' => $rows]);

