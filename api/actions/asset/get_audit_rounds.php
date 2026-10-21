<?php
// api/actions/asset/get_audit_rounds.php

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin','it','technician','manager'])) {
    echo json_encode(['status'=>'error','message'=>'ไม่มีสิทธิ์']); exit;
}

// Ensure tables exist
foreach ([
    "CREATE TABLE IF NOT EXISTS asset_audit_rounds (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, deadline DATE DEFAULT NULL, status ENUM('active','closed') DEFAULT 'active', created_by VARCHAR(100) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS asset_audit_tokens (id INT AUTO_INCREMENT PRIMARY KEY, round_id INT NOT NULL, employee_id VARCHAR(100) NOT NULL, employee_name VARCHAR(200) NOT NULL, token VARCHAR(64) NOT NULL, confirmed_at DATETIME DEFAULT NULL, status ENUM('pending','confirmed','partial') DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_token (token), INDEX idx_round (round_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
] as $ddl) $conn->query($ddl);

$rounds = $conn->query("SELECT * FROM asset_audit_rounds ORDER BY id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/';

foreach ($rounds as &$round) {
    $rid = $round['id'];
    $tRes = $conn->prepare("SELECT id, employee_name, token, status, confirmed_at FROM asset_audit_tokens WHERE round_id=? ORDER BY status ASC, employee_name ASC");
    $tRes->bind_param('i', $rid);
    $tRes->execute();
    $tokens = $tRes->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($tokens as &$t) {
        $t['link'] = $baseUrl . 'asset_confirm.php?token=' . $t['token'];
    }
    unset($t);
    $round['tokens']        = $tokens;
    $round['total']         = count($tokens);
    $round['confirmed']     = count(array_filter($tokens, fn($t) => $t['status'] !== 'pending'));
}
unset($round);

echo json_encode(['status'=>'success','rounds'=>$rounds,'base_url'=>$baseUrl]);
