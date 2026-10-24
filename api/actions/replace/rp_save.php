<?php
// api/actions/replace/rp_save.php — สร้าง / แก้ใบประเมิน (แก้ได้เฉพาะตอนรออนุมัติ — อนุมัติแล้วตัวเลขต้องตรงกับใบที่เซ็น) · สิทธิ์ IT
_initReplaceTables($conn);
$id = (int)($_POST['id'] ?? 0);
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$num = fn($k) => ($v = str_replace(',', '', (string)($_POST[$k] ?? ''))) === '' || !is_numeric($v) ? null : max(0, round((float)$v, 2));
$date = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : null;
$company = (string)($_POST['company'] ?? '');
if ($company !== '' && !in_array($company, ACCESS_COS, true)) throw new Exception('บริษัทไม่ถูกต้อง');
if ($company !== '' && !inScope($conn, $company)) throw new Exception("ไม่มีสิทธิ์ดูแลเครื่องของ {$company}");
$assetName = $f('asset_name', 255);
if ($assetName === '' && $f('asset_code', 100) === '') throw new Exception('ระบุเครื่องที่ประเมิน');
$findings = $f('findings', 3000);
if (mb_strlen($findings) < 5) throw new Exception('ใส่สิ่งที่ตรวจพบ / เหตุผลที่ซ่อมไม่คุ้ม');
$reasons = implode(',', array_values(array_intersect(array_keys(RP_REASONS), array_map('strval', (array)json_decode((string)($_POST['reasons'] ?? '[]'), true)))));
if ($reasons === '') throw new Exception('เลือกสาเหตุอย่างน้อย 1 ข้อ');
$rec = in_array($_POST['recommendation'] ?? '', ['replace', 'retire'], true) ? $_POST['recommendation'] : 'replace';
$vals = [$f('ticket_id', 50) ?: null, (int)($_POST['asset_db_id'] ?? 0) ?: null, $f('asset_code', 100), $assetName, $f('serial_number', 255), $company ?: null, $f('user_name', 255), $f('department', 150),
    $date('purchase_date'), $num('purchase_price'), (int)($_POST['useful_life'] ?? 0) ?: null, $f('problem', 3000), $findings, $reasons, $num('repair_cost'), $num('new_cost'), $rec, $f('new_spec', 3000), $date('need_by'), $f('loaner', 255)];
$cols = 'ticket_id, asset_db_id, asset_code, asset_name, serial_number, company, user_name, department, purchase_date, purchase_price, useful_life, problem, findings, reasons, repair_cost, new_cost, recommendation, new_spec, need_by, loaner';
$types = 'sisssssssdisssddssss';
if ($id) {
    $r = rpGet($conn, $id);
    if (!$r) throw new Exception('ไม่พบใบประเมิน');
    if (!rpInScope($conn, $r)) throw new Exception('ใบนี้ไม่อยู่ในบริษัทที่คุณดูแล');
    if ($r['status'] !== 'pending') throw new Exception('อนุมัติแล้ว แก้ใบประเมินไม่ได้ (ตัวเลขต้องตรงกับใบที่เซ็น)');
    $set = implode(', ', array_map(fn($c) => trim($c) . ' = ?', explode(',', $cols)));
    $u = $conn->prepare("UPDATE it_replacements SET {$set} WHERE id = ?");
    $vals[] = $id; $u->bind_param($types . 'i', ...$vals); $u->execute();
} else {
    $code = rpNextCode($conn); $by = rpActor();
    $u = $conn->prepare("INSERT INTO it_replacements (code, {$cols}, status, assessed_by, assessed_at) VALUES (?," . implode(',', array_fill(0, 20, '?')) . ", 'pending', ?, NOW())");
    $u->bind_param('s' . $types . 's', $code, ...array_merge($vals, [$by])); $u->execute();
    $id = $conn->insert_id;
    // ประวัติเครื่อง (พาสปอร์ต): เห็นว่าเครื่องนี้ถูกประเมินซ่อมไม่คุ้มเมื่อไหร่ โดยใคร
    if ($vals[1] && function_exists('logAssetHistory')) logAssetHistory($conn, $vals[1], 'REPLACE_ASSESS', "{$code}: ประเมินซ่อมไม่คุ้ม — " . ($rec === 'replace' ? 'เสนอซื้อทดแทน' : 'เสนอตัดจำหน่าย ไม่ซื้อทดแทน'), $by);
}
echo json_encode(['status' => 'success', 'id' => $id]);
