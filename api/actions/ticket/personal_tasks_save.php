<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['display_name'] ?? ($_SESSION['username'] ?? '');
$role = $_SESSION['role'] ?? 'guest';
$company = $_SESSION['company'] ?? null;
$department = $_SESSION['primary_department'] ?? null;
$raw = $_POST['data'] ?? '';
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$title = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$status = $data['status'] ?? 'todo';
$statusDetail = trim($data['status_detail'] ?? '');
$priority = $data['priority'] ?? 'normal';
$dueDate = $data['due_date'] ?? null;
$tags = trim($data['tags'] ?? '');
$shareScope = $data['share_scope'] ?? 'private';
$assigneeName = trim($data['assignee_name'] ?? '');
$taskType = $data['task_type'] ?? 'personal';
$building = trim($data['building'] ?? '');
$workflowStage = $data['workflow_stage'] ?? '';
$inputCompany = trim($data['company'] ?? '');
$responsibleCompaniesRaw = $data['responsible_companies'] ?? [];
$remindMonthStart = !empty($data['remind_month_start']) ? 1 : 0;
$remindMonthEnd = !empty($data['remind_month_end']) ? 1 : 0;
$isRecurring = !empty($data['is_recurring']) ? 1 : 0;
$recurringIntervalMonths = isset($data['recurring_interval_months']) ? (int)$data['recurring_interval_months'] : 12;
$remindDaysBefore = isset($data['remind_days_before']) ? (int)$data['remind_days_before'] : 0;
$inputProgress = isset($data['progress_pct']) && $data['progress_pct'] !== '' ? max(0, min(100, (int)$data['progress_pct'])) : null;

if (is_string($responsibleCompaniesRaw)) {
    $responsibleCompaniesRaw = explode(',', $responsibleCompaniesRaw);
}
if (!is_array($responsibleCompaniesRaw)) {
    $responsibleCompaniesRaw = [];
}
$responsibleCompanies = [];
foreach ($responsibleCompaniesRaw as $code) {
    $normalized = strtoupper(trim((string)$code));
    if ($normalized !== '') {
        $responsibleCompanies[] = $normalized;
    }
}
$responsibleCompanies = array_values(array_unique($responsibleCompanies));
$responsibleCompaniesCsv = implode(',', $responsibleCompanies);

function deriveProgressPct($taskType, $status, $workflowStage) {
    if ($status === 'done') return 100;
    if ($status === 'approve') return 90;
    if ($status === 'doing') return 50;
    return 0;
}

if ($title === '') {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่องาน']);
    exit;
}

$allowedStatus = ['todo', 'doing', 'approve', 'done'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'todo';
}

$allowedPriority = ['urgent', 'high', 'normal', 'low'];
if (!in_array($priority, $allowedPriority, true)) {
    $priority = 'normal';
}

$allowedShare = ['private', 'department', 'company'];
if (!in_array($shareScope, $allowedShare, true)) {
    $shareScope = 'private';
}

$allowedTaskTypes = ['personal', 'job_ticket'];
if (!in_array($taskType, $allowedTaskTypes, true)) {
    $taskType = 'personal';
}

if ($dueDate === '') {
    $dueDate = null;
}

// Basic normalization/guardrails (avoid weird payloads breaking UI)
if (is_array($data['responsible_companies'] ?? null) && count($data['responsible_companies']) > 10) {
    echo json_encode(['status' => 'error', 'message' => 'เลือกบริษัทได้ไม่เกิน 10 รายการ']);
    exit;
}
if ($dueDate !== null && $dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dueDate)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบวันที่ไม่ถูกต้อง']);
    exit;
}
if ($isRecurring && !$dueDate) {
    echo json_encode(['status' => 'error', 'message' => 'งานประจำต้องระบุกำหนดเสร็จ']);
    exit;
}
if ($recurringIntervalMonths < 1) $recurringIntervalMonths = 1;
if ($recurringIntervalMonths > 60) $recurringIntervalMonths = 60;
if ($remindDaysBefore < 0) $remindDaysBefore = 0;
if ($remindDaysBefore > 365) $remindDaysBefore = 365;
if (mb_strlen($title, 'UTF-8') > 255) {
    echo json_encode(['status' => 'error', 'message' => 'หัวข้อยาวเกินไป']);
    exit;
}
if (mb_strlen($assigneeName, 'UTF-8') > 255) {
    $assigneeName = mb_substr($assigneeName, 0, 255, 'UTF-8');
}

