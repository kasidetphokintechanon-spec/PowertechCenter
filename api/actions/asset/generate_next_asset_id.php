<?php
// api/actions/generate_next_asset_id.php

$company = $_REQUEST['company'] ?? 'PTA';
$deptId = $_REQUEST['department_id'] ?? '';
$catId = $_REQUEST['category_id'] ?? '';

$deptRes = $conn->query("SELECT dept_abbr FROM departments WHERE dept_id = '$deptId'");
$deptAbbr = ($deptRes && $row = $deptRes->fetch_assoc()) ? $row['dept_abbr'] : 'XX';

$catRes = $conn->query("SELECT cat_abbr FROM asset_categories WHERE cat_id = '$catId'");
$catAbbr = ($catRes && $row = $catRes->fetch_assoc()) ? $row['cat_abbr'] : 'XX';

$prefix = "$company-$deptAbbr-$catAbbr-";
$res = $conn->query("SELECT equipment_id FROM it_assets WHERE equipment_id LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
$lastId = ($res && $row = $res->fetch_assoc()) ? $row['equipment_id'] : '';

$nextNum = 1;
if ($lastId) {
    $parts = explode('-', $lastId);
    $lastNum = end($parts);
    if (is_numeric($lastNum)) $nextNum = intval($lastNum) + 1;
}
echo json_encode(['status' => 'success', 'new_id' => $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT)]);
?>