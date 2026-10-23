<?php
// api/actions/room/room_save.php — เพิ่ม/แก้ห้อง · ปิดห้องชั่วคราว (ไม่ลบ — การจองเดิมยังอ้างถึง) · สิทธิ์ room.manage
// ห้องใหม่และการเลือกผู้ดูแลห้อง ทำได้เฉพาะผู้ดูแลทุกห้อง (admin / ธุรการกลาง) — ผู้ดูแลรายห้องแก้ได้แค่ห้องตัวเอง
_initRoomTables($conn);
$id = (int)($_POST['id'] ?? 0);
$all = roomIsCentral($conn);
if ($id ? !roomKeeps($conn, $id) : !$all) throw new Exception('ไม่ได้ดูแลห้องนี้');
// ห้องเป็นของบริษัทไหน — ตั้งได้เฉพาะบริษัทที่ดูแล (ว่าง = ยังไม่ระบุ)
$company = (string)($_POST['company'] ?? '');
if ($company !== '' && !in_array($company, ACCESS_COS, true)) throw new Exception('บริษัทไม่ถูกต้อง');
$was = null;
if ($id) { $o = $conn->prepare("SELECT company FROM rm_rooms WHERE id = ?"); $o->bind_param('i', $id); $o->execute(); $was = (string)($o->get_result()->fetch_row()[0] ?? ''); }
// ผู้ดูแลรายห้องแก้ห้องที่ได้รับมอบได้ แม้เป็นบริษัทอื่น — แต่ย้ายบริษัทของห้องได้เฉพาะภายในบริษัทที่ดูแล
if ($company !== '' && $company !== $was && !inScope($conn, $company)) throw new Exception("ไม่มีสิทธิ์ดูแลห้องของ {$company}");
$company = $company === '' ? null : $company;
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$name = $f('name', 150);
if ($name === '') throw new Exception('ใส่ชื่อห้อง');
$building = $f('building', 150); $equip = $f('equipment', 500); $cn = $f('contact_name', 150); $cp = $f('contact_phone', 50); $note = $f('note', 500);
$seats = max(0, min(999, (int)($_POST['seats'] ?? 0)));
$active = !empty($_POST['is_active']) ? 1 : 0;
$appr = !empty($_POST['needs_approval']) ? 1 : 0;
$sort = (int)($_POST['sort_order'] ?? 0);
$photo = null;
if (!empty($_FILES['photo']['name'])) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) throw new Exception('รูปต้องเป็น jpg / png / webp');
    if ($_FILES['photo']['size'] > 3 * 1024 * 1024) throw new Exception('รูปต้องไม่เกิน 3MB');
    if (!@getimagesize($_FILES['photo']['tmp_name'])) throw new Exception('ไฟล์ไม่ใช่รูปภาพ');
    $dir = __DIR__ . '/../../../uploads/rooms/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fn = 'room-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $fn)) throw new Exception('อัปโหลดรูปไม่สำเร็จ');
    $photo = 'uploads/rooms/' . $fn;
}
if ($id) {
    $u = $conn->prepare("UPDATE rm_rooms SET name = ?, building = ?, seats = ?, company = ?, equipment = ?, contact_name = ?, contact_phone = ?, note = ?, is_active = ?, needs_approval = ?, sort_order = ?" . ($photo ? ', photo = ?' : '') . " WHERE id = ?");
    if ($photo) $u->bind_param('ssisssssiiisi', $name, $building, $seats, $company, $equip, $cn, $cp, $note, $active, $appr, $sort, $photo, $id);
    else $u->bind_param('ssisssssiiii', $name, $building, $seats, $company, $equip, $cn, $cp, $note, $active, $appr, $sort, $id);
    $u->execute();
} else {
    $u = $conn->prepare("INSERT INTO rm_rooms (name, building, seats, company, equipment, contact_name, contact_phone, note, is_active, needs_approval, sort_order, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $u->bind_param('ssisssssiiis', $name, $building, $seats, $company, $equip, $cn, $cp, $note, $active, $appr, $sort, $photo); $u->execute();
    $id = $conn->insert_id;
}
if ($all && isset($_POST['keepers'])) {
    $ks = json_decode((string)$_POST['keepers'], true);
    if (is_array($ks)) {
        $d = $conn->prepare("DELETE FROM rm_room_keepers WHERE room_id = ?"); $d->bind_param('i', $id); $d->execute();
        $i = $conn->prepare("INSERT IGNORE INTO rm_room_keepers (room_id, user_id) VALUES (?, ?)");
        foreach (array_slice(array_unique(array_map('strval', $ks)), 0, 10) as $k) { $i->bind_param('is', $id, $k); $i->execute(); }
    }
}
echo json_encode(['status' => 'success', 'id' => $id]);
