<?php
// api/actions/update_employee.php

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'guest'), ['admin', 'staff'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'is_executive'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN is_executive TINYINT(1) NOT NULL DEFAULT 0 AFTER position");
}

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'is_management'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN is_management TINYINT(1) NOT NULL DEFAULT 0 AFTER is_executive");
}

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'is_technician'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN is_technician TINYINT(1) NOT NULL DEFAULT 0 AFTER is_management");
}

// จัดการรูปภาพพนักงาน
$imagePath = null;
if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imageFile']['name'], PATHINFO_EXTENSION);
    $newDetails = uniqid('emp_') . '.' . $ext;
    $target = "uploads/employees/" . $newDetails;
    if (!file_exists("uploads/employees/")) mkdir("uploads/employees/", 0777, true);
    if (move_uploaded_file($_FILES['imageFile']['tmp_name'], $target)) {
        $imagePath = $newDetails;
    }
}

$id = $_POST['id'] ?? '';
$name = $_POST['name'] ?? '';
$name_th = $_POST['name_th'] ?? '';
$position = $_POST['position'] ?? '';
$phone = $_POST['phone'] ?? '';
$birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : NULL;
$start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : NULL;
$status = $_POST['employment_status'] ?? 'active';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$responsible_department = $_POST['responsible_department'] ?? null;
$is_executive = (($_POST['is_executive'] ?? '0') === '1') ? 1 : 0;
$is_management = (($_POST['is_management'] ?? '0') === '1') ? 1 : 0;
$is_technician = (($_POST['is_technician'] ?? '0') === '1') ? 1 : 0;
$assignmentsJSON = $_POST['assignments'] ?? '[]'; // JSON String
$assignments = json_decode($assignmentsJSON, true);
if (!is_array($assignments)) $assignments = [];

$validAssignments = array_values(array_filter($assignments, function($assign) {
    $company = trim((string)($assign['company'] ?? ''));
    $department = trim((string)($assign['department'] ?? ''));
    $email = trim((string)($assign['email'] ?? ''));
    return $company !== '' || $department !== '' || $email !== '';
}));

$sessionRole = $_SESSION['role'] ?? 'guest';
if ($sessionRole === 'staff' && $id !== '') {
    $stmt_current = $conn->prepare("SELECT username, role, responsible_department, is_executive, is_management, is_technician FROM employees WHERE id = ? LIMIT 1");
    if ($stmt_current) {
        $stmt_current->bind_param("s", $id);
        $stmt_current->execute();
        $res_current = $stmt_current->get_result();
        $row_current = $res_current ? $res_current->fetch_assoc() : null;
        if ($row_current) {
            $username = (string)($row_current['username'] ?? '');
            $role = (string)($row_current['role'] ?? 'user');
            $responsible_department = $row_current['responsible_department'] ?? null;
            $is_executive = intval($row_current['is_executive'] ?? 0) ? 1 : 0;
            $is_management = intval($row_current['is_management'] ?? 0) ? 1 : 0;
            $is_technician = intval($row_current['is_technician'] ?? 0) ? 1 : 0;
        }
    }
    $password = '';
}

if ($is_technician === 1 && count($validAssignments) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ทีมช่างต้องมีอย่างน้อย 1 สังกัด']);
    exit;
}

$conn->begin_transaction();
try {
    $sql = "UPDATE employees SET name=?, name_th=?, position=?, is_executive=?, is_management=?, is_technician=?, phone=?, birthdate=?, start_date=?, employment_status=?, username=?, role=?, responsible_department=?";
    $params = [$name, $name_th, $position, $is_executive, $is_management, $is_technician, $phone, $birthdate, $start_date, $status, $username, $role, $responsible_department];
    $types = "sssiii" . str_repeat("s", 7);
    
    if (!empty($password)) {
        $sql .= ", password=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
        $types .= "s";
    }
    if ($imagePath) {
        $sql .= ", image=?";
        $params[] = $imagePath;
        $types .= "s";
    }
    $sql .= " WHERE id=?";
    $params[] = $id;
    $types .= "s";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Prepare failed (Update Employee): " . $conn->error);
    $bind = [];
    $bind[] = $types;
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    if (!call_user_func_array([$stmt, 'bind_param'], $bind)) {
        throw new Exception("Bind failed (Update Employee): " . $stmt->error);
    }
    if (!$stmt->execute()) throw new Exception($stmt->error);

    // Delete old assignments before inserting new ones
    $delete_stmt = $conn->prepare("DELETE FROM employee_assignments WHERE employee_id = ?");
    if (!$delete_stmt) throw new Exception("Prepare failed (Delete Assignments): " . $conn->error);
    $delete_stmt->bind_param("s", $id);
    if (!$delete_stmt->execute()) throw new Exception("Execute failed (Delete Assignments): " . $delete_stmt->error);

    // Insert new assignments
    if (!empty($validAssignments)) {
        $assign_sql = "INSERT INTO employee_assignments (employee_id, company, department, email, is_primary) VALUES (?, ?, ?, ?, ?)";
        $assign_stmt = $conn->prepare($assign_sql);
        if (!$assign_stmt) throw new Exception("Prepare failed (Insert Assignments): " . $conn->error);

        foreach ($validAssignments as $assign) {
            $is_primary = isset($assign['is_primary']) && $assign['is_primary'] ? 1 : 0;
            $company = (string)($assign['company'] ?? '');
            $department = (string)($assign['department'] ?? '');
            $email = (string)($assign['email'] ?? '');
            if ($department === '') $department = null;
            $assign_stmt->bind_param("ssssi", $id, $company, $department, $email, $is_primary);
            if (!$assign_stmt->execute()) throw new Exception("Execute failed (Insert Assignment): " . $assign_stmt->error);
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
