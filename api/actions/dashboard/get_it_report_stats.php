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

// เวลาแจ้งจริง/รับเรื่อง/ช่างซ่อมเสร็จ — it_logs เก็บแค่วันที่ จึงดึงเวลาจากประวัติเหตุการณ์
// "ซ่อมเสร็จ" = ตอนช่างส่งให้ผู้แจ้งยืนยัน (ไม่รวมเวลาที่รอผู้แจ้งกดยืนยัน ซึ่งอาจนานถึง 3 วัน)
$evAt = fn($type) => "(SELECT MIN(e.created_at) FROM mt_ticket_events e WHERE e.log_id COLLATE utf8mb4_unicode_ci = l.id COLLATE utf8mb4_unicode_ci AND e.event_type = '$type')";
$T = "(SELECT l.*, COALESCE({$evAt('CREATE')}, TIMESTAMP(l.date, '08:00:00')) AS c_at, {$evAt('ACCEPT')} AS a_at,
        COALESCE(l.awaiting_confirmation_at, l.completed_date) AS f_at FROM it_logs l) t";

// เวลารับเรื่องตามที่ตั้งในหน้าตั้งค่า (ข้อความ เช่น "ภายใน 4 ชม.") → นาที · ใช้ค่าเดียวกับบอร์ดคิวงาน
$respMin = ['ปกติ' => 240, 'ด่วน' => 120, 'ด่วนที่สุด' => 30];
$sr = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sla_normal','sla_urgent','sla_critical')");
if ($sr) foreach ($sr->fetch_all(MYSQLI_ASSOC) as $row) {
    if (preg_match('/(\d+(?:\.\d+)?)\s*(ชม|ชั่วโมง|นาที|วัน)/u', (string)$row['setting_value'], $m)) {
        $min = (float)$m[1] * ($m[2] === 'นาที' ? 1 : ($m[2] === 'วัน' ? 1440 : 60));
        $respMin[['sla_normal' => 'ปกติ', 'sla_urgent' => 'ด่วน', 'sla_critical' => 'ด่วนที่สุด'][$row['setting_key']]] = (int)$min;
    }
}

// ── 1. KPI totals (year scope) ──────────────────────────────────────────────
$kpi = ['total'=>0,'completed'=>0,'open'=>0,'cancelled'=>0,
        'sla_pass'=>0,'sla_total'=>0,'mttr_hours'=>null,'avg_rating'=>null,
        'resp_avg_min'=>null,'resp_pass'=>0,'resp_total'=>0];

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

// SLA ปิดงาน: แจ้งจริง → ช่างซ่อมเสร็จ (ด่วนที่สุด 4 ชม. / ด่วน 24 ชม. / ปกติ 72 ชม.)
$r = $conn->query("SELECT
    SUM(CASE
        WHEN urgency IN ('ด่วนที่สุด','Critical') THEN TIMESTAMPDIFF(MINUTE, c_at, f_at) <= 240
        WHEN urgency IN ('ด่วน','Urgent')        THEN TIMESTAMPDIFF(MINUTE, c_at, f_at) <= 1440
        ELSE TIMESTAMPDIFF(MINUTE, c_at, f_at) <= 4320 END) AS sla_pass,
    COUNT(*) AS sla_total
    FROM $T WHERE status = 'Completed' AND f_at IS NOT NULL $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['sla_pass']  = intval($row['sla_pass']);
    $kpi['sla_total'] = intval($row['sla_total']);
}

// เวลาซ่อมเฉลี่ย (ชม.): แจ้งจริง → ช่างซ่อมเสร็จ
$r = $conn->query("SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, c_at, f_at))/3600, 1) AS avg_hours
    FROM $T WHERE status = 'Completed' AND f_at IS NOT NULL $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['mttr_hours'] = $row['avg_hours'] !== null ? floatval($row['avg_hours']) : null;
}

// เวลารับเรื่อง: แจ้ง → ช่างกดรับ (เฉพาะงานที่มีประวัติเวลาแจ้ง/รับจริง)
$rn = (int)$respMin['ปกติ']; $ru = (int)$respMin['ด่วน']; $rc = (int)$respMin['ด่วนที่สุด'];
$r = $conn->query("SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, c_at, a_at))) AS avg_min,
    SUM(TIMESTAMPDIFF(MINUTE, c_at, a_at) <= CASE WHEN urgency IN ('ด่วนที่สุด','Critical') THEN $rc WHEN urgency IN ('ด่วน','Urgent') THEN $ru ELSE $rn END) AS pass,
    COUNT(*) AS total
    FROM $T WHERE a_at IS NOT NULL $cYear");
if ($r && $row = $r->fetch_assoc()) {
    $kpi['resp_avg_min'] = $row['avg_min'] !== null ? (int)$row['avg_min'] : null;
    $kpi['resp_pass'] = (int)$row['pass'];
    $kpi['resp_total'] = (int)$row['total'];
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
    ROUND(AVG(IF(status='Completed', TIMESTAMPDIFF(SECOND, c_at, f_at), NULL))/3600,1) AS avg_hours
    FROM $T WHERE servicedBy IS NOT NULL AND servicedBy != '' $cYear
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
