<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = max(10, min(100, (int)($_GET['page_size'] ?? 25)));
$search = trim($_GET['search'] ?? '');
$company = trim($_GET['company'] ?? '');
$department = trim($_GET['department'] ?? '');
$assignee = trim($_GET['assignee'] ?? '');
$taskType = trim($_GET['task_type'] ?? '');
$status = trim($_GET['status'] ?? '');
$workflowStage = trim($_GET['workflow_stage'] ?? '');
$sort = trim($_GET['sort'] ?? 'updated_desc');
$openOnly = isset($_GET['open_only']) && $_GET['open_only'] === '1';
$overdueOnly = isset($_GET['overdue_only']) && $_GET['overdue_only'] === '1';

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

if ($search !== '') {
    $where[] = "(pt.title LIKE ? OR pt.description LIKE ? OR pt.status_detail LIKE ? OR pt.tags LIKE ? OR pt.assignee_name LIKE ? OR pt.building LIKE ? OR pt.department LIKE ? OR pt.responsible_companies LIKE ?)";
    $like = '%' . $search . '%';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}

if ($company !== '' && $company !== 'all') {
    $where[] = "(pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0)";
    $params[] = $company;
    $params[] = $company;
    $types .= 'ss';
}

if ($department !== '') {
    $where[] = 'pt.department LIKE ?';
    $params[] = '%' . $department . '%';
    $types .= 's';
}

if ($assignee !== '') {
    $where[] = 'pt.assignee_name LIKE ?';
    $params[] = '%' . $assignee . '%';
    $types .= 's';
}

if ($taskType !== '' && in_array($taskType, ['personal', 'job_ticket'], true)) {
    $where[] = 'COALESCE(pt.task_type, \'personal\') = ?';
    $params[] = $taskType;
    $types .= 's';
}

if ($status !== '' && in_array($status, ['todo', 'doing', 'approve', 'done'], true)) {
    $where[] = 'pt.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($workflowStage !== '' && in_array($workflowStage, ['todo', 'receive', 'doing', 'send', 'approve', 'done'], true)) {
    $where[] = 'COALESCE(pt.workflow_stage, \'todo\') = ?';
    $params[] = $workflowStage;
    $types .= 's';
}

if ($openOnly) {
    $where[] = "pt.status <> 'done'";
}

if ($overdueOnly) {
    $where[] = "pt.due_date < CURDATE() AND pt.status <> 'done'";
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$orderBy = "pt.updated_at DESC, pt.id DESC";
if ($sort === 'due_asc') {
    $orderBy = "CASE WHEN pt.due_date IS NULL THEN 1 ELSE 0 END, pt.due_date ASC, pt.updated_at DESC";
} elseif ($sort === 'due_desc') {
    $orderBy = "pt.due_date DESC, pt.updated_at DESC";
} elseif ($sort === 'created_desc') {
    $orderBy = "pt.created_at DESC, pt.id DESC";
} elseif ($sort === 'priority_desc') {
    $orderBy = "CASE pt.priority WHEN 'urgent' THEN 4 WHEN 'high' THEN 3 WHEN 'normal' THEN 2 WHEN 'low' THEN 1 ELSE 0 END DESC, pt.updated_at DESC";
}

$countSql = "SELECT COUNT(*) as total,
                    SUM(CASE WHEN pt.status = 'todo' THEN 1 ELSE 0 END) as todo_count,
                    SUM(CASE WHEN pt.status IN ('doing','approve') THEN 1 ELSE 0 END) as doing_count,
                    SUM(CASE WHEN pt.status = 'done' THEN 1 ELSE 0 END) as done_count,
                    SUM(CASE WHEN pt.due_date < CURDATE() AND pt.status <> 'done' THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN pt.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND pt.status <> 'done' THEN 1 ELSE 0 END) as due_soon_count
             FROM personal_tasks pt" . $whereSql;

$listSql = "SELECT
                pt.id,
                pt.user_id,
                COALESCE(pt.task_type, 'personal') AS task_type,
                pt.company,
                pt.responsible_companies,
                pt.department,
                pt.title,
                pt.description,
                pt.status,
                pt.priority,
                pt.due_date,
                pt.tags,
                pt.share_scope,
                pt.assignee_name,
                pt.building,
                COALESCE(pt.workflow_stage, 'todo') AS workflow_stage,
                pt.status_detail,
                pt.progress_pct,
                pt.remind_month_start,
                pt.remind_month_end,
                pt.created_at,
                pt.updated_at,
                COALESCE(e.name_th, e.name, pt.assignee_name, pt.user_id) AS owner_name
            FROM personal_tasks pt
            LEFT JOIN employees e ON pt.user_id = e.id" . $whereSql . "
            ORDER BY " . $orderBy . "
            LIMIT ? OFFSET ?";

try {
    $stmtCount = $conn->prepare($countSql);
    if (!$stmtCount) {
        throw new Exception($conn->error);
    }
    if ($types !== '') {
        $stmtCount->bind_param($types, ...$params);
    }
    $stmtCount->execute();
    $summary = $stmtCount->get_result()->fetch_assoc() ?: [];

    $listParams = $params;
    $listTypes = $types . 'ii';
    $offset = ($page - 1) * $pageSize;
    $listParams[] = $pageSize;
    $listParams[] = $offset;

    $stmtList = $conn->prepare($listSql);
    if (!$stmtList) {
        throw new Exception($conn->error);
    }
    $stmtList->bind_param($listTypes, ...$listParams);
    $stmtList->execute();
    $resList = $stmtList->get_result();

    $items = [];
    while ($row = $resList->fetch_assoc()) {
        $items[] = $row;
    }

    $total = (int)($summary['total'] ?? 0);
    $pageCount = $total > 0 ? (int)ceil($total / $pageSize) : 1;

    echo json_encode([
        'status' => 'success',
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'page_count' => $pageCount
        ],
        'summary' => [
            'total' => $total,
            'todo' => (int)($summary['todo_count'] ?? 0),
            'doing' => (int)($summary['doing_count'] ?? 0),
            'done' => (int)($summary['done_count'] ?? 0),
            'overdue' => (int)($summary['overdue_count'] ?? 0),
            'due_soon' => (int)($summary['due_soon_count'] ?? 0)
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
