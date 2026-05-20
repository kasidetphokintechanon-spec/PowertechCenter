<?php
// api/actions/export_assets.php
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT a.*, c.cat_name, d.dept_name FROM it_assets a 
        LEFT JOIN asset_categories c ON a.asset_category = c.cat_id 
        LEFT JOIN departments d ON a.owning_department = d.dept_id 
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($category)) {
    $sql .= " AND c.cat_name = ?";
    $params[] = $category;
    $types .= "s";
}
if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}
if (!empty($search)) {
    $searchTerm = "%$search%";
    $sql .= " AND (a.equipment_id LIKE ? OR a.name LIKE ? OR a.serial_number LIKE ? OR a.asset_id LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=assets_export_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM for Excel
fputcsv($output, ['Equipment ID', 'Asset ID', 'Name', 'Brand', 'Model', 'Serial Number', 'Category', 'Company', 'Department', 'Location', 'Status', 'Purchase Date', 'Price', 'Warranty Expires']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['equipment_id'],
        $row['asset_id'],
        $row['name'],
        $row['brand'],
        $row['model'],
        $row['serial_number'],
        $row['cat_name'],
        $row['company'],
        $row['dept_name'] ?? $row['owning_department'],
        $row['location'],
        $row['status'],
        $row['purchase_date'],
        $row['purchase_price'],
        $row['warranty_expires_on']
    ]);
}
fclose($output);
exit;
?>