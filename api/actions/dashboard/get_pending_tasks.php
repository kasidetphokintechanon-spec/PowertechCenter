<?php
// api/actions/dashboard/get_pending_tasks.php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$sessionCompany = $_SESSION['company'] ?? '';
$userId = $_SESSION['user_id'];

$tasks = [];
$total = 0;
$urgentCount = 0;

try {
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception('No database connection');
    }

    // Repair tickets (it_logs) ที่ยังไม่ Completed / Cancelled
    $sql = "SELECT id, date, company, department, requester, servicedBy, status, urgency 
            FROM it_logs 
            WHERE status <> 'Completed' AND status <> 'Cancelled'";
    $params = [];
    $types = "";

    if ($role !== 'admin' && !empty($sessionCompany)) {
        $sql .= " AND company = ?";
        $params[] = $sessionCompany;
        $types .= "s";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $isUrgent = in_array($row['urgency'] ?? '', ['ด่วน', 'ด่วนที่สุด'], true);
            if ($isUrgent) $urgentCount++;
            $tasks[] = [
                'type' => 'repair',
                'id' => $row['id'],
                'title' => $row['department'] . ' - ' . $row['id'],
                'status' => $row['status'],
                'urgency' => $row['urgency'],
                'is_urgent' => $isUrgent,
                'requester' => $row['requester'],
                'company' => $row['company'],
                'link' => 'log.html?id=' . urlencode($row['id'])
            ];
        }
    }

    // Loan requests จาก it_assets (status = รออนุมัติ)
    $sqlLoan = "SELECT id, equipment_id, name, borrower, borrower_id, company, department, return_date 
                FROM it_assets 
                WHERE status = 'รออนุมัติ'";
    $params = [];
    $types = "";

    if ($role !== 'admin' && !empty($sessionCompany)) {
        $sqlLoan .= " AND company = ?";
        $params[] = $sessionCompany;
        $types .= "s";
    }

    $stmtLoan = $conn->prepare($sqlLoan);
    if ($stmtLoan) {
        if (!empty($params)) {
            $stmtLoan->bind_param($types, ...$params);
        }
        $stmtLoan->execute();
        $resLoan = $stmtLoan->get_result();
        while ($row = $resLoan->fetch_assoc()) {
            $tasks[] = [
                'type' => 'loan',
                'id' => $row['equipment_id'],
                'title' => $row['name'],
                'status' => 'Pending',
                'urgency' => null,
                'is_urgent' => false,
                'requester' => $row['borrower'],
                'company' => $row['company'],
                'link' => 'borrow_management.html'
            ];
        }
    }

    // Job tickets ที่ยังไม่ Done
    $sqlJob = "SELECT id, job_name, company, building, is_receive, is_doing, is_send, is_approve, is_done 
               FROM job_tickets 
               WHERE is_done = 0";
    $params = [];
    $types = "";

    if ($role !== 'admin' && !empty($sessionCompany)) {
        $sqlJob .= " AND company = ?";
        $params[] = $sessionCompany;
        $types .= "s";
    }

    $stmtJob = $conn->prepare($sqlJob);
    if ($stmtJob) {
        if (!empty($params)) {
            $stmtJob->bind_param($types, ...$params);
        }
        $stmtJob->execute();
        $resJob = $stmtJob->get_result();
        while ($row = $resJob->fetch_assoc()) {
            $statusLabel = 'Pending';
            if ($row['is_doing']) $statusLabel = 'In Progress';
            elseif ($row['is_send']) $statusLabel = 'Sent';
            elseif ($row['is_approve']) $statusLabel = 'Waiting Approve';

            $tasks[] = [
                'type' => 'job',
                'id' => 'JOB-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                'title' => $row['job_name'],
                'status' => $statusLabel,
                'urgency' => null,
                'is_urgent' => false,
                'requester' => $row['building'] ?? '',
                'company' => $row['company'],
                'link' => 'task_center.html'
            ];
        }
    }

    // Filter เฉพาะที่เกี่ยวข้องกับผู้ใช้ถ้าไม่ใช่ admin/IT
    if (!($role === 'admin' || ($_SESSION['primary_department'] ?? '') === 'IT')) {
        $uid = $userId;
        $tasks = array_values(array_filter($tasks, function ($t) use ($uid) {
            if ($t['type'] === 'repair') {
                return true;
            }
            if ($t['type'] === 'loan') {
                return true;
            }
            if ($t['type'] === 'job') {
                return true;
            }
            return false;
        }));
    }

    $total = count($tasks);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'total' => $total,
    'urgent_count' => $urgentCount,
    'tasks' => $tasks
]);
