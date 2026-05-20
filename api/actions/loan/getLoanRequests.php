<?php
// api/actions/getLoanRequests.php
$company_filter = $_GET['company'] ?? '';
$sql = "SELECT 
            a.id as loan_id,
            a.equipment_id,
            a.name as asset_name,
            a.image_url as image_path,
            a.borrower as borrower_name,
            a.borrower_id,
            a.borrower_id as asset_borrower_id,
            a.department,
            a.purpose,
            a.return_date,
            CASE 
                WHEN a.status = 'รออนุมัติ' THEN 'Pending'
                WHEN a.status = 'ถูกยืม' THEN 'Active'
                ELSE a.status
            END as loan_status,
            e.image as borrower_image,
            e.id as employee_id
        FROM it_assets a
        LEFT JOIN employees e ON a.borrower_id = e.id
        WHERE a.status IN ('รออนุมัติ', 'ถูกยืม')";

if (!empty($company_filter) && $company_filter !== 'All') {
    $escaped_company = $conn->real_escape_string($company_filter);
    $sql .= " AND a.company = '$escaped_company'";
}

$sql .= " ORDER BY a.id DESC";

$result = $conn->query($sql);
if (!$result) throw new Exception("SQL Error in getLoanRequests: " . $conn->error);

$data = [];
while ($row = $result->fetch_assoc()) {
    $v = trim((string)($row['image_path'] ?? ''));
    if ($v !== '') {
        if (strpos($v, 'uploads/') === 0) {
            $row['image_path'] = $v;
        } else {
            $path = parse_url($v, PHP_URL_PATH);
            if (!$path) $path = $v;
            $path = ltrim($path, '/');
            $pos = strpos($path, 'uploads/');
            if ($pos !== false) $row['image_path'] = substr($path, $pos);
        }
    }
    $data[] = $row;
}
echo json_encode($data);
?>
