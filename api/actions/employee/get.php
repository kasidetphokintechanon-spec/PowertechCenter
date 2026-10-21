<?php
// api/actions/get.php

// Read-only on $_SESSION (auth check only). admin.html fires this concurrently for
// several `file` values on every load — releasing the session lock immediately lets
// those requests actually run in parallel instead of queuing behind PHP's session lock.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$file = $_GET['file'] ?? '';

if ($file === 'employees') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $status_filter = $_GET['status_filter'] ?? '';
    $scope = $_GET['scope'] ?? '';

    // Minimal technician list for IT log (avoid loading all employees + assignments)
    if ($scope === 'technicians') {
        $sql = "SELECT DISTINCT e.id, e.name
                FROM employees e
                LEFT JOIN employee_assignments a ON a.employee_id = e.id
                WHERE e.employment_status = 'active'
                  AND (
                    e.is_technician = 1
                    OR LOWER(TRIM(IFNULL(a.department, ''))) IN ('it', 'i.t.')
                    OR LOWER(TRIM(IFNULL(a.department, ''))) LIKE '%information technology%'
                  )
                ORDER BY e.name ASC
                LIMIT 80";
        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = ['id' => $row['id'], 'name' => $row['name']];
            }
        }
        echo json_encode($rows);
        exit;
    }

    $slimAssignable = ($scope === 'assignable');
    $sql = $slimAssignable
        ? "SELECT id, name, image, is_technician FROM employees"
        : "SELECT * FROM employees";
    if ($status_filter === 'active') {
        $sql .= " WHERE employment_status = 'active'";
    } elseif ($status_filter === 'inactive') {
        $sql .= " WHERE employment_status = 'inactive'";
    }
    
    $result = $conn->query($sql);
    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // ห้ามส่ง hash รหัสผ่าน / remember_token ออกไปหน้าเว็บ — token ใช้สวมรอยล็อกอินได้ (ดู config.php)
            unset($row['password'], $row['remember_token']);
            $row['assignments'] = []; // เตรียม array สำหรับเก็บสังกัด
            $employees[$row['id']] = $row;
        }
    }

    // สถานะงาน offboard ล่าสุดของแต่ละคน (ให้ HR เห็นว่า IT ปิดสิทธิ์ถึงไหน) — เฉพาะ list เต็ม ไม่ใช่ slim
    if (!$slimAssignable && !empty($employees)) {
        // DB ที่ยังไม่มีตาราง/คอลัมน์ (ยังไม่เคยเปิด Task Center) — ข้ามไป ห้ามทำให้รายชื่อทั้งหน้าโหลดไม่ขึ้น
        $hasTbl = $conn->query("SHOW TABLES LIKE 'personal_tasks'");
        $hasCol = ($hasTbl && $hasTbl->num_rows > 0) ? $conn->query("SHOW COLUMNS FROM personal_tasks LIKE 'offboard_employee_id'") : null;
        if ($hasCol && $hasCol->num_rows > 0) {
            $resOff = $conn->query("SELECT offboard_employee_id, status, progress_pct FROM personal_tasks
                                    WHERE offboard_employee_id IS NOT NULL ORDER BY id ASC");
            if ($resOff) {
                while ($off = $resOff->fetch_assoc()) {
                    $eid = $off['offboard_employee_id'];
                    if (isset($employees[$eid])) {
                        $employees[$eid]['offboard_task_status'] = $off['status'];
                        $employees[$eid]['offboard_task_pct'] = (int)$off['progress_pct'];
                    }
                }
            }
        }
    }

    if (!empty($employees)) {
        // ดึงข้อมูลสังกัดทั้งหมดมาจับคู่กับพนักงาน
        $assignSql = $slimAssignable
            ? "SELECT employee_id, company, department, is_primary FROM employee_assignments"
            : "SELECT * FROM employee_assignments";
        $assign_res = $conn->query($assignSql);
        if ($assign_res) {
            while ($assign = $assign_res->fetch_assoc()) {
                if (isset($employees[$assign['employee_id']])) {
                    $employees[$assign['employee_id']]['assignments'][] = $assign;
                }
            }
        }
    }

    $role = $_SESSION['role'] ?? 'guest';
    $sessionCompany = $_SESSION['company'] ?? '';
    if ($role !== 'admin' && $sessionCompany) {
        $filtered_employees = [];
        foreach ($employees as $emp) {
            $emp['assignments'] = array_values(array_filter($emp['assignments'] ?? [], function ($a) use ($sessionCompany) {
                return isset($a['company']) && $a['company'] === $sessionCompany;
            }));
            if (!empty($emp['assignments']) || (string)($emp['id'] ?? '') === (string)($_SESSION['user_id'] ?? '')) {
                $filtered_employees[] = $emp;
            }
        }
        $employees = [];
        foreach ($filtered_employees as $emp) {
            $employees[$emp['id']] = $emp;
        }
    }
    
    // กรองข้อมูลสำหรับ Staff (ให้เห็นเฉพาะแผนกที่รับผิดชอบ)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff' && !empty($_SESSION['responsible_department'])) {
        $allowed_depts = array_map('trim', explode(',', $_SESSION['responsible_department']));
        $allowed_depts[] = 'IT';
        $allowed_depts = array_values(array_unique(array_filter($allowed_depts, function ($d) { return $d !== ''; })));
        $filtered_employees = [];
        foreach ($employees as $emp) {
            $has_access = ($emp['id'] == $_SESSION['user_id']); // เห็นตัวเองเสมอ
            if (!$has_access) {
                foreach ($emp['assignments'] as $assign) {
                    $dept = trim((string)($assign['department'] ?? ''));
                    foreach ($allowed_depts as $allowed) {
                        if (strcasecmp($dept, (string)$allowed) === 0) { $has_access = true; break 2; }
                    }
                }
            }
            if ($has_access) $filtered_employees[] = $emp;
        }
        echo json_encode(array_values($filtered_employees));
    } else {
        echo json_encode(array_values($employees));
    }
} else {
    // ดึงข้อมูลตารางอื่นๆ (Generic)
    $tableMap = [
        'asset_categories' => 'asset_categories',
        'departments' => 'departments',
        'meeting_rooms' => 'meeting_rooms',
        'meeting_amenities' => 'meeting_amenities',
        'announcements' => 'announcements',
        'holidays' => 'holidays',
        'services' => 'services',
        'it_logs' => 'it_logs',
        'it_log_attachments' => 'it_log_attachments',
        'kb_articles' => 'kb_articles',
        'kb_categories' => 'kb_categories',
        'job_tickets' => 'job_tickets'
    ];
    
    if (isset($tableMap[$file])) {
        if ($file === 'it_logs' && !isset($_SESSION['user_id'])) {
            echo json_encode([]);
            exit;
        }
        $table = $tableMap[$file];
        $sql = "SELECT * FROM $table";
        $params = [];
        $types = "";
        $where_clauses = [];

        if ($file === 'it_logs') {
            $role = $_SESSION['role'] ?? 'guest';
            $sessionCompany = $_SESSION['company'] ?? '';

            // Lightweight payload for index.html dashboard (avoid loading full table into browser)
            if (($_GET['scope'] ?? '') === 'dashboard') {
                $where = ["(status NOT IN ('Completed','Closed','Cancelled','Unrepairable') OR date >= DATE_SUB(NOW(), INTERVAL 90 DAY))"];
                $params = [];
                $types = '';
                if ($role !== 'admin' && $sessionCompany) {
                    $where[] = 'company = ?';
                    $params[] = $sessionCompany;
                    $types .= 's';
                }
                $whereStr = ' WHERE ' . implode(' AND ', $where);
                $limit = min(400, max(50, (int)($_GET['limit'] ?? 250)));
                $sql = "SELECT id, date, status, requester_id, servicedById, servicedBy, requester, department, type, company
                        FROM it_logs{$whereStr}
                        ORDER BY date DESC, id DESC
                        LIMIT {$limit}";
                $rows = [];
                if (!empty($params)) {
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
                    }
                } else {
                    $res = $conn->query($sql);
                    if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;
                }
                echo json_encode($rows);
                exit;
            }

            // request.html — only current user's tickets (paginated; avoids full-table load)
            if (($_GET['scope'] ?? '') === 'my_tickets') {
                $uid = trim((string)($_SESSION['user_id'] ?? ''));
                if ($uid === '') {
                    echo json_encode(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 100, 'total_pages' => 0]);
                    exit;
                }
                $primaryDept = trim((string)($_SESSION['primary_department'] ?? ''));
                $isItViewer = ($role === 'admin') || (strcasecmp($primaryDept, 'IT') === 0);
                if ($isItViewer) {
                    $where_clauses[] = "(requester_id = ? OR servicedById = ? OR status = 'Pending')";
                    $params[] = $uid;
                    $params[] = $uid;
                    $types .= 'ss';
                } else {
                    $where_clauses[] = '(requester_id = ? OR servicedById = ?)';
                    $params[] = $uid;
                    $params[] = $uid;
                    $types .= 'ss';
                }
                $_GET['paginate'] = '1';
            }

            // Server-side filters from query string
            $filterId        = trim($_GET['id'] ?? '');
            $filterCompany   = trim($_GET['filter_company']   ?? '');
            $filterStatus    = trim($_GET['filter_status']    ?? '');
            $filterType      = trim($_GET['filter_type']      ?? '');
            $filterSearch    = trim($_GET['filter_search']    ?? '');
            $filterDateStart = trim($_GET['filter_date_start'] ?? '');
            $filterDateEnd   = trim($_GET['filter_date_end']   ?? '');
            $filterAssignee  = trim($_GET['filter_assignee']   ?? '');
            $paginate        = ($_GET['paginate'] ?? '0') === '1';
            $page            = max(1, intval($_GET['page']     ?? 1));
            $perPage         = min(200, max(1, intval($_GET['per_page'] ?? 20)));

            // Company restriction for non-admins
            if ($role !== 'admin' && $sessionCompany) {
                $where_clauses[] = "company = ?";
                $params[] = $sessionCompany;
                $types .= "s";
            }
            // Optional company filter (admin can cross-company)
            if ($filterId !== '') {
                $where_clauses[] = 'id = ?';
                $params[] = $filterId;
                $types .= 's';
            }
            if ($filterCompany !== '') {
                $where_clauses[] = "company = ?";
                $params[] = $filterCompany;
                $types .= "s";
            }
            if ($filterStatus !== '') {
                $where_clauses[] = "status = ?";
                $params[] = $filterStatus;
                $types .= "s";
            }
            if ($filterType !== '') {
                $where_clauses[] = "type = ?";
                $params[] = $filterType;
                $types .= "s";
            }
            if ($filterAssignee !== '') {
                $where_clauses[] = "servicedById = ?";
                $params[] = $filterAssignee;
                $types .= "s";
            }
            if ($filterDateStart !== '') {
                $where_clauses[] = "date >= ?";
                $params[] = $filterDateStart . ' 00:00:00';
                $types .= "s";
            }
            if ($filterDateEnd !== '') {
                $where_clauses[] = "date <= ?";
                $params[] = $filterDateEnd . ' 23:59:59';
                $types .= "s";
            }
            if ($filterSearch !== '') {
                $like = '%' . $filterSearch . '%';
                $where_clauses[] = "(id LIKE ? OR requester LIKE ? OR problem LIKE ? OR servicedBy LIKE ? OR department LIKE ? OR asset_id LIKE ?)";
                array_push($params, $like, $like, $like, $like, $like, $like);
                $types .= "ssssss";
            }

            // Build WHERE before paginating
            $whereStr = empty($where_clauses) ? '' : ' WHERE ' . implode(' AND ', $where_clauses);
            $conn->query("CREATE TABLE IF NOT EXISTS ticket_comments (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                ticket_id  VARCHAR(50)  NOT NULL,
                user_id    VARCHAR(100) NOT NULL,
                user_name  VARCHAR(200) NOT NULL,
                user_role  ENUM('it','user') NOT NULL DEFAULT 'user',
                message    TEXT NOT NULL,
                file_path  VARCHAR(500) DEFAULT NULL,
                file_name  VARCHAR(200) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tc_ticket (ticket_id),
                INDEX idx_tc_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $listCols = 'id, date, company, requester, department, asset_id, requester_id, servicedBy, servicedById, asset, problem, solution, root_cause, contact, repair_cost, rating, status, type, urgency, appointment_date, closed_date, pr_no, po_no, vendor_name, eta_date, doc_number, user_confirmed, user_confirmed_at, user_confirmed_by, completed_date, last_work_start_time, total_work_duration_seconds, (SELECT COUNT(*) FROM ticket_comments WHERE ticket_id = id) AS comment_count';
            $sql = "SELECT * FROM it_logs" . $whereStr . " ORDER BY date DESC, id DESC";

            if ($paginate) {
                if ($filterId === '') {
                    $sql = "SELECT {$listCols} FROM it_logs" . $whereStr . " ORDER BY date DESC, id DESC";
                }
                // Count total
                $countSql = "SELECT COUNT(*) AS total FROM it_logs" . $whereStr;
                if (!empty($params)) {
                    $cStmt = $conn->prepare($countSql);
                    $cStmt->bind_param($types, ...$params);
                    $cStmt->execute();
                    $totalRows = $cStmt->get_result()->fetch_assoc()['total'] ?? 0;
                } else {
                    $totalRows = $conn->query($countSql)->fetch_assoc()['total'] ?? 0;
                }

                // Status breakdown for sidebar/KPI (same filters, no row payload)
                $statusCounts = [];
                $statsSql = "SELECT status, COUNT(*) AS cnt FROM it_logs" . $whereStr . " GROUP BY status";
                if ($types !== '') {
                    $sStmt = $conn->prepare($statsSql);
                    if ($sStmt) {
                        $sStmt->bind_param($types, ...$params);
                        $sStmt->execute();
                        $sRes = $sStmt->get_result();
                        if ($sRes) {
                            while ($row = $sRes->fetch_assoc()) {
                                $statusCounts[$row['status']] = (int)$row['cnt'];
                            }
                        }
                    }
                } else {
                    $sRes = $conn->query($statsSql);
                    if ($sRes) {
                        while ($row = $sRes->fetch_assoc()) {
                            $statusCounts[$row['status']] = (int)$row['cnt'];
                        }
                    }
                }

                $progWhere = $whereStr . ($whereStr !== '' ? ' AND ' : ' WHERE ')
                    . "(type = 'Programmer' OR problem LIKE '%[PROGRAMMER REQUEST]%')";
                $progSql = "SELECT COUNT(*) AS cnt FROM it_logs" . $progWhere;
                $programmerCount = 0;
                if ($types !== '') {
                    $pStmt = $conn->prepare($progSql);
                    if ($pStmt) {
                        $pStmt->bind_param($types, ...$params);
                        $pStmt->execute();
                        $programmerCount = (int)($pStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                    }
                } else {
                    $programmerCount = (int)($conn->query($progSql)->fetch_assoc()['cnt'] ?? 0);
                }

                $offset = ($page - 1) * $perPage;
                $sql .= " LIMIT ? OFFSET ?";
                $params[] = $perPage; $params[] = $offset;
                $types .= "ii";

                if (!empty($params)) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }
                $rows = [];
                $slimListPayload = ($filterId === '');
                while ($row = $result->fetch_assoc()) {
                    if ($slimListPayload) {
                        unset($row['solution'], $row['root_cause'], $row['user_signature'], $row['notes'], $row['image_path']);
                        if (isset($row['problem']) && strlen($row['problem']) > 500) {
                            $row['problem'] = (function_exists('mb_substr')
                                ? mb_substr($row['problem'], 0, 500, 'UTF-8')
                                : substr($row['problem'], 0, 500)) . '…';
                        }
                    }
                    $rows[] = $row;
                }
                echo json_encode([
                    'data'              => $rows,
                    'total'             => (int)$totalRows,
                    'page'              => $page,
                    'per_page'          => $perPage,
                    'total_pages'       => (int)ceil($totalRows / $perPage),
                    'status_counts'     => $statusCounts,
                    'programmer_count'  => $programmerCount,
                ]);
                exit;
            }
            // Non-paginated: apply WHERE then continue normal flow below
            $sql_override = $sql; // handled below; skip duplicate WHERE build
        }

        // Special handling for meeting_rooms filter
        if ($file === 'meeting_rooms') {
            $context = $_GET['context'] ?? 'user';
            // For non-admin context, filter by active and company
            if ($context !== 'admin') {
                $where_clauses[] = "is_active = 1";
                $company = $_GET['company'] ?? '';
                if ($company && $company !== 'all') {
                    $where_clauses[] = "company = ?";
                    $params[] = $company;
                    $types .= "s";
                }
            }
            // For admin context, no filter is applied, showing all rooms.
        }

        // Special handling for it_log_attachments filter
        if ($file === 'it_log_attachments') {
            $log_id = $_GET['log_id'] ?? '';
            if ($log_id) {
                $where_clauses[] = "log_id = ?";
                $params[] = $log_id;
                $types .= "s";
            }
        }

        // For it_logs with non-paginated server-side filters, sql_override is already set
        if (isset($sql_override)) {
            $sql = $sql_override;
        } else {
            if (!empty($where_clauses)) {
                $sql .= " WHERE " . implode(' AND ', $where_clauses);
            }
        }

        // เรียงลำดับ Services ตาม Sort Order
        if ($file === 'services') {
            ptc_ensure_services_is_active_column($conn);
            $sql .= " ORDER BY sort_order ASC";
        }

        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        $data = [];
        if($result) {
            while ($row = $result->fetch_assoc()) {
                if ($file === 'meeting_rooms') {
                    if (isset($row['images'])) $row['images'] = json_decode($row['images'] ?? '[]', true);
                    if (isset($row['equipment'])) $row['equipment'] = json_decode($row['equipment'] ?? '[]', true);
                }
                $data[] = $row;
            }
        }
        if ($file === 'departments') {
            $includeManagement = ($_GET['include_management'] ?? '') === '1';
            if (!$includeManagement) {
                $data = array_values(array_filter($data, function ($d) {
                    $name = strtolower(trim((string)($d['dept_name'] ?? '')));
                    return $name !== 'management' && $name !== 'mangement';
                }));
            }
        }
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
}
?>
