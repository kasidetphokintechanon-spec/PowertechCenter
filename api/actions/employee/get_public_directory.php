<?php
// api/actions/employee/get_public_directory.php

$status_filter = $_GET['status_filter'] ?? 'active';
$status_filter = in_array($status_filter, ['active'], true) ? $status_filter : 'active';

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'is_executive'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN is_executive TINYINT(1) NOT NULL DEFAULT 0 AFTER position");
}

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'is_management'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN is_management TINYINT(1) NOT NULL DEFAULT 0 AFTER is_executive");
}

$sql = "SELECT id, name, name_th, position, is_executive, is_management, phone, image, employment_status, created_at, updated_at FROM employees";
if ($status_filter === 'active') {
    $sql .= " WHERE employment_status = 'active'";
}

$result = $conn->query($sql);
$employees = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['assignments'] = [];
        $employees[$row['id']] = $row;
    }
}

if (!empty($employees)) {
    $assign_res = $conn->query("SELECT employee_id, company, department, email, is_primary FROM employee_assignments");
    if ($assign_res) {
        while ($assign = $assign_res->fetch_assoc()) {
            $empId = $assign['employee_id'] ?? '';
            if ($empId !== '' && isset($employees[$empId])) {
                unset($assign['employee_id']);
                $employees[$empId]['assignments'][] = $assign;
            }
        }
    }
}

echo json_encode(array_values($employees));
