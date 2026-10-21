<?php
// api/actions/getLoanRequests.php
$conn->query("ALTER TABLE it_assets ADD COLUMN IF NOT EXISTS checkout_time DATETIME NULL");

$company_filter = $_GET['company'] ?? '';
$base_sql = "SELECT
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
            a.checkout_time,
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
    $stmt = $conn->prepare($base_sql . " AND a.company = ? ORDER BY a.id DESC");
    if (!$stmt) throw new Exception("Prepare failed in getLoanRequests: " . $conn->error);
    $stmt->bind_param("s", $company_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_sql . " ORDER BY a.id DESC");
    if (!$result) throw new Exception("SQL Error in getLoanRequests: " . $conn->error);
}

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
