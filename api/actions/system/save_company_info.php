<?php
// api/actions/system/save_company_info.php
$co      = strtoupper(trim($_POST['company'] ?? ''));
$allowed = ['PTA','PT4','PTE'];

if (!in_array($co, $allowed)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid company code']);
    exit;
}

$info = [
    'name_th'    => trim($_POST['name_th']    ?? ''),
    'name_en'    => trim($_POST['name_en']    ?? ''),
    'address_th' => trim($_POST['address_th'] ?? ''),
    'address_en' => trim($_POST['address_en'] ?? ''),
    'phone'      => trim($_POST['phone']      ?? ''),
    'fax'        => trim($_POST['fax']        ?? ''),
    'tax_id'     => trim($_POST['tax_id']     ?? ''),
];

$key   = 'company_info_' . $co;
$value = json_encode($info, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare(
    "INSERT INTO system_settings (setting_key, setting_value)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);
$stmt->bind_param("ss", $key, $value);

if ($stmt->execute()) {
    echo json_encode(['status'=>'success','company'=>$co]);
} else {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$conn->error]);
}
$stmt->close();
?>
