<?php

require_once __DIR__ . '/personal_tasks_schema.php';
require_once __DIR__ . '/task_center_hrga_helper.php';
ensurePersonalTasksSchema($conn);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$sessionDept = $_SESSION['primary_department'] ?? '';

if ($role !== 'admin' && !taskCenterHrGaModeEnabled($role, $sessionDept, $conn, $userId)) {
    echo json_encode(['status' => 'error', 'message' => 'HR&GA mode not enabled for this user']);
    exit;
}

$companyFilter = trim($_GET['company'] ?? 'all');
$view = trim($_GET['view'] ?? 'board');
$taskUserId = trim($_GET['user_id'] ?? '');
$openOnly = ($_GET['open_only'] ?? '1') !== '0';

$where = [];
$params = [];
$types = '';

$visibilityParts = [];
if ($role === 'admin') {
    $visibilityParts[] = '1=1'; // admin sees all tasks, further filtered by HRGA dept block below
} else {
    taskCenterAppendVisibility($conn, $role, $userId, $sessionCompany, $sessionDept, $visibilityParts, $params, $types);
}
$where[] = '(' . implode(' OR ', $visibilityParts) . ')';

// งานของทีม HR&GA: แชร์แผนก/บริษัท หรืองานของสมาชิกทีม (ไม่บังคับ department ตรงตัวเดียว — รองรับข้อมูลเก่า)
$where[] = "(
    pt.department = ?
    OR pt.share_scope IN ('department', 'company')
    OR pt.user_id IN (
        SELECT DISTINCT ea.employee_id FROM employee_assignments ea WHERE ea.department = ?
    )
)";
$params[] = TASK_CENTER_HRGA_DEPT;
$params[] = TASK_CENTER_HRGA_DEPT;
$types .= 'ss';

if ($view === 'routine' && $taskUserId !== '') {
    $where[] = 'pt.user_id = ?';
    $params[] = $taskUserId;
    $types .= 's';
    $where[] = "(pt.status <> 'done' OR pt.is_recurring = 1)";
} elseif ($openOnly) {
    $where[] = "pt.status <> 'done'";
}

if ($companyFilter !== '' && $companyFilter !== 'all') {
    $where[] = "(pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0)";
    $params[] = $companyFilter;
    $params[] = $companyFilter;
    $types .= 'ss';
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$sql = "SELECT
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
            pt.share_scope,
            pt.assignee_name,
            COALESCE(pt.workflow_stage, 'todo') AS workflow_stage,
            pt.is_recurring,
            pt.updated_at,
            COALESCE(e.name_th, e.name, pt.assignee_name, pt.user_id) AS owner_name,
            COALESCE(e.image, '') AS owner_image
        FROM personal_tasks pt
        LEFT JOIN employees e ON pt.user_id = e.id" . $whereSql . "
        ORDER BY COALESCE(pt.due_date, '2999-12-31') ASC, pt.updated_at DESC
        LIMIT 500";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $tasks = [];
    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }

    $teamSql = "SELECT DISTINCT e.id, e.name, e.name_th, ea.company, ea.department
                FROM employees e
                INNER JOIN employee_assignments ea ON ea.employee_id = e.id
                WHERE e.employment_status = 'active'
                ORDER BY ea.company ASC, COALESCE(e.name_th, e.name) ASC";
    $teamRes = $conn->query($teamSql);
    $team = [];
    $teamSeen = [];
    if ($teamRes) {
        while ($row = $teamRes->fetch_assoc()) {
            if (!taskCenterIsHrGaDepartment($row['department'] ?? '')) {
                continue;
            }
            $eid = (string)($row['id'] ?? '');
            if ($eid === '' || isset($teamSeen[$eid])) {
                continue;
            }
            $teamSeen[$eid] = true;
            $team[] = $row;
        }
    }

    $byPerson = [];
    $summary = [
        'team_count' => count($team),
        'open_total' => 0,
        'overdue_total' => 0,
        'routine_open' => 0,
        'adhoc_open' => 0,
    ];
    $today = date('Y-m-d');

    foreach ($tasks as $t) {
        $uid = (string)($t['user_id'] ?? '');
        if ($uid === '') {
            continue;
        }
        if (!isset($byPerson[$uid])) {
            $byPerson[$uid] = [
                'user_id' => $uid,
                'owner_name' => $t['owner_name'] ?? $uid,
                'open_count' => 0,
                'overdue_count' => 0,
                'routine_count' => 0,
                'adhoc_count' => 0,
                'tasks' => [],
            ];
        }
        $isOverdue = !empty($t['due_date']) && $t['due_date'] < $today && ($t['status'] ?? '') !== 'done';
        $isRoutine = (int)($t['is_recurring'] ?? 0) === 1;
        $byPerson[$uid]['open_count']++;
        $summary['open_total']++;
        if ($isOverdue) {
            $byPerson[$uid]['overdue_count']++;
            $summary['overdue_total']++;
        }
        if ($isRoutine) {
            $byPerson[$uid]['routine_count']++;
            $summary['routine_open']++;
        } else {
            $byPerson[$uid]['adhoc_count']++;
            $summary['adhoc_open']++;
        }
        $byPerson[$uid]['tasks'][] = $t;
    }

    foreach ($team as $member) {
        $uid = (string)($member['id'] ?? '');
        if ($uid === '') {
            continue;
        }
        if (!isset($byPerson[$uid])) {
            $displayName = trim((string)($member['name_th'] ?? '')) ?: trim((string)($member['name'] ?? '')) ?: $uid;
            $byPerson[$uid] = [
                'user_id' => $uid,
                'owner_name' => $displayName,
                'open_count' => 0,
                'overdue_count' => 0,
                'routine_count' => 0,
                'adhoc_count' => 0,
                'tasks' => [],
            ];
        }
    }

    usort($byPerson, function ($a, $b) {
        if ($b['open_count'] !== $a['open_count']) {
            return $b['open_count'] <=> $a['open_count'];
        }
        return strcmp($a['owner_name'], $b['owner_name']);
    });
    $byPerson = array_values($byPerson);

    $loadRows = array_map(function ($p) {
        $total = max(1, (int)$p['open_count']);
        return [
            'user_id' => $p['user_id'],
            'owner_name' => $p['owner_name'],
            'open_count' => (int)$p['open_count'],
            'routine_count' => (int)$p['routine_count'],
            'adhoc_count' => (int)$p['adhoc_count'],
            'overdue_count' => (int)$p['overdue_count'],
            'routine_pct' => (int)round(($p['routine_count'] / $total) * 100),
            'adhoc_pct' => (int)round(($p['adhoc_count'] / $total) * 100),
        ];
    }, $byPerson);
    usort($loadRows, function ($a, $b) {
        return $b['open_count'] <=> $a['open_count'];
    });

    echo json_encode([
        'status' => 'success',
        'hrga_mode' => true,
        'view' => $view,
        'department' => TASK_CENTER_HRGA_DEPT,
        'companies' => TASK_CENTER_HRGA_COMPANIES,
        'summary' => $summary,
        'team' => $team,
        'by_person' => $byPerson,
        'load' => $loadRows,
        'tasks' => $tasks,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
