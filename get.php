<?php
// api/actions/get.php

$file = $_GET['file'] ?? '';

if ($file === 'employees') {
    $status_filter = $_GET['status_filter'] ?? '';
    $sql = "SELECT * FROM employees";
    if ($status_filter === 'active') {
        $sql .= " WHERE employment_status = 'active'";
    } elseif ($status_filter === 'inactive') {
        $sql .= " WHERE employment_status = 'inactive'";
    }
    
    $result = $conn->query($sql);
    $employees = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['assignments'] = []; // เตรียม array สำหรับเก็บสังกัด
            $employees[$row['id']] = $row;
        }
    }

    if (!empty($employees)) {
        // ดึงข้อมูลสังกัดทั้งหมดมาจับคู่กับพนักงาน
        $assign_res = $conn->query("SELECT * FROM employee_assignments");
        if ($assign_res) {
            while ($assign = $assign_res->fetch_assoc()) {
                if (isset($employees[$assign['employee_id']])) {
                    $employees[$assign['employee_id']]['assignments'][] = $assign;
                }
            }
        }
    }
    
    // กรองข้อมูลสำหรับ Staff (ให้เห็นเฉพาะแผนกที่รับผิดชอบ)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'staff' && !empty($_SESSION['responsible_department'])) {
        $allowed_depts = array_map('trim', explode(',', $_SESSION['responsible_department']));
        $filtered_employees = [];
        foreach ($employees as $emp) {
            $has_access = ($emp['id'] == $_SESSION['user_id']); // เห็นตัวเองเสมอ
            if (!$has_access) {
                foreach ($emp['assignments'] as $assign) {
                    if (in_array($assign['department'], $allowed_depts)) {
                        $has_access = true;
                        break;
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
        $table = $tableMap[$file];
        $sql = "SELECT * FROM $table";
        $params = [];
        $types = "";
        $where_clauses = [];

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

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        // เรียงลำดับ Services ตาม Sort Order
        if ($file === 'services') {
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
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
}
?>