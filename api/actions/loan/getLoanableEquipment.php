<?php
// api/actions/loan/getLoanableEquipment.php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$allCompanies = isset($_GET['all_companies']) && $_GET['all_companies'] === '1';

$where = ['a.is_loanable = 1'];
$params = [];
$types = '';

if (!($allCompanies && $role === 'admin')) {
    if ($sessionCompany !== '') {
        $where[] = 'a.company = ?';
        $params[] = $sessionCompany;
        $types .= 's';
    }
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$sql = "SELECT 
            a.id AS db_id,
            a.equipment_id AS `ID อุปกรณ์`,
            a.name AS `ชื่ออุปกรณ์`,
            c.cat_name AS `หมวดหมู่`,
            a.serial_number AS `Serial Number`,
            a.status AS `สถานะ`,
            a.image_url AS `รูปภาพ (URL)`,
            a.company,
            a.borrower AS `ผู้ยืม`,
            a.spec_details AS `รายละเอียด`,
            a.brand,
            a.model,
            a.location,
            a.accessories,
            a.is_loanable,
            a.return_date,
            a.purpose
        FROM it_assets a
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
        {$whereSql}
        ORDER BY 
            CASE 
                WHEN a.status IN ('ว่าง', 'ใช้งานปกติ') THEN 0
                WHEN a.status = 'รออนุมัติ' THEN 1
                ELSE 2
            END,
            a.name ASC";

$normalizeUploadPath = function ($value) {
    $v = trim((string)($value ?? ''));
    if ($v === '') return '';
    if (strpos($v, 'uploads/') === 0) return $v;
    $path = parse_url($v, PHP_URL_PATH);
    if (!$path) $path = $v;
    $path = ltrim($path, '/');
    $pos = strpos($path, 'uploads/');
    if ($pos !== false) return substr($path, $pos);
    return $v;
};

$data = [];

if ($types !== '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['accessories'] = json_decode($row['accessories'] ?? '[]', true);
        if (!is_array($row['accessories'])) {
            $row['accessories'] = [];
        }
        if (isset($row['รูปภาพ (URL)'])) {
            $row['รูปภาพ (URL)'] = $normalizeUploadPath($row['รูปภาพ (URL)']);
        }
        $data[] = $row;
    }
}

echo json_encode($data);
