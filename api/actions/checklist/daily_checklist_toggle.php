<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
$sessionCompany = $_SESSION['company'] ?? '';
$role = $_SESSION['role'] ?? 'guest';

$raw = $_POST['data'] ?? '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$itemId = isset($data['item_id']) ? (int)$data['item_id'] : 0;
$date = $data['date'] ?? date('Y-m-d');
$company = $data['company'] ?? $sessionCompany;
$markDone = isset($data['done']) ? (bool)$data['done'] : true;
$notes = trim($data['notes'] ?? '');

if ($itemId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid item id']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$allowedCompanies = ['', 'PTA', 'PT4', 'PTE'];
if (!in_array($company, $allowedCompanies, true)) {
    $company = $sessionCompany;
}

if ($company === '' && $role !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบบริษัทใน Session']);
    exit;
}

$stmtItem = $conn->prepare("SELECT id, company FROM daily_checklist_items WHERE id = ? AND is_active = 1");
if (!$stmtItem) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}
$stmtItem->bind_param("i", $itemId);
$stmtItem->execute();
$resItem = $stmtItem->get_result();
$item = $resItem->fetch_assoc();

if (!$item) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการเช็คลิสต์']);
    exit;
}

$itemCompany = $item['company'] ?? '';
if ($itemCompany && $company && $itemCompany !== $company) {
    echo json_encode(['status' => 'error', 'message' => 'บริษัทไม่ตรงกับรายการเช็คลิสต์']);
    exit;
}

if ($markDone) {
    $stmt = $conn->prepare("INSERT INTO daily_checklist_logs (checklist_item_id, checklist_date, company, user_id, user_name, checked_at, notes)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), user_name = VALUES(user_name), checked_at = VALUES(checked_at), notes = VALUES(notes)");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("isssss", $itemId, $date, $company, $userId, $userName, $notes);
    $ok = $stmt->execute();
} else {
    $stmt = $conn->prepare("DELETE FROM daily_checklist_logs WHERE checklist_item_id = ? AND checklist_date = ?");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param("is", $itemId, $date);
    $ok = $stmt->execute();
}

if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    exit;
}

$stmt2 = $conn->prepare("SELECT id, checklist_item_id, checklist_date, company, user_id, user_name, checked_at, notes FROM daily_checklist_logs WHERE checklist_item_id = ? AND checklist_date = ?");
if (!$stmt2) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    exit;
}
$stmt2->bind_param("is", $itemId, $date);
$stmt2->execute();
$res = $stmt2->get_result();
$log = $res->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'item_id' => $itemId,
    'date' => $date,
    'company' => $company,
    'done' => $markDone && $log ? true : false,
    'log' => $log
]);

