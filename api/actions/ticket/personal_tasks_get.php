<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'guest';
$company = $_SESSION['company'] ?? '';
$department = $_SESSION['primary_department'] ?? '';
$availableCompanies = [];
if (isset($_SESSION['user_id'])) {
    $stmtComps = $conn->prepare("SELECT company FROM employee_assignments WHERE employee_id = ?");
    if ($stmtComps) {
        $stmtComps->bind_param("s", $userId);
        $stmtComps->execute();
        $resComps = $stmtComps->get_result();
        while ($rowComp = $resComps->fetch_assoc()) {
            if (!empty($rowComp['company'])) {
                $availableCompanies[] = $rowComp['company'];
            }
        }
    }
}
$availableCompanies = array_values(array_unique(array_filter($availableCompanies)));
if (!$availableCompanies && $company !== '') {
    $availableCompanies = [$company];
}

$scope = $_GET['scope'] ?? 'mine';
$scope = in_array($scope, ['mine', 'department', 'company'], true) ? $scope : 'mine';

if ($scope === 'mine') {
    $sql = "SELECT pt.id, pt.user_id, pt.task_type, pt.title, pt.description, pt.status, pt.status_detail, pt.priority, pt.due_date, pt.tags, pt.share_scope, pt.company, pt.responsible_companies, pt.department, pt.assignee_id, pt.assignee_name, pt.building, pt.workflow_stage, pt.legacy_job_ticket_id, pt.progress_pct, pt.remind_month_start, pt.remind_month_end, pt.is_recurring, pt.recurring_interval_months, pt.remind_days_before, pt.recurring_parent_id, pt.created_at, pt.updated_at, e.name AS owner_name, e.name_th AS owner_name_th FROM personal_tasks pt LEFT JOIN employees e ON pt.user_id = e.id WHERE pt.user_id = ? ORDER BY COALESCE(pt.due_date, pt.created_at) ASC, pt.id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    $stmt->bind_param("s", $userId);
} elseif ($scope === 'department') {
    if (!$company || !$department) {
        echo json_encode([]);
        exit;
    }
    $sql = "SELECT pt.id, pt.user_id, pt.task_type, pt.title, pt.description, pt.status, pt.status_detail, pt.priority, pt.due_date, pt.tags, pt.share_scope, pt.company, pt.responsible_companies, pt.department, pt.assignee_id, pt.assignee_name, pt.building, pt.workflow_stage, pt.legacy_job_ticket_id, pt.progress_pct, pt.remind_month_start, pt.remind_month_end, pt.is_recurring, pt.recurring_interval_months, pt.remind_days_before, pt.recurring_parent_id, pt.created_at, pt.updated_at, e.name AS owner_name, e.name_th AS owner_name_th FROM personal_tasks pt LEFT JOIN employees e ON pt.user_id = e.id WHERE pt.company = ? AND pt.department = ? AND pt.share_scope IN ('department','company') ORDER BY COALESCE(pt.due_date, pt.created_at) ASC, pt.id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    $stmt->bind_param("ss", $company, $department);
} else {
    if (!in_array($role, ['admin', 'staff'], true)) {
        echo json_encode([]);
        exit;
    }
    $filterCompany = $_GET['company'] ?? ($availableCompanies[0] ?? $company);
    if ($role === 'admin' && $filterCompany === 'all') {
        $sql = "SELECT pt.id, pt.user_id, pt.task_type, pt.title, pt.description, pt.status, pt.status_detail, pt.priority, pt.due_date, pt.tags, pt.share_scope, pt.company, pt.responsible_companies, pt.department, pt.assignee_id, pt.assignee_name, pt.building, pt.workflow_stage, pt.legacy_job_ticket_id, pt.progress_pct, pt.remind_month_start, pt.remind_month_end, pt.is_recurring, pt.recurring_interval_months, pt.remind_days_before, pt.recurring_parent_id, pt.created_at, pt.updated_at, e.name AS owner_name, e.name_th AS owner_name_th FROM personal_tasks pt LEFT JOIN employees e ON pt.user_id = e.id WHERE pt.share_scope = 'company' ORDER BY COALESCE(pt.due_date, pt.created_at) ASC, pt.id DESC";
        $stmt = $conn->prepare($sql);
    } else {
        $allowedCompanies = $role === 'admin' ? ['PTA', 'PT4', 'PTE'] : $availableCompanies;
        if (!$allowedCompanies) {
            echo json_encode([]);
            exit;
        }
        if ($filterCompany !== 'all' && !in_array($filterCompany, $allowedCompanies, true)) {
            echo json_encode([]);
            exit;
        }

        if ($filterCompany === 'all') {
            $conditionParts = [];
            $bindValues = [];
            foreach ($allowedCompanies as $code) {
                $conditionParts[] = "(pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0)";
                $bindValues[] = $code;
                $bindValues[] = $code;
            }
            $sql = "SELECT pt.id, pt.user_id, pt.task_type, pt.title, pt.description, pt.status, pt.status_detail, pt.priority, pt.due_date, pt.tags, pt.share_scope, pt.company, pt.responsible_companies, pt.department, pt.assignee_id, pt.assignee_name, pt.building, pt.workflow_stage, pt.legacy_job_ticket_id, pt.progress_pct, pt.remind_month_start, pt.remind_month_end, pt.is_recurring, pt.recurring_interval_months, pt.remind_days_before, pt.recurring_parent_id, pt.created_at, pt.updated_at, e.name AS owner_name, e.name_th AS owner_name_th FROM personal_tasks pt LEFT JOIN employees e ON pt.user_id = e.id WHERE pt.share_scope = 'company' AND (" . implode(' OR ', $conditionParts) . ") ORDER BY COALESCE(pt.due_date, pt.created_at) ASC, pt.id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(str_repeat('s', count($bindValues)), ...$bindValues);
            }
        } else {
            $sql = "SELECT pt.id, pt.user_id, pt.task_type, pt.title, pt.description, pt.status, pt.status_detail, pt.priority, pt.due_date, pt.tags, pt.share_scope, pt.company, pt.responsible_companies, pt.department, pt.assignee_id, pt.assignee_name, pt.building, pt.workflow_stage, pt.legacy_job_ticket_id, pt.progress_pct, pt.remind_month_start, pt.remind_month_end, pt.is_recurring, pt.recurring_interval_months, pt.remind_days_before, pt.recurring_parent_id, pt.created_at, pt.updated_at, e.name AS owner_name, e.name_th AS owner_name_th FROM personal_tasks pt LEFT JOIN employees e ON pt.user_id = e.id WHERE pt.share_scope = 'company' AND (pt.company = ? OR FIND_IN_SET(?, REPLACE(COALESCE(pt.responsible_companies,''), ' ', '')) > 0) ORDER BY COALESCE(pt.due_date, pt.created_at) ASC, pt.id DESC";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $filterCompany, $filterCompany);
            }
        }
    }
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
}

$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
