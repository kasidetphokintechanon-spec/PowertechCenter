<?php
// IT Report Stats API
// Parameters: company (all/PTA/PT4/PTE), year (e.g. 2026), months (1-24)

$company   = $_GET['company'] ?? 'all';
if (!in_array($company, ['all', 'PTA', 'PT4', 'PTE'], true)) $company = 'all';
$yearParam = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$months    = isset($_GET['months']) ? min(max(intval($_GET['months']), 1), 24) : 12;

// Build WHERE fragments — company is whitelisted above, safe to interpolate
$cWhere    = $company !== 'all' ? " AND company = '{$company}'" : '';
$yearWhere = " AND YEAR(date) = $yearParam";
$cYear     = $cWhere . $yearWhere;

// ── 1. KPI totals (year scope) ──────────────────────────────────────────────
$kpi = ['total'=>0,'completed'=>0,'open'=>0,'cancelled'=>0,
        'sla_pass'=>0,'sla_total'=>0,'mttr_hours'=>null,'avg_rating'=>null];

$r = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(status='Completed') AS completed,
    SUM(status NOT IN ('Completed','Cancelled')) AS open,
    SUM(status='Cancelled') AS cancelled,
    ROUND(AVG(IF(rating IS NOT NULL, rating, NULL)),2) AS avg_rating
    FROM it_logs WHERE 1=1 $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['total']      = intval($row['total']);
    $kpi['completed']  = intval($row['completed']);
    $kpi['open']       = intval($row['open']);
    $kpi['cancelled']  = intval($row['cancelled']);
    $kpi['avg_rating'] = $row['avg_rating'] ? floatval($row['avg_rating']) : null;
}

// SLA compliance
$r = $conn->query("SELECT
    SUM(CASE
        WHEN urgency IN ('ด่วนที่สุด','Critical') AND
             TIMESTAMPDIFF(HOUR, date, COALESCE(completed_date, NOW())) <= 4  THEN 1
        WHEN urgency IN ('ด่วน','Urgent') AND
             TIMESTAMPDIFF(HOUR, date, COALESCE(completed_date, NOW())) <= 24 THEN 1
        WHEN (urgency IS NULL OR urgency = 'ปกติ' OR urgency = 'Normal') AND
             TIMESTAMPDIFF(HOUR, date, COALESCE(completed_date, NOW())) <= 72 THEN 1
        ELSE 0 END) AS sla_pass,
    COUNT(*) AS sla_total
    FROM it_logs WHERE status IN ('Completed') $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['sla_pass']  = intval($row['sla_pass']);
    $kpi['sla_total'] = intval($row['sla_total']);
}

// MTTR (hours) — year scope
$r = $conn->query("SELECT ROUND(AVG(
    IF(total_work_duration_seconds > 0,
       total_work_duration_seconds,
       TIMESTAMPDIFF(SECOND, date, completed_date))
    )/3600, 1) AS avg_hours
    FROM it_logs WHERE status='Completed' AND completed_date IS NOT NULL $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['mttr_hours'] = $row['avg_hours'] ? floatval($row['avg_hours']) : null;
}

// ── 2. Monthly trend (N months back) ────────────────────────────────────────
$r = $conn->query("SELECT
    DATE_FORMAT(date,'%Y-%m') AS month,
    COUNT(*) AS total,
    SUM(status='Completed') AS completed,
    SUM(status NOT IN ('Completed','Cancelled')) AS open
    FROM it_logs
    WHERE date >= DATE_SUB(LAST_DAY(NOW()), INTERVAL $months MONTH) $cWhere
    GROUP BY month ORDER BY month ASC");
$monthly = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 3. By status (year scope) ───────────────────────────────────────────────
$r = $conn->query("SELECT status, COUNT(*) AS count FROM it_logs WHERE 1=1 $cYear GROUP BY status ORDER BY count DESC");
$by_status = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 4. By type (year scope) ─────────────────────────────────────────────────
$r = $conn->query("SELECT type, COUNT(*) AS count FROM it_logs WHERE 1=1 $cYear GROUP BY type ORDER BY count DESC LIMIT 12");
$by_type = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 5. By department (year scope, top 12) ───────────────────────────────────
$r = $conn->query("SELECT department, COUNT(*) AS count FROM it_logs WHERE department IS NOT NULL AND department != '' $cYear GROUP BY department ORDER BY count DESC LIMIT 12");
$by_department = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 6. By technician (year scope, top 10) ───────────────────────────────────
$r = $conn->query("SELECT servicedBy, COUNT(*) AS total,
    SUM(status='Completed') AS completed,
    ROUND(AVG(IF(total_work_duration_seconds>0,total_work_duration_seconds,
        TIMESTAMPDIFF(SECOND,date,completed_date)))/3600,1) AS avg_hours
    FROM it_logs WHERE servicedBy IS NOT NULL AND servicedBy != '' $cYear
    GROUP BY servicedBy ORDER BY total DESC LIMIT 10");
$by_technician = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 7. Urgency distribution (year scope) ────────────────────────────────────
$r = $conn->query("SELECT urgency, COUNT(*) AS count FROM it_logs WHERE 1=1 $cYear GROUP BY urgency ORDER BY count DESC");
$by_urgency = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 8. Aging buckets (open tickets only, no date filter) ───────────────────
$ageWhere = "WHERE status NOT IN ('Completed','Cancelled')" . $cWhere;
$r = $conn->query("SELECT
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) <= 1)          AS d_0_1,
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) BETWEEN 2 AND 3)   AS d_2_3,
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) BETWEEN 4 AND 7)   AS d_4_7,
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) BETWEEN 8 AND 14)  AS d_8_14,
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) BETWEEN 15 AND 30) AS d_15_30,
    SUM(TIMESTAMPDIFF(DAY,date,NOW()) > 30)              AS d_30_plus
    FROM it_logs $ageWhere");
$aging = $r ? $r->fetch_assoc() : [];

// ── 9. Rating distribution (year scope) ────────────────────────────────────
$r = $conn->query("SELECT rating, COUNT(*) AS count FROM it_logs WHERE rating IS NOT NULL $cYear GROUP BY rating ORDER BY rating");
$rating_dist = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

// ── 10. Year list for selector ──────────────────────────────────────────────
$r = $conn->query("SELECT DISTINCT YEAR(date) AS y FROM it_logs ORDER BY y DESC LIMIT 5");
$years = $r ? array_column($r->fetch_all(MYSQLI_ASSOC), 'y') : [intval(date('Y'))];

echo json_encode([
    'status' => 'success',
    'data'   => compact('kpi','monthly','by_status','by_type','by_department',
                        'by_technician','by_urgency','aging','rating_dist','years')
]);
