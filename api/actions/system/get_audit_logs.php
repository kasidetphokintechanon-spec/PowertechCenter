<?php
// api/actions/get_audit_logs.php
// ใช้ asset_history เป็น Audit Log หลักของระบบ
$sql = "SELECT history_id as id, timestamp, user_name, event_type as action, asset_db_id as target_id, details, '' as ip_address FROM asset_history ORDER BY timestamp DESC LIMIT 500";
$result = $conn->query($sql);
$data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode($data);
?>