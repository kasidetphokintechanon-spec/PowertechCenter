<?php
// api/actions/reset_password.php

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($token) || empty($new_password)) {
    throw new Exception("Token and new password are required.");
}

// Validate token
$stmt = $conn->prepare("SELECT user_id, expires_at FROM password_reset_tokens WHERE token = ? AND is_used = 0");
if (!$stmt) throw new Exception("Prepare failed (validate token): " . $conn->error);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (new DateTime() > new DateTime($row['expires_at'])) {
        throw new Exception("This password reset link has expired.");
    }

    // Token is valid, update password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
    $update_stmt->bind_param("ss", $hashed_password, $row['user_id']);
    $update_stmt->execute();

    // Invalidate the token
    $invalidate_stmt = $conn->prepare("UPDATE password_reset_tokens SET is_used = 1 WHERE token = ?");
    $invalidate_stmt->bind_param("s", $token);
    $invalidate_stmt->execute();

    echo json_encode(['status' => 'success']);
} else {
    throw new Exception("Invalid or already used password reset link.");
}
?>