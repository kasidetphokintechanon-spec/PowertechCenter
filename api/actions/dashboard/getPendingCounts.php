<?php
// api/actions/getPendingCounts.php

// Query for pending loan requests
$loan_sql = "SELECT COUNT(*) as count FROM it_assets WHERE status = 'รออนุมัติ'";
$loan_result = $conn->query($loan_sql);
$loan_count = $loan_result ? (int)$loan_result->fetch_assoc()['count'] : 0;

// Query for pending repair tickets
$repair_sql = "SELECT COUNT(*) as count FROM it_logs WHERE status = 'Pending'";
$repair_result = $conn->query($repair_sql);
$repair_count = $repair_result ? (int)$repair_result->fetch_assoc()['count'] : 0;

// Query for pending bookings
$booking_sql = "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'";
$booking_result = $conn->query($booking_sql);
$booking_count = $booking_result ? (int)$booking_result->fetch_assoc()['count'] : 0;

echo json_encode([
    'status' => 'success',
    'loan' => $loan_count, 'repair' => $repair_count, 'booking' => $booking_count,
    'loan_pending_count' => $loan_count,
    'repair_pending_count' => $repair_count
]);
?>