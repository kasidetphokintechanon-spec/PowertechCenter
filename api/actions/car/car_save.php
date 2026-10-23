<?php
// api/actions/car/car_save.php — เพิ่ม/แก้รถ · เลิกใช้ = ปิด (ไม่ลบ — ใบจองเดิมยังอ้างถึง) · สิทธิ์ car.manage · แก้ได้เฉพาะรถของบริษัทที่ดูแล
_initCarTables($conn);
$id = (int)($_POST['id'] ?? 0);
$company = (string)($_POST['company'] ?? '');
if ($company !== '' && !in_array($company, ACCESS_COS, true)) throw new Exception('บริษัทไม่ถูกต้อง');
if ($id) {
    $o = $conn->prepare("SELECT company FROM cr_cars WHERE id = ?"); $o->bind_param('i', $id); $o->execute();
    $was = $o->get_result()->fetch_row();
    if (!$was) throw new Exception('ไม่พบรถ');
    if (!carKeeps($conn, $was[0])) throw new Exception('ไม่ได้ดูแลรถคันนี้');
}
if ($company !== '' && !carKeeps($conn, $company)) throw new Exception("ไม่มีสิทธิ์ดูแลรถของ {$company}");
$company = $company === '' ? null : $company;
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$plate = $f('plate', 30);
if ($plate === '') throw new Exception('ใส่ทะเบียนรถ');
$prov = $f('province', 60); $name = $f('name', 150); $brand = $f('brand', 60); $type = $f('car_type', 30); $color = $f('color', 60); $note = $f('note', 500);
$seats = max(0, min(99, (int)($_POST['seats'] ?? 0)));
$active = !empty($_POST['is_active']) ? 1 : 0;
$sort = (int)($_POST['sort_order'] ?? 0);
$photo = null;
if (!empty($_FILES['photo']['name'])) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) throw new Exception('รูปต้องเป็น jpg / png / webp');
    if ($_FILES['photo']['size'] > 3 * 1024 * 1024) throw new Exception('รูปต้องไม่เกิน 3MB');
    if (!@getimagesize($_FILES['photo']['tmp_name'])) throw new Exception('ไฟล์ไม่ใช่รูปภาพ');
    $dir = __DIR__ . '/../../../uploads/cars/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fn = 'car-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $fn)) throw new Exception('อัปโหลดรูปไม่สำเร็จ');
    $photo = 'uploads/cars/' . $fn;
}
if ($id) {
    $u = $conn->prepare("UPDATE cr_cars SET plate = ?, province = ?, name = ?, brand = ?, car_type = ?, seats = ?, color = ?, company = ?, note = ?, is_active = ?, sort_order = ?" . ($photo ? ', photo = ?' : '') . " WHERE id = ?");
    if ($photo) $u->bind_param('sssssisssiisi', $plate, $prov, $name, $brand, $type, $seats, $color, $company, $note, $active, $sort, $photo, $id);
    else $u->bind_param('sssssisssiii', $plate, $prov, $name, $brand, $type, $seats, $color, $company, $note, $active, $sort, $id);
    $u->execute();
} else {
    $u = $conn->prepare("INSERT INTO cr_cars (plate, province, name, brand, car_type, seats, color, company, note, is_active, sort_order, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $u->bind_param('sssssisssiis', $plate, $prov, $name, $brand, $type, $seats, $color, $company, $note, $active, $sort, $photo); $u->execute();
    $id = $conn->insert_id;
}
echo json_encode(['status' => 'success', 'id' => $id]);
