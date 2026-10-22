<?php
// เลขที่เอกสาร (ISO) ของใบพิมพ์ทุกใบ — ตั้งในหน้า settings_system.html
// ยังไม่ตั้งเลขใบไหน = ใบนั้นไม่แสดงกล่องเลขฟอร์ม (ไม่ใส่เลขปลอมไว้ก่อน)
const DOC_FORM_KEYS = [
    'setup'    => 'ใบติดตั้ง / ส่งมอบเครื่อง',
    'offboard' => 'ใบคืนเครื่อง / ถอนสิทธิ์',
    'repair'   => 'ใบแจ้ง / ใบงาน / ใบปิดงานซ่อม',
    'handover' => 'ใบมอบ / รับทรัพย์สิน',
    'loan'     => 'ใบยืม-คืนอุปกรณ์',
    'audit'    => 'ใบตรวจนับทรัพย์สิน',
    'password' => 'ใบแจ้งรหัสผ่าน',
    'server_check' => 'ใบตรวจห้อง Server ประจำเดือน',
    'it_report' => 'รายงานผลการให้บริการ IT',
    'directory' => 'สมุดรายชื่อพนักงาน',
];

function getDocForms(mysqli $conn): array {
    $out = [];
    foreach (DOC_FORM_KEYS as $k => $label) $out[$k] = ['label' => $label, 'no' => '', 'rev' => '', 'eff' => ''];
    $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'doc_forms' LIMIT 1");
    if ($st && $st->execute()) {
        $saved = json_decode($st->get_result()->fetch_assoc()['setting_value'] ?? '', true);
        if (is_array($saved)) {
            foreach ($out as $k => &$v) {
                foreach (['no', 'rev', 'eff'] as $f) $v[$f] = trim((string)($saved[$k][$f] ?? ''));
            }
            unset($v);
        }
    }
    return $out;
}
