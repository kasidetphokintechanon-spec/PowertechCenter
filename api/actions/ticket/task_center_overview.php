<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';
$companyFilter = trim($_GET['company'] ?? '');
$taskType = trim($_GET['task_type'] ?? '');

$where = [];
$params = [];
$types = '';

if ($role !== 'admin') {
    $visibilityParts = ["pt.user_id = ?"];
    $params[] = $userId;
    $types .= 's';

    if ($sessionCompany !== '' && $sessionDept !== '') {
        $visibilityParts[] = "(pt.share_scope = 'department' AND (pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0) AND pt.department = ?)";
        $params[] = $sessionCompany;
        $params[] = $sessionCompany;
        $params[] = $sessionDept;
        $types .= 'sss';
    }

    if ($sessionCompany !== '') {
        $visibilityParts[] = "(pt.share_scope = 'company' AND (pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0))";
        $params[] = $sessionCompany;
        $params[] = $sessionCompany;
        $types .= 'ss';
    }

    $where[] = '(' . implode(' OR ', $visibilityParts) . ')';
}

if ($companyFilter !== '' && $companyFilter !== 'all') {
    $where[] = "(pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0)";
    $params[] = $companyFilter;
    $params[] = $companyFilter;
    $types .= 'ss';
}

if ($taskType !== '' && in_array($taskType, ['personal', 'job_ticket'], true)) {
    $where[] = "COALESCE(pt.task_type, 'personal') = ?";
    $params[] = $taskType;
    $types .= 's';
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

function prepareAndRun($conn, $sql, $types, $params) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

try {
    $companySql = "SELECT
                        COALESCE(NULLIF(pt.company, ''), 'ไม่ระบุ') AS company,
                        COUNT(*) AS total,
                        SUM(CASE WHEN pt.status <> 'done' THEN 1 ELSE 0 END) AS open_count,
                        SUM(CASE WHEN pt.status = 'done' THEN 1 ELSE 0 END) AS done_count,
                        SUM(CASE WHEN pt.due_date < CURDATE() AND pt.status <> 'done' THEN 1 ELSE 0 END) AS overdue_count,
                        SUM(CASE WHEN COALESCE(pt.workflow_stage, 'todo') = 'approve' AND pt.status <> 'done' THEN 1 ELSE 0 END) AS approval_count
                   FROM personal_tasks pt" . $whereSql . "
                   GROUP BY COALESCE(NULLIF(pt.company, ''), 'ไม่ระบุ')
                   ORDER BY total DESC, company ASC
                   LIMIT 6";

    $agingSql = "SELECT
                    SUM(CASE WHEN pt.status <> 'done' AND pt.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) AS bucket_0_3,
                    SUM(CASE WHEN pt.status <> 'done' AND pt.due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 4 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS bucket_4_7,
                    SUM(CASE WHEN pt.status <> 'done' AND pt.due_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS bucket_8_14,
                    SUM(CASE WHEN pt.status <> 'done' AND pt.due_date > DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS bucket_15_plus,
                    SUM(CASE WHEN pt.status <> 'done' AND pt.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_bucket
                 FROM personal_tasks pt" . $whereSql;

    $ownerSql = "SELECT
                    COALESCE(NULLIF(pt.assignee_name, ''), NULLIF(e.name_th, ''), NULLIF(e.name, ''), pt.user_id) AS owner_name,
                    COUNT(*) AS overdue_count
                 FROM personal_tasks pt
                 LEFT JOIN employees e ON pt.user_id = e.id" . $whereSql .
                 ($whereSql ? " AND " : " WHERE ") . "pt.status <> 'done' AND pt.due_date < CURDATE()
                 GROUP BY COALESCE(NULLIF(pt.assignee_name, ''), NULLIF(e.name_th, ''), NULLIF(e.name, ''), pt.user_id)
                 ORDER BY overdue_count DESC, owner_name ASC
                 LIMIT 8";

    $approvalSql = "SELECT
                        pt.id,
                        pt.title,
                        pt.company,
                        pt.department,
                        pt.assignee_name,
                        pt.due_date,
                        pt.updated_at
                    FROM personal_tasks pt" . $whereSql .
                    ($whereSql ? " AND " : " WHERE ") . "COALESCE(pt.workflow_stage, 'todo') = 'approve' AND pt.status <> 'done'
                    ORDER BY COALESCE(pt.due_date, '2999-12-31') ASC, pt.updated_at DESC
                    LIMIT 8";

    $companyRes = prepareAndRun($conn, $companySql, $types, $params);
    $agingRes = prepareAndRun($conn, $agingSql, $types, $params);
    $ownerRes = prepareAndRun($conn, $ownerSql, $types, $params);
    $approvalRes = prepareAndRun($conn, $approvalSql, $types, $params);

    $companySummary = [];
    while ($row = $companyRes->fetch_assoc()) {
        $companySummary[] = $row;
    }

    $topOwners = [];
    while ($row = $ownerRes->fetch_assoc()) {
        $topOwners[] = $row;
    }

    $approvalQueue = [];
    while ($row = $approvalRes->fetch_assoc()) {
        $approvalQueue[] = $row;
    }

    $aging = $agingRes->fetch_assoc() ?: [];

    echo json_encode([
        'status' => 'success',
        'company_summary' => $companySummary,
        'aging' => [
            ['label' => 'เกินกำหนด', 'value' => (int)($aging['overdue_bucket'] ?? 0)],
            ['label' => '0-3 วัน', 'value' => (int)($aging['bucket_0_3'] ?? 0)],
            ['label' => '4-7 วัน', 'value' => (int)($aging['bucket_4_7'] ?? 0)],
            ['label' => '8-14 วัน', 'value' => (int)($aging['bucket_8_14'] ?? 0)],
            ['label' => '15+ วัน', 'value' => (int)($aging['bucket_15_plus'] ?? 0)],
        ],
        'top_owners' => $topOwners,
        'approval_queue' => $approvalQueue
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
