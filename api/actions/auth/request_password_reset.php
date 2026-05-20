<?php
// api/actions/request_password_reset.php

// TISAX Security: This action must always return a success response to prevent user enumeration.
// The actual email sending should only happen if the user exists.
$email = $_POST['email'] ?? '';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Still return success, but log the invalid attempt.
    error_log("Invalid password reset attempt for email: " . $email);
    echo json_encode(['status' => 'success']);
    exit;
}

// Find user by email from employee_assignments
$stmt = $conn->prepare("SELECT e.id, e.name FROM employees e JOIN employee_assignments ea ON e.id = ea.employee_id WHERE ea.email = ? AND e.employment_status = 'active' LIMIT 1");
if (!$stmt) throw new Exception("Prepare failed (find user by email): " . $conn->error);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    // User found, generate token
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", time() + 3600); // Token expires in 1 hour

    $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    if (!$insert_stmt) throw new Exception("Prepare failed (insert token): " . $conn->error);
    $insert_stmt->bind_param("sss", $user['id'], $token, $expires);
    $insert_stmt->execute();

    // --- Email Sending Logic ---
    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.html?token=" . $token;
    $subject = "Powertech System: Password Reset Request";
    $body = "Hello " . $user['name'] . ",\n\n" .
            "A password reset was requested for your account. Please click the link below to set a new password:\n" .
            $reset_link . "\n\n" .
            "If you did not request this, please ignore this email. This link is valid for 1 hour.\n\n" .
            "Thank you,\nPowertech System Center";
    $headers = "From: no-reply@powertech.co.th";

    mail($email, $subject, $body, $headers);
    error_log("Password reset for $email. Link: $reset_link");
}

// Always return success
echo json_encode(['status' => 'success']);
?>