<?php
// api/actions/system/perm_people.php — รายชื่อพนักงาน + บทบาท/หน้าที่/บริษัทที่ดูแล สำหรับหน้ากำหนดสิทธิ์ · สิทธิ์ admin.manage_permissions ($ACTION_PERMS)
// หน้าที่คำนวณจากสิทธิ์ที่มีผลจริง (ชุดสิทธิ์เดิม + สิทธิ์รายคน) — คนที่ใช้ชุดสิทธิ์เดิมจะเห็นการ์ดถูกติ๊กตรงกับสิทธิ์เดิม
_initAccessTables($conn);
$emps = $conn->query("SELECT id, name, role, position, image FROM employees WHERE employment_status = 'active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$asg = [];
foreach ($conn->query("SELECT employee_id, company, department, is_primary FROM employee_assignments ORDER BY is_primary DESC, assignment_id")->fetch_all(MYSQLI_ASSOC) as $a) $asg[$a['employee_id']][] = $a;
$scope = [];
foreach ($conn->query("SELECT user_id, company FROM user_company_scope")->fetch_all() as [$u, $c]) $scope[$u][] = $c;
$legacy = array_flip(array_column($conn->query("SELECT DISTINCT user_id FROM user_permission_roles")->fetch_all(), 0));
$out = [];
foreach ($emps as $e) {
    $id = (string)$e['id'];
    $role = in_array($e['role'], ['admin', 'staff'], true) ? $e['role'] : 'user';
    $perms = $role === 'admin' ? [] : getEffectivePermissionsForUserId($conn, $id, $role);
    $a = $asg[$id] ?? [];
    $out[] = ['id' => $id, 'name' => $e['name'], 'position' => $e['position'], 'image' => $e['image'], 'role' => $role,
        'department' => $a[0]['department'] ?? '', 'companies' => array_values(array_unique(array_filter(array_column($a, 'company')))),
        'duties' => $role === 'admin' ? array_keys(ACCESS_DUTIES) : dutiesFromPerms($perms),
        'scope' => array_values(array_intersect(ACCESS_COS, $scope[$id] ?? [])), 'legacy_sets' => isset($legacy[$id])];
}
$duties = [];
foreach (ACCESS_DUTIES as $k => $d) $duties[] = ['key' => $k, 't' => $d['t'], 'd' => $d['d'], 'scoped' => $d['scoped']];
echo json_encode(['status' => 'success', 'people' => $out, 'duties' => $duties, 'companies' => ACCESS_COS, 'me' => (string)$_SESSION['user_id'], 'i_am_admin' => ($_SESSION['role'] ?? '') === 'admin'], JSON_UNESCAPED_UNICODE);
