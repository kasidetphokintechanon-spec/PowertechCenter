<?php
// api/actions/dashboard_summary_get.php
// Executive Dashboard - สรุปภาพรวมงานทุกบริษัท/แผนก

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';

// กำหนดว่าจะดูได้กี่บริษัท
$targetCompanies = ['PTA', 'PT4', 'PTE'];
if ($role !== 'admin') {
    $targetCompanies = [$sessionCompany];
}

$filterCompany = $_GET['company'] ?? 'all';

// ========== PERSONAL TASKS SUMMARY ==========
// Dashboard ภาพรวมแสดงเฉพาะงานส่วนกลางเท่านั้น
$personalSummary = [];

// ========== JOB TICKETS SUMMARY ==========
$jobSummary = [];

$jobSql = "SELECT 
                company,
                COUNT(*) as total,
                SUM(CASE WHEN workflow_stage = 'receive' THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN workflow_stage = 'doing' THEN 1 ELSE 0 END) as doing_raw,
                SUM(CASE WHEN workflow_stage = 'send' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN workflow_stage = 'approve' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                SUM(CASE WHEN COALESCE(workflow_stage, 'todo') = 'todo' AND status <> 'done' THEN 1 ELSE 0 END) as todo,
                SUM(CASE WHEN status <> 'done' AND COALESCE(workflow_stage, 'todo') IN ('receive','doing','send','approve') THEN 1 ELSE 0 END) as doing,
                SUM(CASE WHEN due_date < CURDATE() AND status <> 'done' THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND status <> 'done' THEN 1 ELSE 0 END) as due_soon
            FROM personal_tasks
            WHERE COALESCE(task_type, 'personal') = 'job_ticket'";

$whereJob = [];
$paramsJob = [];
$typesJob = "";

if ($filterCompany !== 'all') {
    $whereJob[] = "company = ?";
    $paramsJob[] = $filterCompany;
    $typesJob .= "s";
} elseif ($role !== 'admin') {
    $whereJob[] = "company = ?";
    $paramsJob[] = $sessionCompany;
    $typesJob .= "s";
}

if (!empty($whereJob)) {
    $jobSql .= " AND " . implode(' AND ', $whereJob);
}
$jobSql .= " GROUP BY company";

if (!empty($paramsJob)) {
    $stmtJob = $conn->prepare($jobSql);
    if ($stmtJob) {
        $stmtJob->bind_param($typesJob, ...$paramsJob);
        $stmtJob->execute();
        $resJob = $stmtJob->get_result();
    }
} else {
    $resJob = $conn->query($jobSql);
}

if ($resJob) {
    while ($jRow = $resJob->fetch_assoc()) {
        $jRow['todo'] = (int)($jRow['todo'] ?? 0);
        $jRow['doing'] = (int)($jRow['doing'] ?? 0);
        $jRow['approved'] = (int)($jRow['approved'] ?? 0);
        $jRow['done'] = (int)($jRow['done'] ?? 0);
        $jRow['overdue'] = (int)($jRow['overdue'] ?? 0);
        $jRow['due_soon'] = (int)($jRow['due_soon'] ?? 0);
        $jRow['progress_pct'] = $jRow['total'] > 0 ? round($jRow['done'] / $jRow['total'] * 100) : 0;
        $jRow['departments'] = [];
        $jobSummary[] = $jRow;
    }
}

$jobDeptSql = "SELECT
                    company,
                    COALESCE(NULLIF(department, ''), 'ไม่ระบุ') AS department,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                    SUM(CASE WHEN status <> 'done' AND COALESCE(workflow_stage, 'todo') = 'todo' THEN 1 ELSE 0 END) as todo,
                    SUM(CASE WHEN status <> 'done' AND COALESCE(workflow_stage, 'todo') IN ('receive','doing','send','approve') THEN 1 ELSE 0 END) as doing,
                    SUM(CASE WHEN due_date < CURDATE() AND status <> 'done' THEN 1 ELSE 0 END) as overdue
               FROM personal_tasks
               WHERE COALESCE(task_type, 'personal') = 'job_ticket'";

if (!empty($whereJob)) {
    $jobDeptSql .= " AND " . implode(' AND ', $whereJob);
}
$jobDeptSql .= " GROUP BY company, department ORDER BY company, department";

$jobDeptMap = [];
if (!empty($paramsJob)) {
    $stmtJobDept = $conn->prepare($jobDeptSql);
    if ($stmtJobDept) {
        $stmtJobDept->bind_param($typesJob, ...$paramsJob);
        $stmtJobDept->execute();
        $resJobDept = $stmtJobDept->get_result();
    }
} else {
    $resJobDept = $conn->query($jobDeptSql);
}

if (!empty($resJobDept)) {
    while ($dRow = $resJobDept->fetch_assoc()) {
        $company = $dRow['company'] ?? '';
        if ($company === '') {
            continue;
        }
        $dRow['total'] = (int)($dRow['total'] ?? 0);
        $dRow['todo'] = (int)($dRow['todo'] ?? 0);
        $dRow['doing'] = (int)($dRow['doing'] ?? 0);
        $dRow['done'] = (int)($dRow['done'] ?? 0);
        $dRow['overdue'] = (int)($dRow['overdue'] ?? 0);
        $dRow['progress_pct'] = $dRow['total'] > 0 ? round($dRow['done'] / $dRow['total'] * 100) : 0;
        if (!isset($jobDeptMap[$company])) {
            $jobDeptMap[$company] = [];
        }
        $jobDeptMap[$company][] = $dRow;
    }
}

foreach ($jobSummary as &$jobRow) {
    $company = $jobRow['company'] ?? '';
    $jobRow['departments'] = $jobDeptMap[$company] ?? [];
}
unset($jobRow);

// ========== PENDING JOB TICKETS DETAIL ==========
$pendingJobTickets = [];

$pendingJobSql = "SELECT
                    id,
                    company,
                    COALESCE(NULLIF(department, ''), 'ไม่ระบุ') AS department,
                    building,
                    title AS job_name,
                    assignee_name AS responsible,
                    due_date AS deadline,
                    workflow_stage,
                    updated_at AS activity_at
                  FROM personal_tasks
                  WHERE COALESCE(task_type, 'personal') = 'job_ticket' AND status <> 'done'";

if (!empty($whereJob)) {
    $pendingJobSql .= " AND " . implode(' AND ', $whereJob);
}

$pendingJobSql .= " ORDER BY
                        CASE WHEN deadline < CURDATE() THEN 0
                             WHEN deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1
                             ELSE 2 END,
                        deadline IS NULL,
                        deadline ASC,
                        activity_at DESC
                    LIMIT 12";

if (!empty($paramsJob)) {
    $stmtPendingJob = $conn->prepare($pendingJobSql);
    if ($stmtPendingJob) {
        $stmtPendingJob->bind_param($typesJob, ...$paramsJob);
        $stmtPendingJob->execute();
        $resPendingJob = $stmtPendingJob->get_result();
    }
} else {
    $resPendingJob = $conn->query($pendingJobSql);
}

if (!empty($resPendingJob)) {
    while ($pRow = $resPendingJob->fetch_assoc()) {
        $status = 'ยังไม่เริ่ม';
        if (($pRow['workflow_stage'] ?? '') === 'approve') {
            $status = 'รออนุมัติ';
        } elseif (($pRow['workflow_stage'] ?? '') === 'send') {
            $status = 'ส่งแล้ว';
        } elseif (in_array(($pRow['workflow_stage'] ?? ''), ['receive', 'doing'], true)) {
            $status = 'กำลังดำเนินการ';
        }

        $deadlineClass = 'normal';
        if (!empty($pRow['deadline'])) {
            if ($pRow['deadline'] < date('Y-m-d')) {
                $deadlineClass = 'overdue';
            } elseif ($pRow['deadline'] <= date('Y-m-d', strtotime('+3 days'))) {
                $deadlineClass = 'soon';
            }
        }

        $pendingJobTickets[] = [
            'id' => (int)($pRow['id'] ?? 0),
            'company' => $pRow['company'] ?? '',
            'department' => $pRow['department'] ?? 'ไม่ระบุ',
            'building' => $pRow['building'] ?? '',
            'job_name' => $pRow['job_name'] ?? '',
            'responsible' => $pRow['responsible'] ?? '',
            'deadline' => $pRow['deadline'] ?? null,
            'deadline_class' => $deadlineClass,
            'status_label' => $status,
            'activity_at' => $pRow['activity_at'] ?? null
        ];
    }
}

// ========== TOP OVERDUE TASKS (งานเกินกำหนดรายบุคคลของงานส่วนกลาง) ==========
$overdueTasksSql = "SELECT 
                        COALESCE(NULLIF(pt.assignee_name, ''), e.name_th, e.name, 'ไม่ระบุ') AS person_name,
                        pt.company,
                        pt.department,
                        COUNT(*) as overdue_count
                    FROM personal_tasks pt
                    LEFT JOIN employees e ON pt.user_id = e.id
                    WHERE pt.due_date < CURDATE() AND pt.status != 'done'
                          AND COALESCE(pt.task_type, 'personal') = 'job_ticket'";

if ($role !== 'admin') {
    $overdueTasksSql .= " AND pt.company = '" . $conn->real_escape_string($sessionCompany) . "'";
}
$overdueTasksSql .= " GROUP BY person_name, pt.company, pt.department ORDER BY overdue_count DESC LIMIT 5";

$overdueRes = $conn->query($overdueTasksSql);
$overdueByPerson = [];
if ($overdueRes) {
    while ($oRow = $overdueRes->fetch_assoc()) {
        $overdueByPerson[] = $oRow;
    }
}

// ========== RECENT ACTIVITY (งานส่วนกลางที่ถูกอัปเดตล่าสุด) ==========
$recentSql = "SELECT 
                pt.id, pt.title, pt.status, pt.priority, pt.due_date, pt.company, pt.department,
                COALESCE(NULLIF(pt.assignee_name, ''), e.name_th, e.name, 'ไม่ระบุ') AS person_name,
                pt.updated_at,
                COALESCE(pt.workflow_stage, 'todo') AS workflow_stage
              FROM personal_tasks pt
              LEFT JOIN employees e ON pt.user_id = e.id
              WHERE COALESCE(pt.task_type, 'personal') = 'job_ticket'";

if ($role !== 'admin') {
    $recentSql .= " AND pt.company = '" . $conn->real_escape_string($sessionCompany) . "'";
}
$recentSql .= " ORDER BY pt.updated_at DESC LIMIT 10";

$recentRes = $conn->query($recentSql);
$recentActivity = [];
if ($recentRes) {
    while ($rRow = $recentRes->fetch_assoc()) {
        $recentActivity[] = $rRow;
    }
}

echo json_encode([
    'status' => 'success',
    'personal_summary' => $personalSummary,
    'job_summary' => $jobSummary,
    'pending_job_tickets' => $pendingJobTickets,
    'overdue_by_person' => $overdueByPerson,
    'recent_activity' => $recentActivity,
    'generated_at' => date('Y-m-d H:i:s')
]);
?>
