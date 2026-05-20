<?php
// api/actions/get_job_plans.php

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$company = $_GET['company'] ?? '';

$sql = "SELECT * FROM job_tickets WHERE ((MONTH(deadline) = ? AND YEAR(deadline) = ?) OR (deadline IS NULL AND MONTH(last_update) = ? AND YEAR(last_update) = ?))";
$params = [$month, $year, $month, $year];
$types = "ssss";

if (!empty($company) && $company !== 'all') {
    $sql .= " AND company = ?";
    $params[] = $company;
    $types .= "s";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$i = 1;
while ($row = $result->fetch_assoc()) {
    $refDate = $row['deadline'] ? $row['deadline'] : $row['last_update'];
    $day = (int)date('j', strtotime($refDate));
    
    // Mock Plan: 2 days duration ending on deadline
    $planStart = max(1, $day - 2);
    $planEnd = $day;
    
    $actual = null;
    if ($row['is_done']) {
        $actual = ['start' => $planStart, 'end' => $planEnd];
    } elseif ($row['is_doing']) {
        $actual = ['start' => $planStart, 'end' => min((int)date('j'), 31)];
    }

    $data[] = ['no' => $i++, 'dateReq' => date('Y-m-d', strtotime($row['last_update'])), 'docNo' => 'JOB-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT), 'desc' => $row['job_name'], 'resp' => $row['responsible'] ?? '-', 'loc' => $row['building'] ?? '-', 'plan' => ['start' => $planStart, 'end' => $planEnd], 'actual' => $actual];
}
echo json_encode($data);
?>