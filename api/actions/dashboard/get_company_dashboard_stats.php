<?php
// api/actions/get_company_dashboard_stats.php
$company = $_SESSION['company'] ?? 'PTA';
$safeCompany = $conn->real_escape_string($company);
$res = $conn->query("SELECT department, COUNT(*) as count FROM it_logs WHERE company = '$safeCompany' GROUP BY department ORDER BY count DESC");
$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['status' => 'success', 'company' => $company, 'data' => $data]);
?>