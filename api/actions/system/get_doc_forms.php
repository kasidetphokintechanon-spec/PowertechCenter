<?php
// api/actions/system/get_doc_forms.php — เลขที่เอกสารของใบพิมพ์ (ผู้ที่ล็อกอินทุกคนอ่านได้ เพราะใบพิมพ์ต้องใช้)
require_once __DIR__ . '/../../../includes/doc_forms.php';
echo json_encode(['status' => 'success', 'forms' => getDocForms($conn)], JSON_UNESCAPED_UNICODE);
