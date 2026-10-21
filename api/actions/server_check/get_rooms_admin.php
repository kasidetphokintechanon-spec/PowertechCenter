<?php
// api/actions/server_check/get_rooms_admin.php
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }
if (function_exists('requirePermission')) requirePermission($conn, 'admin.manage_permissions');

$rooms = $conn->query(
    "SELECT id, code, name, location, company, sort_order,
        (SELECT COUNT(*) FROM sc_items WHERE room_id = sc_rooms.id AND is_active = 1) AS item_count
     FROM sc_rooms
     WHERE is_active = 1
     ORDER BY sort_order, id"
)->fetch_all(MYSQLI_ASSOC);

echo json_encode(['status' => 'success', 'data' => $rooms]);
