<?php
// api/actions/generate_next_asset_id.php

$company = trim($_REQUEST['company'] ?? 'PTA');
$deptId = (int)($_REQUEST['department_id'] ?? 0);
$catId = (int)($_REQUEST['category_id'] ?? 0);

$deptAbbr = 'XX';
$catAbbr = 'XX';

if ($deptId > 0) {
    $stmt = $conn->prepare('SELECT dept_abbr FROM departments WHERE dept_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $deptId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['dept_abbr'])) $deptAbbr = $row['dept_abbr'];
    }
}

if ($catId > 0) {
    $stmt = $conn->prepare('SELECT cat_abbr FROM asset_categories WHERE cat_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $catId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['cat_abbr'])) $catAbbr = $row['cat_abbr'];
    }
}

$prefix = "$company-$deptAbbr-$catAbbr-";
$like = $prefix . '%';
$stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(equipment_id, '-', -1) AS UNSIGNED)) AS max_num FROM it_assets WHERE equipment_id LIKE ?");
$maxNum = 0;
if ($stmt) {
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $maxNum = (int)($row['max_num'] ?? 0);
}

echo json_encode(['status' => 'success', 'new_id' => $prefix . str_pad((string)($maxNum + 1), 4, '0', STR_PAD_LEFT)]);
?>
