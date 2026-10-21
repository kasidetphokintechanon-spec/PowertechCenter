<?php
// includes/employee_contact_fields.php
// ช่องติดต่อเพิ่มเติมของพนักงานที่ใช้ใน Contact List: ชื่อเล่น (ค้นหาได้) และเบอร์ภายใน (Ext.)
// บันทึกแยกจาก INSERT/UPDATE หลักของ add_employee / update_employee เพื่อไม่ต้องแตะ bind_param ชุดใหญ่

function ensureEmployeeContactColumns(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $col = $conn->query("SHOW COLUMNS FROM employees LIKE 'nickname'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN nickname VARCHAR(100) NULL AFTER name_th");
    }
    $col = $conn->query("SHOW COLUMNS FROM employees LIKE 'phone_ext'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE employees ADD COLUMN phone_ext VARCHAR(20) NULL AFTER phone");
    }
}

// บันทึกเฉพาะช่องที่ฟอร์มส่งมา (ฟอร์มเก่าที่ไม่มีช่องนี้ = ไม่แตะค่าเดิม)
function saveEmployeeContactFields(mysqli $conn, string $employeeId): void {
    $sets = [];
    $params = [];
    if (array_key_exists('nickname', $_POST)) {
        $sets[] = 'nickname = ?';
        $v = trim((string)$_POST['nickname']);
        $params[] = $v === '' ? null : mb_substr($v, 0, 100, 'UTF-8');
    }
    if (array_key_exists('phone_ext', $_POST)) {
        $sets[] = 'phone_ext = ?';
        $v = trim((string)$_POST['phone_ext']);
        $params[] = $v === '' ? null : mb_substr($v, 0, 20, 'UTF-8');
    }
    if (!$sets || $employeeId === '') return;
    ensureEmployeeContactColumns($conn);
    $params[] = $employeeId;
    $stmt = $conn->prepare('UPDATE employees SET ' . implode(', ', $sets) . ' WHERE id = ?');
    if (!$stmt) throw new Exception('Prepare failed (contact fields): ' . $conn->error);
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    if (!$stmt->execute()) throw new Exception('Save failed (contact fields): ' . $stmt->error);
}