$saveCompany = $company;
if ($taskType === 'job_ticket') {
    $workflowStage = $status === 'approve' ? 'approve' : ($status === 'done' ? 'done' : ($status === 'doing' ? 'doing' : 'todo'));
    if (in_array($role, ['admin', 'staff'], true) && $inputCompany !== '') {
        $saveCompany = $inputCompany;
    }
} else {
    $workflowStage = $status === 'approve' ? 'approve' : ($status === 'done' ? 'done' : ($status === 'doing' ? 'doing' : 'todo'));
}

if (!$responsibleCompaniesCsv) {
    $fallbackCompany = $saveCompany ?: $inputCompany;
    if ($fallbackCompany) {
        $responsibleCompaniesCsv = strtoupper($fallbackCompany);
    }
}
if ($saveCompany === '' || $saveCompany === null) {
    $saveCompany = $inputCompany ?: (explode(',', $responsibleCompaniesCsv)[0] ?? null);
}

function calcNextDueDate($dueDate, $months) {
    if (!$dueDate) return null;
    $dt = DateTime::createFromFormat('Y-m-d', $dueDate);
    if (!$dt) return null;
    $months = max(1, (int)$months);
    $dt->modify('+' . $months . ' month');
    return $dt->format('Y-m-d');
}

function generateRecurringNextTask($conn, $sourceTask) {
    $sourceId = (int)($sourceTask['id'] ?? 0);
    $dueDate = $sourceTask['due_date'] ?? null;
    $isRecurring = (int)($sourceTask['is_recurring'] ?? 0) === 1;
    if (!$isRecurring || !$sourceId || !$dueDate) return;

    $intervalMonths = max(1, (int)($sourceTask['recurring_interval_months'] ?? 12));
    $nextDueDate = calcNextDueDate($dueDate, $intervalMonths);
    if (!$nextDueDate) return;

    $parentId = (int)($sourceTask['recurring_parent_id'] ?? 0);
    if ($parentId <= 0) $parentId = $sourceId;

    $check = $conn->prepare("SELECT id FROM personal_tasks WHERE recurring_parent_id = ? AND due_date = ? LIMIT 1");
    if ($check) {
        $check->bind_param("is", $parentId, $nextDueDate);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        if ($exists) return;
    }

    $insert = $conn->prepare("INSERT INTO personal_tasks (
        user_id, company, responsible_companies, department, task_type, title, description, status, status_detail,
        priority, due_date, tags, share_scope, assignee_name, building, workflow_stage, progress_pct,
        remind_month_start, remind_month_end, is_recurring, recurring_interval_months, remind_days_before, recurring_parent_id, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'todo', '', ?, ?, ?, ?, ?, ?, 'todo', 0, ?, ?, 1, ?, ?, ?, NOW(), NOW())");
    if (!$insert) return;

    $userId = (string)($sourceTask['user_id'] ?? '');
    $company = (string)($sourceTask['company'] ?? '');
    $responsibleCompanies = (string)($sourceTask['responsible_companies'] ?? '');
    $department = (string)($sourceTask['department'] ?? '');
    $taskType = (string)($sourceTask['task_type'] ?? 'personal');
    $title = (string)($sourceTask['title'] ?? '');
    $description = (string)($sourceTask['description'] ?? '');
    $priority = (string)($sourceTask['priority'] ?? 'normal');
    $tags = (string)($sourceTask['tags'] ?? '');
    $shareScope = (string)($sourceTask['share_scope'] ?? 'private');
    $assigneeName = (string)($sourceTask['assignee_name'] ?? '');
    $building = (string)($sourceTask['building'] ?? '');
    $remindMonthStart = (int)($sourceTask['remind_month_start'] ?? 0);
    $remindMonthEnd = (int)($sourceTask['remind_month_end'] ?? 0);
    $remindDaysBefore = (int)($sourceTask['remind_days_before'] ?? 0);

    $insert->bind_param(
        "sssssssssssssiiiii",
        $userId, $company, $responsibleCompanies, $department, $taskType, $title, $description,
        $priority, $nextDueDate, $tags, $shareScope, $assigneeName, $building,
        $remindMonthStart, $remindMonthEnd, $intervalMonths, $remindDaysBefore, $parentId
    );
    $insert->execute();
}

