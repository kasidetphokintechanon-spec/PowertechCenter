<?php
// api/actions/delete_asset_note.php

$history_id = $_POST['history_id'] ?? '';
$conn->query("DELETE FROM asset_history WHERE history_id = '$history_id'");
echo json_encode(['status' => 'success']);
?>