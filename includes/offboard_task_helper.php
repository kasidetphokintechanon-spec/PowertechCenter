<?php
// includes/offboard_task_helper.php
// เชื่อมขั้นตอนพนักงานลาออก: HR ตั้ง inactive → สร้างงานใน Task Center ให้ IT → IT ติ๊กเช็กลิสต์หน้า Offboard → งานปิดเอง
// งานพวกนี้จำพนักงานด้วย personal_tasks.offboard_employee_id (ไม่ใช้ tags/title เพราะแก้ได้จากหน้า Task Center)

require_once __DIR__ . '/../api/actions/ticket/personal_tasks_schema.php';

const OFFBOARD_TASK_DEPT = 'Information Technology';

// รวบรวมว่าต้องปิดระบบอะไร/รับคืนเครื่องอะไร จาก setup record ล่าสุด + ทะเบียนทรัพย์สิน
function collectOffboardInfo(mysqli $conn, string $employeeId): array {
    $granted = [];
    $printerBuildings = [];
    $company = null;

    // รวมจากทุก setup record (1 คนอาจมีหลายเครื่อง) และไม่กรอง status='active'
    // — IT อาจเริ่มติ๊กเช็กลิสต์ (record กลายเป็น offboarded) ก่อน HR กด inactive
    $stmt = $conn->prepare("SELECT company, granted_systems, printer_buildings
                             FROM setup_records WHERE employee_id = ?
                             ORDER BY (status = 'active') DESC, updated_at DESC");
    $hasSetupRecord = false;
    if ($stmt) {
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($rec = $res->fetch_assoc()) {
            $hasSetupRecord = true;
            if (!$company && !empty($rec['company'])) $company = $rec['company'];
            $granted += array_filter(json_decode($rec['granted_systems'] ?? '{}', true) ?: []);
            $printerBuildings = array_merge($printerBuildings, json_decode($rec['printer_buildings'] ?? '[]', true) ?: []);
        }
    }

    // ไม่มี setup record (พนักงานเก่า) → ใช้บริษัทหลักจากสังกัดแทน
    if (!$company) {
        $stmtC = $conn->prepare("SELECT company FROM employee_assignments WHERE employee_id = ? ORDER BY is_primary DESC LIMIT 1");
        if ($stmtC) {
            $stmtC->bind_param("s", $employeeId);
            $stmtC->execute();
            $rowC = $stmtC->get_result()->fetch_assoc();
            $company = $rowC['company'] ?? null;
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
    $systems = ['Windows Login / AD', 'Internet / VPN'];
    foreach ($labels as $key => $label) {
        if (!empty($granted[$key])) $systems[] = $label;
    }
    if (!empty($printerBuildings)) $systems[] = 'รหัสเครื่องพิมพ์';

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

    return ['company' => $company, 'systems' => $systems, 'assets' => $assets, 'has_setup_record' => $hasSetupRecord];
}

// สร้างงาน offboard ให้ IT (ถ้ามีงานที่ยังไม่เสร็จของคนนี้อยู่แล้ว จะไม่สร้างซ้ำ)
function createOffboardTask(mysqli $conn, string $employeeId, string $employeeName, ?string $resignDate, string $createdBy, array $info): ?int {
    ensurePersonalTasksSchema($conn);

    $stmtDup = $conn->prepare("SELECT id FROM personal_tasks WHERE offboard_employee_id = ? AND status <> 'done' LIMIT 1");
    $stmtDup->bind_param("s", $employeeId);
    $stmtDup->execute();
    if ($stmtDup->get_result()->fetch_assoc()) return null;

    $lines = [];
    $lines[] = "พนักงานพ้นสภาพ: {$employeeName} (รหัส {$employeeId})";
    $lines[] = 'วันที่ออก: ' . ($resignDate ?: 'ไม่ระบุ');
    $lines[] = '';
    $lines[] = 'ระบบที่ต้องปิด: ' . implode(', ', $info['systems']);
    $lines[] = 'ทรัพย์สินที่ต้องรับคืน: ' . ($info['assets'] ? implode(', ', $info['assets']) : 'ไม่พบในทะเบียน (ตรวจสอบเพิ่มเติม)');
    if (!$info['has_setup_record']) {
        $lines[] = '';
        $lines[] = '⚠️ ไม่มีข้อมูลการติดตั้งเครื่องในระบบ — ให้ตรวจสอบสิทธิ์ด้วยตนเอง';
    }
    $description = implode("\n", $lines);

    $title = mb_substr("ปิดสิทธิ์พนักงานลาออก — {$employeeName}", 0, 255, 'UTF-8');
    $company = $info['company'];
    $dept = OFFBOARD_TASK_DEPT;
    $due = $resignDate ?: null;

    $stmt = $conn->prepare("INSERT INTO personal_tasks
        (user_id, company, department, task_type, title, description, status, progress_pct, priority, due_date, tags, share_scope, workflow_stage, offboard_employee_id)
        VALUES (?, ?, ?, 'personal', ?, ?, 'todo', 0, 'high', ?, 'offboard', 'department', 'todo', ?)");
    if (!$stmt) {
        error_log('[Offboard] create task prepare failed: ' . $conn->error);
        return null;
    }
    $stmt->bind_param("sssssss", $createdBy, $company, $dept, $title, $description, $due, $employeeId);
    if (!$stmt->execute()) {
        error_log('[Offboard] create task failed: ' . $stmt->error);
        return null;
    }
    return (int)$conn->insert_id;
}

// HR เปลี่ยนกลับเป็น active (กดผิด/ยกเลิกลาออก) → ลบงาน offboard ที่ยังไม่เสร็จทิ้ง
function removeOpenOffboardTasks(mysqli $conn, string $employeeId): void {
    ensurePersonalTasksSchema($conn);
    $stmt = $conn->prepare("DELETE FROM personal_tasks WHERE offboard_employee_id = ? AND status <> 'done'");
    if ($stmt) {
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
    }
}

// IT บันทึกเช็กลิสต์หน้า Offboard → อัปเดตความคืบหน้างานที่ยังเปิดอยู่
// รวมเช็กลิสต์จากทุก setup record ของคนนั้น (1 คนอาจมีหลายเครื่อง) — ครบทุกเครื่อง = ปิดงาน
// งานที่ done ไปแล้วไม่แตะ (กันบันทึกซ้ำทีหลังแล้วงานเด้งกลับมาเปิด)
function syncOffboardTaskFromChecklist(mysqli $conn, string $employeeId): void {
    if ($employeeId === '') return;
    ensurePersonalTasksSchema($conn);

    $total = 0;
    $done = 0;
    $stmtR = $conn->prepare("SELECT offboard_checklist FROM setup_records WHERE employee_id = ?");
    $stmtR->bind_param("s", $employeeId);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    while ($rec = $resR->fetch_assoc()) {
        $items = json_decode($rec['offboard_checklist'] ?? '', true);
        if (!is_array($items) || empty($items)) {
            $total++; // เครื่องที่ยังไม่เริ่มเช็กลิสต์เลย นับเป็นค้าง 1 ข้อ
            continue;
        }
        foreach ($items as $item) {
            $state = $item['state'] ?? 'pending';
            if ($state === 'skipped') continue;
            $total++;
            if ($state === 'done') $done++;
        }
    }
    if ($total === 0 && $done === 0 && $resR->num_rows === 0) return;

    $allDone = ($done === $total);
    $pct = $total > 0 ? (int)round($done * 100 / $total) : 100;
    $status = $allDone ? 'done' : ($done > 0 ? 'doing' : 'todo');
    $detail = $allDone ? 'IT ปิดสิทธิ์ครบแล้ว' : "เช็กลิสต์ {$done}/{$total}";

    $stmt = $conn->prepare("UPDATE personal_tasks
        SET status = ?, workflow_stage = ?, progress_pct = ?, status_detail = ?
        WHERE offboard_employee_id = ? AND status <> 'done'");
    if ($stmt) {
        $stmt->bind_param("ssiss", $status, $status, $pct, $detail, $employeeId);
        $stmt->execute();
    }
}

// HR แก้วันที่ออกทีหลัง (หลังตั้ง inactive ไปแล้ว) → อัปเดตกำหนดส่งของงานที่ยังเปิดอยู่
function updateOffboardTaskDueDate(mysqli $conn, string $employeeId, ?string $resignDate): void {
    ensurePersonalTasksSchema($conn);
    $stmt = $conn->prepare("UPDATE personal_tasks SET due_date = ? WHERE offboard_employee_id = ? AND status <> 'done'");
    if ($stmt) {
        $stmt->bind_param("ss", $resignDate, $employeeId);
        $stmt->execute();
    }
}
