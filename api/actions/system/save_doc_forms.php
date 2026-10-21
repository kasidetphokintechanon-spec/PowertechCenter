<?php
// api/actions/system/save_doc_forms.php — บันทึกเลขที่เอกสาร (สิทธิ์ admin.access_admin เช็กที่ด่านกลางใน api.php)
require_once __DIR__ . '/../../../includes/doc_forms.php';

$in = json_decode($_POST['forms'] ?? '', true);
if (!is_array($in)) throw new Exception('ข้อมูลไม่ถูกต้อง');

$clean = [];
foreach (array_keys(DOC_FORM_KEYS) as $k) {
    $clean[$k] = [
        'no'  => mb_substr(trim((string)($in[$k]['no'] ?? '')), 0, 40),
        'rev' => mb_substr(trim((string)($in[$k]['rev'] ?? '')), 0, 10),
        'eff' => mb_substr(trim((string)($in[$k]['eff'] ?? '')), 0, 20),
    ];
}
$json = json_encode($clean, JSON_UNESCAPED_UNICODE);
$st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('doc_forms', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
$st->bind_param('s', $json);
$st->execute();
echo json_encode(['status' => 'success', 'forms' => getDocForms($conn)], JSON_UNESCAPED_UNICODE);
