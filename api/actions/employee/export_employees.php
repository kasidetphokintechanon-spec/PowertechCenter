<?php
// api/actions/export_employees.php
$company = $_GET['company'] ?? '';
$search = $_GET['search'] ?? '';

// Query ดึงข้อมูลพนักงานพร้อมสังกัดหลัก
$sql = "SELECT e.id, e.name, e.name_th, e.position, e.phone, ea.company, ea.department, ea.email 
        FROM employees e 
        LEFT JOIN employee_assignments ea ON e.id = ea.employee_id 
        WHERE e.employment_status = 'active' AND (ea.is_primary = 1 OR ea.is_primary IS NULL)";

if (!empty($company)) {
    $sql .= " AND ea.company = '" . $conn->real_escape_string($company) . "'";
}
if (!empty($search)) {
    $searchTerm = "%" . $conn->real_escape_string($search) . "%";
    $sql .= " AND (e.name LIKE '$searchTerm' OR e.name_th LIKE '$searchTerm' OR e.phone LIKE '$searchTerm')";
}

$sql .= " ORDER BY ea.company, ea.department, e.name";
$result = $conn->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Employee_Directory_' . ($company ? $company . '_' : '') . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // BOM for Excel Thai support
fputcsv($output, ['ID', 'Name (EN)', 'Name (TH)', 'Position', 'Company', 'Department', 'Phone', 'Email']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>