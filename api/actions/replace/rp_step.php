<?php
// api/actions/replace/rp_step.php — เลื่อนขั้นใบซื้อทดแทน · สิทธิ์ IT · ทุกขั้นเก็บว่าใครกด เมื่อไหร่ + ลงประวัติเครื่องเก่า (พาสปอร์ต)
//   approve / reject : บันทึกผลตามใบที่หัวหน้าเซ็น (ชื่อผู้อนุมัติ + วันที่บังคับ)
//   order            : เลข PR / PO / ผู้ขาย / วันที่คาดว่าได้ของ
//   receive          : รหัสเครื่องใหม่ในทะเบียน (ต้องลงทะเบียนเครื่องใหม่ก่อน)
//   close            : จบ — เครื่องเก่าต้องตัดจำหน่ายแล้ว (ล้างข้อมูลตาม TISAX) หรือระบุว่าเก็บไว้ทำอะไร
_initReplaceTables($conn);
$r = rpGet($conn, (int)($_POST['id'] ?? 0));
if (!$r) throw new Exception('ไม่พบใบประเมิน');
if (!rpInScope($conn, $r)) throw new Exception('ใบนี้ไม่อยู่ในบริษัทที่คุณดูแล');
$step = (string)($_POST['step'] ?? '');
$f = fn($k, $n) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $n);
$date = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : null;
$by = rpActor(); $hist = null;
$need = ['approve' => ['pending'], 'reject' => ['pending'], 'order' => ['approved', 'ordered'], 'receive' => ['approved', 'ordered'], 'close' => ['received', 'approved']];
if (!isset($need[$step])) throw new Exception('ขั้นตอนไม่ถูกต้อง');
if (!in_array($r['status'], $need[$step], true)) throw new Exception('ใบนี้อยู่ขั้น "' . (RP_STATUS[$r['status']] ?? $r['status']) . '" ทำขั้นนี้ไม่ได้');
if ($step === 'approve' || $step === 'reject') {
    $who = $f('approver_name', 255); $on = $date('approved_on'); $note = $f('note', 500);
    if ($who === '' || !$on) throw new Exception('ใส่ชื่อผู้อนุมัติและวันที่ ตามใบประเมินที่เซ็น');
    if ($step === 'reject' && $note === '') throw new Exception('ใส่เหตุผลที่ไม่อนุมัติ เช่น ให้ซ่อมต่อ / ใช้ต่อไปก่อน');
    // เสนอตัดจำหน่ายไม่ซื้อทดแทน → อนุมัติแล้วข้ามไปขั้นปิดได้เลย (ไม่มีของต้องซื้อ)
    $ns = $step === 'reject' ? 'rejected' : 'approved';
    $u = $conn->prepare("UPDATE it_replacements SET status = ?, approver_name = ?, approved_on = ?, decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?");
    $u->bind_param('sssssi', $ns, $who, $on, $note, $by, $r['id']);
    $hist = $step === 'approve' ? "{$r['code']}: อนุมัติ" . ($r['recommendation'] === 'replace' ? 'ซื้อทดแทน' : 'ตัดจำหน่าย') . " โดย {$who} ({$on})" : "{$r['code']}: ไม่อนุมัติ โดย {$who} — {$note}";
} elseif ($step === 'order') {
    $pr = $f('pr_no', 50); $po = $f('po_no', 50); $vendor = $f('vendor', 255); $eta = $date('eta_date');
    if ($pr === '' && $po === '') throw new Exception('ใส่เลข PR หรือ PO อย่างน้อย 1 อย่าง');
    $u = $conn->prepare("UPDATE it_replacements SET status = 'ordered', pr_no = ?, po_no = ?, vendor = ?, eta_date = ?, ordered_by = ?, ordered_at = COALESCE(ordered_at, NOW()) WHERE id = ?");
    $u->bind_param('sssssi', $pr, $po, $vendor, $eta, $by, $r['id']);
} elseif ($step === 'receive') {
    $nc = $f('new_asset_code', 100);
    if ($nc === '') throw new Exception('ใส่รหัสเครื่องใหม่ (ลงทะเบียนเครื่องใหม่ในทะเบียนทรัพย์สินก่อน)');
    if (!rpFindAsset($conn, $nc)) throw new Exception("ไม่พบรหัส {$nc} ในทะเบียนทรัพย์สิน — ลงทะเบียนเครื่องใหม่ก่อน");
    $u = $conn->prepare("UPDATE it_replacements SET status = 'received', new_asset_code = ?, received_by = ?, received_at = NOW() WHERE id = ?");
    $u->bind_param('ssi', $nc, $by, $r['id']);
    $hist = "{$r['code']}: ได้เครื่องทดแทนแล้ว ({$nc})";
} else {
    if ($r['status'] === 'approved' && $r['recommendation'] === 'replace') throw new Exception('ยังไม่ได้เครื่องใหม่ — บันทึก "ได้เครื่องใหม่" ก่อน');
    $note = $f('note', 500);
    $retired = in_array((string)$r['asset_status'], ['ตัดจำหน่าย', 'เลิกใช้งาน'], true);
    // ห้ามปิดทั้งที่เครื่องเก่ายังมีข้อมูลค้าง/ไม่รู้ไปไหน — ต้องตัดจำหน่าย หรือบอกว่าเก็บไว้ทำอะไร
    if ($r['asset_db_id'] && !$retired && $note === '') throw new Exception('เครื่องเก่ายังไม่ได้ตัดจำหน่าย — ตัดจำหน่ายในพาสปอร์ตเครื่องก่อน หรือใส่หมายเหตุว่าเก็บไว้ทำอะไร (เช่น เก็บเป็นอะไหล่)');
    $u = $conn->prepare("UPDATE it_replacements SET status = 'closed', closed_by = ?, closed_at = NOW(), close_note = ? WHERE id = ?");
    $u->bind_param('ssi', $by, $note, $r['id']);
    $hist = "{$r['code']}: ปิดเรื่องซื้อทดแทน" . ($note ? " — {$note}" : '');
}
$u->execute();
if ($r['asset_db_id'] && $hist && function_exists('logAssetHistory')) logAssetHistory($conn, $r['asset_db_id'], 'REPLACE_' . strtoupper($step), $hist, $by);
echo json_encode(['status' => 'success']);
