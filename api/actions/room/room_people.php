<?php
// api/actions/room/room_people.php — รายชื่อพนักงานสำหรับช่องพิมพ์ชื่อ (ประชุมของ / ผู้เข้าร่วม) ลดพิมพ์ผิด · ต้องล็อกอิน (ด่านกลาง)
// ส่งเฉพาะชื่อ ชื่อเล่น แผนก บริษัท — ข้อมูลระดับเดียวกับสมุดรายชื่อ ไม่มีเบอร์/อีเมล
$rows = $conn->query("SELECT e.id, e.name, e.nickname,
        (SELECT a.department FROM employee_assignments a WHERE a.employee_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci ORDER BY a.is_primary DESC, a.assignment_id LIMIT 1) AS department,
        (SELECT a.company FROM employee_assignments a WHERE a.employee_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci ORDER BY a.is_primary DESC, a.assignment_id LIMIT 1) AS company
    FROM employees e WHERE e.employment_status = 'active' ORDER BY e.name")->fetch_all(MYSQLI_ASSOC);
$out = array_map(fn($r) => ['n' => trim($r['name']), 'k' => trim((string)$r['nickname']), 'd' => (string)$r['department'], 'c' => (string)$r['company']], $rows);
echo json_encode(['status' => 'success', 'people' => $out], JSON_UNESCAPED_UNICODE);
