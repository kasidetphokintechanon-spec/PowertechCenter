<?php
// api/actions/car/car_driver_save.php — เพิ่ม/แก้คนขับ · ลาออก = ปิด (ไม่ลบ — ใบจองเดิมยังอ้างถึง) · สิทธิ์ car.manage
_initCarTables($conn);
$id = (int)($_POST['id'] ?? 0);
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$name = $f('name', 150);
if ($name === '') throw new Exception('ใส่ชื่อคนขับ');
$nick = $f('nickname', 60); $phone = $f('phone', 50);
$company = (string)($_POST['company'] ?? '');
if ($company !== '' && !in_array($company, ACCESS_COS, true)) throw new Exception('บริษัทไม่ถูกต้อง');
$company = $company === '' ? null : $company;
$active = !empty($_POST['is_active']) ? 1 : 0;
if ($id) { $u = $conn->prepare("UPDATE cr_drivers SET name = ?, nickname = ?, phone = ?, company = ?, is_active = ? WHERE id = ?"); $u->bind_param('ssssii', $name, $nick, $phone, $company, $active, $id); }
else { $u = $conn->prepare("INSERT INTO cr_drivers (name, nickname, phone, company, is_active) VALUES (?,?,?,?,?)"); $u->bind_param('ssssi', $name, $nick, $phone, $company, $active); }
$u->execute();
echo json_encode(['status' => 'success', 'id' => $id ?: $conn->insert_id]);