try {
    $progressPct = $inputProgress;
    if ($id > 0) {
        $stmtOld = $conn->prepare("SELECT id, user_id, company, responsible_companies, department, task_type, title, description, status, status_detail, priority, due_date, tags, share_scope, assignee_name, building, workflow_stage, progress_pct, remind_month_start, remind_month_end, is_recurring, recurring_interval_months, remind_days_before, recurring_parent_id FROM personal_tasks WHERE id = ? LIMIT 1");
        if (!$stmtOld) {
            throw new Exception($conn->error);
        }
        $stmtOld->bind_param("i", $id);
        $stmtOld->execute();
        $resOld = $stmtOld->get_result();
        $old = $resOld->fetch_assoc();
        if (!$old) {
            throw new Exception('ไม่พบรายการสำหรับแก้ไข');
        }

        $canEdit = ($old['user_id'] ?? '') === $userId;
        if (!$canEdit && in_array($role, ['admin', 'staff'], true)) {
            $taskCompanies = explode(',', (string)($old['responsible_companies'] ?: $old['company']));
            $taskCompanies = array_values(array_filter(array_map('trim', $taskCompanies)));
            $scope = $old['share_scope'] ?? 'private';
            $sameCompany = in_array($company, $taskCompanies, true);
            $sameDepartment = !empty($department) && !empty($old['department']) && $department === $old['department'];
            $canEdit = ($role === 'admin')
                || (($scope === 'company' && $sameCompany) || ($scope === 'department' && $sameCompany && $sameDepartment));
        }
        if (!$canEdit) {
            throw new Exception('ไม่มีสิทธิ์แก้ไขรายการนี้');
        }

        if ($progressPct === null) {
            $typeChanged = ($old['task_type'] ?? 'personal') !== $taskType;
            $stateChanged = ($old['status'] ?? 'todo') !== $status || (($old['workflow_stage'] ?? 'todo') !== $workflowStage);
            if ($typeChanged || $stateChanged) {
                $progressPct = deriveProgressPct($taskType, $status, $workflowStage);
            } else {
                $progressPct = isset($old['progress_pct']) ? (int)$old['progress_pct'] : deriveProgressPct($taskType, $status, $workflowStage);
            }
        }

        $stmt = $conn->prepare("UPDATE personal_tasks SET task_type = ?, title = ?, description = ?, status = ?, status_detail = ?, priority = ?, due_date = ?, tags = ?, share_scope = ?, company = ?, responsible_companies = ?, department = ?, assignee_name = ?, building = ?, workflow_stage = ?, progress_pct = ?, remind_month_start = ?, remind_month_end = ?, is_recurring = ?, recurring_interval_months = ?, remind_days_before = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("sssssssssssssssiiiiiii", $taskType, $title, $description, $status, $statusDetail, $priority, $dueDate, $tags, $shareScope, $saveCompany, $responsibleCompaniesCsv, $department, $assigneeName, $building, $workflowStage, $progressPct, $remindMonthStart, $remindMonthEnd, $isRecurring, $recurringIntervalMonths, $remindDaysBefore, $id);
        $stmt->execute();

        if (($old['status'] ?? 'todo') !== 'done' && $status === 'done') {
            $recurringSource = array_merge($old, [
                'due_date' => $dueDate ?: ($old['due_date'] ?? null),
                'is_recurring' => $isRecurring,
                'recurring_interval_months' => $recurringIntervalMonths,
                'remind_days_before' => $remindDaysBefore,
                'remind_month_start' => $remindMonthStart,
                'remind_month_end' => $remindMonthEnd,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'tags' => $tags,
                'share_scope' => $shareScope,
                'assignee_name' => $assigneeName,
                'building' => $building,
                'company' => $saveCompany,
                'responsible_companies' => $responsibleCompaniesCsv,
                'department' => $department,
                'task_type' => $taskType
            ]);
            generateRecurringNextTask($conn, $recurringSource);
        }

        if ($old) {
            $oldStatus = $old['status'] ?? null;
            $oldPriority = $old['priority'] ?? null;
            if ($oldStatus !== $status || $oldPriority !== $priority) {
                $h = $conn->prepare("INSERT INTO personal_task_history (task_id, user_id, user_name, action, old_status, new_status, old_priority, new_priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($h) {
                    $action = 'update';
                    $h->bind_param("isssssss", $id, $userId, $userName, $action, $oldStatus, $status, $oldPriority, $priority);
                    $h->execute();
                }
            }
        }
    } else {
        if ($progressPct === null) {
            $progressPct = deriveProgressPct($taskType, $status, $workflowStage);
        }

        $stmt = $conn->prepare("INSERT INTO personal_tasks (user_id, company, responsible_companies, department, task_type, title, description, status, status_detail, priority, due_date, tags, share_scope, assignee_name, building, workflow_stage, progress_pct, remind_month_start, remind_month_end, is_recurring, recurring_interval_months, remind_days_before, recurring_parent_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())");
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param("ssssssssssssssssiiiiii", $userId, $saveCompany, $responsibleCompaniesCsv, $department, $taskType, $title, $description, $status, $statusDetail, $priority, $dueDate, $tags, $shareScope, $assigneeName, $building, $workflowStage, $progressPct, $remindMonthStart, $remindMonthEnd, $isRecurring, $recurringIntervalMonths, $remindDaysBefore);
        $stmt->execute();
        $id = $stmt->insert_id;

        if ($status === 'done' && $isRecurring) {
            $recurringSource = [
                'id' => $id,
                'user_id' => $userId,
                'company' => $saveCompany,
                'responsible_companies' => $responsibleCompaniesCsv,
                'department' => $department,
                'task_type' => $taskType,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'due_date' => $dueDate,
                'tags' => $tags,
                'share_scope' => $shareScope,
                'assignee_name' => $assigneeName,
                'building' => $building,
                'remind_month_start' => $remindMonthStart,
                'remind_month_end' => $remindMonthEnd,
                'is_recurring' => $isRecurring,
                'recurring_interval_months' => $recurringIntervalMonths,
                'remind_days_before' => $remindDaysBefore,
                'recurring_parent_id' => null
            ];
            generateRecurringNextTask($conn, $recurringSource);
        }

        $h = $conn->prepare("INSERT INTO personal_task_history (task_id, user_id, user_name, action, new_status, new_priority) VALUES (?, ?, ?, ?, ?, ?)");
        if ($h) {
            $action = 'create';
            $h->bind_param("isssss", $id, $userId, $userName, $action, $status, $priority);
            $h->execute();
        }
    }

    $stmt2 = $conn->prepare("SELECT id, user_id, task_type, title, description, status, status_detail, priority, due_date, tags, share_scope, company, responsible_companies, department, assignee_id, assignee_name, building, workflow_stage, legacy_job_ticket_id, progress_pct, remind_month_start, remind_month_end, is_recurring, recurring_interval_months, remind_days_before, recurring_parent_id, created_at, updated_at FROM personal_tasks WHERE id = ? LIMIT 1");
    if (!$stmt2) {
        throw new Exception($conn->error);
    }
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    $task = $res->fetch_assoc();

    echo json_encode(['status' => 'success', 'task' => $task]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
