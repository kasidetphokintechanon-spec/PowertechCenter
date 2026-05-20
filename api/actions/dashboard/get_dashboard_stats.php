<?php
// api/actions/get_dashboard_stats.php

$company = $_GET['company'] ?? 'all';
$where = "";
$whereAnd = "";

if ($company !== 'all') {
    $safeCompany = $conn->real_escape_string($company);
    $where = " WHERE company = '$safeCompany'";
    $whereAnd = " AND company = '$safeCompany'";
}

$stats = [];

// 1. Monthly Cases
$res = $conn->query("SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count FROM it_logs $where GROUP BY month ORDER BY month DESC LIMIT 6");
$stats['monthly'] = $res ? array_reverse($res->fetch_all(MYSQLI_ASSOC)) : [];

// 2. Status
$res = $conn->query("SELECT status, COUNT(*) as count FROM it_logs $where GROUP BY status");
$stats['by_status'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 3. Type
$res = $conn->query("SELECT type, COUNT(*) as count FROM it_logs $where GROUP BY type");
$stats['by_type'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 4. Company
$res = $conn->query("SELECT company, COUNT(*) as count FROM it_logs $where GROUP BY company");
$stats['by_company'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 5. Technician
$res = $conn->query("SELECT servicedBy, COUNT(*) as count FROM it_logs WHERE servicedBy IS NOT NULL AND servicedBy != '' $whereAnd GROUP BY servicedBy ORDER BY count DESC LIMIT 10");
$stats['by_technician'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 6. Expiring Warranty
$assetWhere = "";
if ($company !== 'all') {
    $safeCompany = $conn->real_escape_string($company);
    $assetWhere = " AND company = '$safeCompany'";
}
$res = $conn->query("SELECT equipment_id, name, warranty_expires_on FROM it_assets WHERE warranty_expires_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) $assetWhere ORDER BY warranty_expires_on ASC");
$stats['expiring_warranty'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 7. Rating
$res = $conn->query("SELECT rating, COUNT(*) as count FROM it_logs WHERE rating IS NOT NULL $whereAnd GROUP BY rating");
$stats['rating_distribution'] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 8. Loan Monthly
$loanWhere = "";
if ($company !== 'all') {
    $safeCompany = $conn->real_escape_string($company);
    $loanWhere = " WHERE a.company = '$safeCompany'";
}
$sql = "SELECT DATE_FORMAT(h.lend_date, '%Y-%m') as month, COUNT(*) as count 
        FROM it_loan_history h 
        LEFT JOIN it_assets a ON h.equipment_id = a.equipment_id 
        $loanWhere
        GROUP BY month ORDER BY month DESC LIMIT 6";
$res = $conn->query($sql);
$stats['loan_monthly'] = $res ? array_reverse($res->fetch_all(MYSQLI_ASSOC)) : [];

// 9. MTTR Monthly (hours)
$sql = "SELECT DATE_FORMAT(completed_date, '%Y-%m') as month,
        ROUND(AVG(IF(total_work_duration_seconds IS NOT NULL AND total_work_duration_seconds > 0,
                      total_work_duration_seconds,
                      TIMESTAMPDIFF(SECOND, date, completed_date)))/3600, 2) AS avg_hours,
        COUNT(*) as count
        FROM it_logs
        WHERE status = 'Completed' AND completed_date IS NOT NULL $whereAnd
        GROUP BY month
        ORDER BY month DESC
        LIMIT 6";
$res = $conn->query($sql);
$stats['mttr_monthly'] = $res ? array_reverse($res->fetch_all(MYSQLI_ASSOC)) : [];

// 10. MTTR Overall (hours)
$sql = "SELECT ROUND(AVG(IF(total_work_duration_seconds IS NOT NULL AND total_work_duration_seconds > 0,
                      total_work_duration_seconds,
                      TIMESTAMPDIFF(SECOND, date, completed_date)))/3600, 2) AS avg_hours
        FROM it_logs
        WHERE status = 'Completed' AND completed_date IS NOT NULL $whereAnd";
$res = $conn->query($sql);
$row = $res ? $res->fetch_assoc() : ['avg_hours' => null];
$stats['mttr_overall'] = $row ?: ['avg_hours' => null];

// 11. Aging Buckets for open tickets
$sql = "SELECT
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) <= 1 THEN 1 ELSE 0 END) AS d_0_1,
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) BETWEEN 2 AND 3 THEN 1 ELSE 0 END) AS d_2_3,
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) BETWEEN 4 AND 7 THEN 1 ELSE 0 END) AS d_4_7,
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) BETWEEN 8 AND 14 THEN 1 ELSE 0 END) AS d_8_14,
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) BETWEEN 15 AND 30 THEN 1 ELSE 0 END) AS d_15_30,
        SUM(CASE WHEN TIMESTAMPDIFF(DAY, date, NOW()) > 30 THEN 1 ELSE 0 END) AS d_30_plus
        FROM it_logs
        WHERE (status IS NULL OR status NOT IN ('Completed','Cancelled')) $whereAnd";
$res = $conn->query($sql);
$stats['aging_buckets'] = $res ? $res->fetch_assoc() : [];

echo json_encode(['status' => 'success', 'data' => $stats]);
?>
