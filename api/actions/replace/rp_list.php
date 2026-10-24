<?php
// api/actions/replace/rp_list.php — รายการใบประเมิน/ซื้อทดแทน ตามบริษัทที่ดูแล · view: open (ยังไม่จบ) | done (ปิด/ไม่อนุมัติ) | all · id = ใบเดียว · สิทธิ์ IT
_initReplaceTables($conn);
[$cw, $cp] = scopeSql('r.company', userCompanies($conn));
// ใบที่ไม่ระบุบริษัท ทุกคนที่มีสิทธิ์เห็น (จะได้ไม่มีใบตกหล่น)
$cw = $cw ? ' AND (r.company IS NULL OR r.company = \'\'' . preg_replace('/^\s*AND\s*/', ' OR ', $cw) . ')' : '';
$view = (string)($_GET['view'] ?? 'open'); $one = (int)($_GET['id'] ?? 0);
$cond = $one ? 'r.id = ?' : ($view === 'done' ? "r.status IN ('closed','rejected')" : ($view === 'all' ? '1 = 1' : "r.status NOT IN ('closed','rejected')"));
$p = $one ? array_merge([$one], $cp) : $cp; $t = ($one ? 'i' : '') . str_repeat('s', count($cp));
$st = $conn->prepare("SELECT r.*, a.status AS asset_status FROM it_replacements r LEFT JOIN it_assets a ON a.id = r.asset_db_id WHERE {$cond}{$cw}
    ORDER BY FIELD(r.status, 'pending', 'approved', 'received', 'ordered', 'rejected', 'closed'), r.created_at DESC LIMIT 300");
if ($t) $st->bind_param($t, ...$p);
$st->execute();
$rows = array_map('rpRow', $st->get_result()->fetch_all(MYSQLI_ASSOC));
$reasons = []; foreach (RP_REASONS as $k => $v) $reasons[] = ['key' => $k, 't' => $v];
echo json_encode(['status' => 'success', 'data' => $rows, 'reasons' => $reasons, 'statuses' => RP_STATUS], JSON_UNESCAPED_UNICODE);
