<?php
// api/actions/rate_ticket.php

$id = $_POST['id'] ?? '';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$role = $_SESSION['role'] ?? 'guest';
$sessionUserId = (string)($_SESSION['user_id'] ?? '');
$sessionName = (string)($_SESSION['display_name'] ?? $_SESSION['username'] ?? '');

if (!$sessionUserId) throw new Exception("Unauthorized");
if (!$id) throw new Exception("ID required");
if ($rating < 1 || $rating > 5) throw new Exception("Invalid rating");

$stmt_chk = $conn->prepare("SELECT requester_id, requester, status, rating FROM it_logs WHERE id = ? LIMIT 1");
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
if (($log['status'] ?? '') !== 'Completed') throw new Exception("Ticket is not completed");
if (!empty($log['rating'])) throw new Exception("Ticket already rated");

$stmt = $conn->prepare("UPDATE it_logs SET rating = ?, closed_date = IFNULL(closed_date, NOW()) WHERE id = ?");
$stmt->bind_param("is", $rating, $id);
if ($stmt->execute()) echo json_encode(['status' => 'success']);
else throw new Exception("Update failed");
?>
