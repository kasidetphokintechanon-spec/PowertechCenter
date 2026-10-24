<?php
// api/actions/cancel_ticket.php

$id = $_POST['id'] ?? '';
$role = $_SESSION['role'] ?? 'guest';
$sessionUserId = (string)($_SESSION['user_id'] ?? '');
$sessionName = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? '');

if (!$sessionUserId) throw new Exception("Unauthorized");
if (!$id) throw new Exception("ID required");

$stmt_chk = $conn->prepare("SELECT requester_id, requester, status FROM it_logs WHERE id = ? LIMIT 1");
if (!$stmt_chk) throw new Exception("Prepare failed");
$stmt_chk->bind_param("s", $id);
$stmt_chk->execute();
$res_chk = $stmt_chk->get_result();
$log = $res_chk ? $res_chk->fetch_assoc() : null;
if (!$log) throw new Exception("Ticket not found");

$isPrivileged = in_array($role, ['admin', 'staff'], true);
$isOwner = false;
if (!empty($log['requester_id'])) {
    $isOwner = ((string)$log['requester_id'] === $sessionUserId);
} else {
    $isOwner = ($sessionName !== '' && (string)($log['requester'] ?? '') === $sessionName);
}

if (!$isPrivileged && !$isOwner) throw new Exception("Access Denied");
if (!$isPrivileged && ($log['status'] ?? '') !== 'Pending') throw new Exception("Cannot cancel this ticket");

$reason = $_POST['reason'] ?? '';
if (empty($reason)) throw new Exception("Cancellation reason required");

// Fetch ticket info before update (for notification)
$stmt_info = $conn->prepare("SELECT requester, problem, company FROM it_logs WHERE id = ? LIMIT 1");
$stmt_info->bind_param("s", $id);
$stmt_info->execute();
$ticketInfo = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

$stmt = $conn->prepare("UPDATE it_logs SET status = 'Cancelled', solution = ? WHERE id = ?");
$stmt->bind_param("ss", $reason, $id);
if ($stmt->execute()) {
    // Notification
    if ($ticketInfo) {
        require_once __DIR__ . '/../../../includes/notify_helper.php';
        $cancellerName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้';
        ticketNotify($conn, 'it_cancel', 'ยกเลิกงานซ่อม', $id, ['ยกเลิกโดย' => $cancellerName, 'เหตุผล' => $reason]);
    }
    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("Update failed");
}
?>
