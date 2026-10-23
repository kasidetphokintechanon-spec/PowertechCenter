<?php
// api/actions/system/perm_save.php — บันทึกบทบาท + หน้าที่ + บริษัทที่ดูแล ของ 1 คน · สิทธิ์ admin.manage_permissions ($ACTION_PERMS)
// เขียนเป็นสิทธิ์รายคนทั้งหมด (เลิกผูกชุดสิทธิ์เดิมของคนนั้น) — ผลลัพธ์ตรงกับการ์ดที่เห็น · บันทึก audit_log ทุกครั้ง (TISAX)
_initAccessTables($conn);
$uid = trim((string)($_POST['user_id'] ?? ''));
$role = (string)($_POST['role'] ?? 'user');
$duties = json_decode((string)($_POST['duties'] ?? '[]'), true);
$cos = json_decode((string)($_POST['companies'] ?? '[]'), true);
if (!in_array($role, ['user', 'staff', 'admin'], true)) throw new Exception('บทบาทไม่ถูกต้อง');
if (!is_array($duties) || !is_array($cos)) throw new Exception('ข้อมูลไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$duties = array_values(array_intersect(array_keys(ACCESS_DUTIES), $duties));
$cos = array_values(array_intersect(ACCESS_COS, $cos));
$me = (string)$_SESSION['user_id'];
$iAmAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($uid === $me) throw new Exception('แก้สิทธิ์ของตัวเองไม่ได้ — ให้ Admin กลางอีกคนแก้ให้');
$st = $conn->prepare("SELECT id, name, role FROM employees WHERE id = ? AND employment_status = 'active'");
$st->bind_param('s', $uid); $st->execute();
$emp = $st->get_result()->fetch_assoc();
if (!$emp) throw new Exception('ไม่พบพนักงาน');
$oldRole = in_array($emp['role'], ['admin', 'staff'], true) ? $emp['role'] : 'user';
// ตั้ง/ถอด Admin กลาง และให้/ถอดหน้าที่ "จัดการสิทธิ์ผู้ใช้" ได้เฉพาะ Admin กลาง — กันเจ้าหน้าที่ยกสิทธิ์ให้ตัวเองผ่านคนอื่น
if (!$iAmAdmin && ($role === 'admin' || $oldRole === 'admin')) throw new Exception('ตั้งหรือแก้ Admin กลางได้เฉพาะ Admin กลาง');
$oldPerms = $oldRole === 'admin' ? [] : getEffectivePermissionsForUserId($conn, $uid, $oldRole);
$oldDuties = $oldRole === 'admin' ? array_keys(ACCESS_DUTIES) : dutiesFromPerms($oldPerms);
if (!$iAmAdmin && in_array('perm', $duties, true) !== in_array('perm', $oldDuties, true)) throw new Exception('ให้หรือถอดหน้าที่ "จัดการสิทธิ์ผู้ใช้" ได้เฉพาะ Admin กลาง');
if ($role !== 'staff') { $duties = []; $cos = []; }
$s = $conn->prepare("SELECT company FROM user_company_scope WHERE user_id = ?"); $s->bind_param('s', $uid); $s->execute();
$oldCos = array_column($s->get_result()->fetch_all(), 0);

$keys = getPowertechPermissionKeys();
$grant = [];
foreach ($duties as $d) $grant = array_merge($grant, ACCESS_DUTIES[$d]['perms']);
if ($grant) $grant[] = 'admin.access_admin';
$grant = array_values(array_unique($grant));
$conn->begin_transaction();
try {
    $u = $conn->prepare("UPDATE employees SET role = ? WHERE id = ?"); $u->bind_param('ss', $role, $uid); $u->execute();
    $ph = implode(',', array_fill(0, count($keys), '?'));
    $d = $conn->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_key IN ($ph)");
    $p = array_merge([$uid], $keys); $d->bind_param(str_repeat('s', count($p)), ...$p); $d->execute();
    $d = $conn->prepare("DELETE FROM user_permission_roles WHERE user_id = ?"); $d->bind_param('s', $uid); $d->execute();
    $i = $conn->prepare("INSERT INTO user_permissions (user_id, permission_key, is_granted) VALUES (?, ?, 1)");
    foreach ($grant as $k) { $i->bind_param('ss', $uid, $k); $i->execute(); }
    $d = $conn->prepare("DELETE FROM user_company_scope WHERE user_id = ?"); $d->bind_param('s', $uid); $d->execute();
    $i = $conn->prepare("INSERT INTO user_company_scope (user_id, company) VALUES (?, ?)");
    foreach ($cos as $c) { $i->bind_param('ss', $uid, $c); $i->execute(); }
    $by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? $me;
    $detail = json_encode(['name' => $emp['name'], 'before' => ['role' => $oldRole, 'duties' => $oldDuties, 'companies' => $oldCos], 'after' => ['role' => $role, 'duties' => $duties, 'companies' => $cos]], JSON_UNESCAPED_UNICODE);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $a = $conn->prepare("INSERT INTO audit_log (user_id, user_name, action, target_id, details, ip_address) VALUES (?, ?, 'permission_change', ?, ?, ?)");
    if ($a) { $a->bind_param('sssss', $me, $by, $uid, $detail, $ip); $a->execute(); }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
echo json_encode(['status' => 'success', 'role' => $role, 'duties' => $duties, 'companies' => $cos]);
