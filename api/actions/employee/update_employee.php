<?php
// api/actions/update_employee.php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}
requirePermission($conn, 'employee.manage');

$sessionRole = $_SESSION['role'] ?? 'guest';
$canEditSensitive = ($sessionRole === 'admin') || hasPermission($conn, 'admin.manage_permissions');

// แจ้งเตือน IT ให้ปิดสิทธิ์ระบบต่างๆ เมื่อพนักงานถูกตั้งเป็น inactive
// (HR เป็นคนกด inactive แต่ไม่รู้จัก ERP/MRP ฯลฯ เลยแจ้ง IT แทนที่จะเด้ง popup ให้ HR)
function notifyEmployeeOffboard(mysqli $conn, string $employeeId, string $employeeName): void {
    $granted = [];
    $printerBuildings = [];
    $company = null;

    $stmt = $conn->prepare("SELECT company, granted_systems, printer_buildings
                             FROM setup_records WHERE employee_id = ? AND status = 'active'
                             ORDER BY updated_at DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        $rec = $stmt->get_result()->fetch_assoc();
        if ($rec) {
            $company = $rec['company'] ?: null;
            $granted = json_decode($rec['granted_systems'] ?? '{}', true) ?: [];
            $printerBuildings = json_decode($rec['printer_buildings'] ?? '[]', true) ?: [];
        }
    }

    $labels = [
        'ptc'     => 'PowertechCenter',
        'erpN'    => 'ERP (ตัวใหม่)',
        'erpO'    => 'ERP (ตัวเก่า)',
        'mrpN'    => 'MRP (ตัวใหม่)',
        'mrpO'    => 'MRP (ตัวเก่า)',
        'ictrl'   => 'Icontrol',
        'carsys'  => 'CARSYS',
        'roomsys' => 'ROOMSYS',
    ];
    $systemsToClose = ['Windows Login / AD', 'Internet / VPN'];
    foreach ($labels as $key => $label) {
        if (!empty($granted[$key])) $systemsToClose[] = $label;
    }
    if (!empty($printerBuildings)) $systemsToClose[] = 'รหัสเครื่องพิมพ์';

    // ทรัพย์สินที่ยังถือครองอยู่ (ต้องรับคืน)
    $assets = [];
    $stmtA = $conn->prepare("SELECT equipment_id, name, hostname FROM it_assets WHERE custodian_employee_id = ? LIMIT 20");
    if ($stmtA) {
        $stmtA->bind_param("s", $employeeId);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($row = $resA->fetch_assoc()) {
            $label = trim(($row['equipment_id'] ?: '-') . ' — ' . ($row['name'] ?: ''));
            if (!empty($row['hostname'])) $label .= " ({$row['hostname']})";
            $assets[] = $label;
        }
    }

    $msg  = "🔴 <b>พนักงานพ้นสภาพ — ต้องปิดสิทธิ์</b>\n";
    $msg .= "ชื่อ: {$employeeName} (รหัสพนักงาน {$employeeId})\n";
    $msg .= "ระบบที่ต้องปิด: " . implode(', ', $systemsToClose) . "\n";
    $msg .= $assets
        ? "ทรัพย์สินที่ต้องรับคืน: " . implode(', ', $assets) . "\n"
        : "ทรัพย์สินที่ต้องรับคืน: ไม่พบในทะเบียน (ตรวจสอบเพิ่มเติม)\n";

    sendNotify($conn, $msg, 'employee_offboard', $company);
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

$colCheck = $conn->query("SHOW COLUMNS FROM employees LIKE 'can_manage_stock'");
if (!$colCheck || intval($colCheck->num_rows) === 0) {
    $conn->query("ALTER TABLE employees ADD COLUMN can_manage_stock TINYINT(1) NOT NULL DEFAULT 0 AFTER is_technician");
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
$manage_system_user = (($_POST['manage_system_user'] ?? '0') === '1') ? 1 : 0;
$is_executive = (($_POST['is_executive'] ?? '0') === '1') ? 1 : 0;
$is_management = (($_POST['is_management'] ?? '0') === '1') ? 1 : 0;
$is_technician = (($_POST['is_technician'] ?? '0') === '1') ? 1 : 0;
$can_manage_stock = (($_POST['can_manage_stock'] ?? '0') === '1') ? 1 : 0;
$assignmentsJSON = $_POST['assignments'] ?? '[]'; // JSON String
$assignments = json_decode($assignmentsJSON, true);
if (!is_array($assignments)) $assignments = [];

$validAssignments = array_values(array_filter($assignments, function($assign) {
    $company = trim((string)($assign['company'] ?? ''));
    $department = trim((string)($assign['department'] ?? ''));
    $email = trim((string)($assign['email'] ?? ''));
    return $company !== '' || $department !== '' || $email !== '';
}));

$roleProvided = array_key_exists('role', $_POST);
$responsibleProvided = array_key_exists('responsible_department', $_POST);
$canManageStockProvided = array_key_exists('can_manage_stock', $_POST);
$usernameProvided = array_key_exists('username', $_POST) && trim((string)($_POST['username'] ?? '')) !== '';
$currentEmployee = null;

if ($id !== '') {
    $stmtCurrent = $conn->prepare("SELECT username, role, responsible_department, is_executive, is_management, is_technician, can_manage_stock, employment_status FROM employees WHERE id = ? LIMIT 1");
    if ($stmtCurrent) {
        $stmtCurrent->bind_param("s", $id);
        $stmtCurrent->execute();
        $resCurrent = $stmtCurrent->get_result();
        $currentEmployee = $resCurrent ? $resCurrent->fetch_assoc() : null;
    }
}

if ($currentEmployee) {
    if (!$roleProvided) $role = (string)($currentEmployee['role'] ?? 'user');
    if (!$responsibleProvided) $responsible_department = $currentEmployee['responsible_department'] ?? null;
    if (!$canManageStockProvided) $can_manage_stock = intval($currentEmployee['can_manage_stock'] ?? 0) ? 1 : 0;
    if ($canEditSensitive && $manage_system_user === 1 && !$usernameProvided) {
        $username = (string)($currentEmployee['username'] ?? '');
    }
}

if (!$canEditSensitive && $currentEmployee) {
    $username = (string)($currentEmployee['username'] ?? '');
    $role = (string)($currentEmployee['role'] ?? 'user');
    $responsible_department = $currentEmployee['responsible_department'] ?? null;
    $is_executive = intval($currentEmployee['is_executive'] ?? 0) ? 1 : 0;
    $is_management = intval($currentEmployee['is_management'] ?? 0) ? 1 : 0;
    $is_technician = intval($currentEmployee['is_technician'] ?? 0) ? 1 : 0;
    $can_manage_stock = intval($currentEmployee['can_manage_stock'] ?? 0) ? 1 : 0;
    $password = '';
}

if ($canEditSensitive && $manage_system_user !== 1 && $currentEmployee) {
    $username = (string)($currentEmployee['username'] ?? '');
    $role = (string)($currentEmployee['role'] ?? 'user');
    $responsible_department = $currentEmployee['responsible_department'] ?? null;
    $can_manage_stock = intval($currentEmployee['can_manage_stock'] ?? 0) ? 1 : 0;
    $password = '';
}

if (!$canEditSensitive && $role === 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ตั้งค่า Admin']);
    exit;
}

if ($is_technician === 1 && count($validAssignments) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'ทีมช่างต้องมีอย่างน้อย 1 สังกัด']);
    exit;
}

$conn->begin_transaction();
try {
    $sql = "UPDATE employees SET name=?, name_th=?, position=?, is_executive=?, is_management=?, is_technician=?, can_manage_stock=?, phone=?, birthdate=?, start_date=?, employment_status=?, username=?, role=?, responsible_department=?";
    $params = [$name, $name_th, $position, $is_executive, $is_management, $is_technician, $can_manage_stock, $phone, $birthdate, $start_date, $status, $username, $role, $responsible_department];
    $types = "sssiiii" . str_repeat("s", 7);
    
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
    syncEmployeeStockFlagFromPermissions($conn, $id);

    $wasActive = ($currentEmployee['employment_status'] ?? 'active') !== 'inactive';
    if ($currentEmployee && $wasActive && $status === 'inactive') {
        notifyEmployeeOffboard($conn, $id, $name_th ?: $name);
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
?>
