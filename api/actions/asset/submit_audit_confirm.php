<?php
// api/actions/asset/submit_audit_confirm.php
// Public endpoint — token-based, no session required

$token   = trim($_POST['token']  ?? '');
$itemsRaw = $_POST['items'] ?? '';
if ($token === '' || $itemsRaw === '') {
    echo json_encode(['status'=>'error','message'=>'ข้อมูลไม่ครบ']); exit;
}

$items = json_decode($itemsRaw, true);
if (!is_array($items) || count($items) === 0) {
    echo json_encode(['status'=>'error','message'=>'ไม่มีรายการที่ยืนยัน']); exit;
}

// Verify token
$st = $conn->prepare("SELECT t.id, t.status, r.status AS round_status
    FROM asset_audit_tokens t
    JOIN asset_audit_rounds r ON r.id = t.round_id
    WHERE t.token = ? LIMIT 1");
$st->bind_param('s', $token);
$st->execute();
$tok = $st->get_result()->fetch_assoc();

if (!$tok) { echo json_encode(['status'=>'error','message'=>'Token ไม่ถูกต้อง']); exit; }
if ($tok['round_status'] === 'closed') { echo json_encode(['status'=>'error','message'=>'รอบตรวจนับปิดแล้ว']); exit; }
if ($tok['status'] !== 'pending') { echo json_encode(['status'=>'error','message'=>'ยืนยันแล้ว ไม่สามารถแก้ไขได้']); exit; }

$tokenId = $tok['id'];
$allowed = ['confirmed','missing','transferred'];
$now     = date('Y-m-d H:i:s');

$upd = $conn->prepare("UPDATE asset_audit_items SET status=?, note=?, confirmed_at=? WHERE token_id=? AND asset_db_id=?");
foreach ($items as $item) {
    $status = in_array($item['status'] ?? '', $allowed) ? $item['status'] : 'confirmed';
    $note   = mb_substr(trim($item['note'] ?? ''), 0, 500);
    $assetId = (int)($item['asset_db_id'] ?? 0);
    if ($assetId <= 0) continue;
    $upd->bind_param('sssii', $status, $note, $now, $tokenId, $assetId);
    $upd->execute();
}

// Determine overall token status
$chk = $conn->prepare("SELECT COUNT(*) AS total, SUM(status='confirmed') AS ok FROM asset_audit_items WHERE token_id=?");
$chk->bind_param('i', $tokenId);
$chk->execute();
$counts = $chk->get_result()->fetch_assoc();
$overall = ($counts['ok'] >= $counts['total']) ? 'confirmed' : 'partial';

$updTok = $conn->prepare("UPDATE asset_audit_tokens SET status=?, confirmed_at=? WHERE id=?");
$updTok->bind_param('ssi', $overall, $now, $tokenId);
$updTok->execute();

echo json_encode(['status'=>'success']);
