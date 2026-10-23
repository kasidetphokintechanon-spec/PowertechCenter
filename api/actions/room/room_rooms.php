<?php
// api/actions/room/room_rooms.php — รายการห้องทั้งหมด (รวมที่ปิด) + ผู้ดูแลแต่ละห้อง สำหรับหน้าจัดการ · สิทธิ์ room.manage
_initRoomTables($conn);
$rooms = $conn->query("SELECT * FROM rm_rooms ORDER BY is_active DESC, sort_order, name")->fetch_all(MYSQLI_ASSOC);
$kp = [];
foreach ($conn->query("SELECT k.room_id, k.user_id, e.name FROM rm_room_keepers k LEFT JOIN employees e ON e.id COLLATE utf8mb4_unicode_ci = k.user_id COLLATE utf8mb4_unicode_ci")->fetch_all(MYSQLI_ASSOC) as $k) $kp[(int)$k['room_id']][] = ['id' => $k['user_id'], 'name' => $k['name'] ?: $k['user_id']];
foreach ($rooms as &$r) { $r['id'] = (int)$r['id']; $r['seats'] = (int)$r['seats']; $r['is_active'] = (int)$r['is_active']; $r['needs_approval'] = (int)$r['needs_approval']; $r['keepers'] = $kp[$r['id']] ?? []; $r['can_edit'] = roomKeeps($conn, $r['id']); }
unset($r);
// คนที่มีหน้าที่ดูแลห้องประชุม (ไว้เลือกเป็นผู้ดูแลห้อง)
$cand = [];
foreach ($conn->query("SELECT DISTINCT e.id, e.name FROM employees e JOIN user_permissions p ON p.user_id COLLATE utf8mb4_unicode_ci = e.id COLLATE utf8mb4_unicode_ci AND p.permission_key = 'room.manage' AND p.is_granted = 1 WHERE e.employment_status = 'active' ORDER BY e.name")->fetch_all(MYSQLI_ASSOC) as $c) $cand[] = $c;
echo json_encode(['status' => 'success', 'rooms' => $rooms, 'keeper_candidates' => $cand, 'is_admin' => ($_SESSION['role'] ?? '') === 'admin', 'kept_all' => roomIsCentral($conn), 'companies' => userCompanies($conn)], JSON_UNESCAPED_UNICODE);
