<?php
// api/actions/system/get_notify_channels.php
$_role  = $_SESSION['role'] ?? 'guest';
$_perms = $_SESSION['effective_permissions'] ?? [];
$_ok    = $_role === 'admin' || in_array('*', $_perms, true) || in_array('settings.notifications', $_perms, true);
if (!$_ok) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit;
}

_initNotifyChannelsTable($conn);

$res = $conn->query("SELECT id, name, channel_type,
    CONCAT(LEFT(token, 8), '...') AS token_preview,
    dest_id, events, company, enabled, sort_order
    FROM notify_channels ORDER BY sort_order ASC, id ASC");

$channels = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['events'] = json_decode($row['events'], true) ?? ['*'];
        $channels[] = $row;
    }
}
echo json_encode(['status' => 'success', 'channels' => $channels]);
