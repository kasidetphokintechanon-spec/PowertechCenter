<?php
// ช่องเก็บหลักฐานการส่งมอบในใบติดตั้ง (ส่งเมลแจ้งผู้ใช้แล้ว / ผู้ใช้เซ็นรับแล้ว)
// เพิ่มเองครั้งแรกที่ถูกเรียก — ALTER ต้องอยู่นอก transaction เพราะ commit ให้เองโดยปริยาย
function ensureSetupHandoverColumns(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $cols = [
        'email_sent_at' => "DATETIME NULL",
        'email_sent_to' => "VARCHAR(200) NULL",
        'email_sent_by' => "VARCHAR(100) NULL",
        'signed_at'     => "DATE NULL",
        'signed_by'     => "VARCHAR(100) NULL",
    ];
    foreach ($cols as $name => $def) {
        $res = $conn->query("SHOW COLUMNS FROM setup_records LIKE '$name'");
        if ($res && $res->num_rows === 0) {
            $conn->query("ALTER TABLE setup_records ADD COLUMN $name $def");
        }
    }
    $done = true;
}
