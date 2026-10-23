<?php
// api/actions/room/room_rules.php — ตั้งค่าห้องประชุมของบริษัท: กติกาการจอง + ของที่ขอเตรียม · สิทธิ์ room.manage ($ACTION_PERMS)
// แก้ได้เฉพาะผู้ดูแลระดับบริษัท (ไม่ได้ผูกรายห้อง) ของบริษัทที่ดูแล · admin = ทุกบริษัท
_initRoomTables($conn);
$co = (string)($_REQUEST['company'] ?? '');
if (!in_array($co, ACCESS_COS, true)) throw new Exception('เลือกบริษัท');
if (!roomIsCentral($conn) || !inScope($conn, $co)) throw new Exception("ไม่มีสิทธิ์ตั้งค่าห้องประชุมของ {$co}");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode((string)($_POST['rules'] ?? ''), true);
    if (!is_array($in)) throw new Exception('ข้อมูลไม่ถูกต้อง — ลองใหม่อีกครั้ง');
    $t = fn($v, $d) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$v) ? (string)$v : $d;
    $r = ['open_from' => $t($in['open_from'] ?? '', '07:00'), 'open_to' => $t($in['open_to'] ?? '', '20:00'),
        'max_days_ahead' => max(0, min(365, (int)($in['max_days_ahead'] ?? 90))), 'max_hours' => max(0, min(336, round((float)($in['max_hours'] ?? 0), 1))),
        'extras' => array_values(array_intersect(array_keys(ROOM_EXTRAS), array_map('strval', (array)($in['extras'] ?? [])))), 'custom' => []];
    if ($r['open_to'] <= $r['open_from']) throw new Exception('เวลาปิดต้องหลังเวลาเปิด');
    foreach (array_slice((array)($in['custom'] ?? []), 0, 20) as $i => $c) {
        $tt = mb_substr(trim(strip_tags((string)($c['t'] ?? ''))), 0, 60);
        if ($tt === '') continue;
        $key = preg_match('/^c[a-z0-9]{1,12}$/', (string)($c['key'] ?? '')) ? (string)$c['key'] : 'c' . substr(md5($tt . $i), 0, 8);
        $r['custom'][] = ['key' => $key, 't' => $tt, 'i' => mb_substr(trim((string)($c['i'] ?? '•')), 0, 4) ?: '•'];
    }
    $json = json_encode($r, JSON_UNESCAPED_UNICODE); $by = roomActor();
    $st = $conn->prepare("INSERT INTO rm_company_rules (company, rules, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE rules = VALUES(rules), updated_by = VALUES(updated_by)");
    $st->bind_param('sss', $co, $json, $by); $st->execute();
    echo json_encode(['status' => 'success']);
    return;
}
$r = roomRules($conn, $co);
if ($r['extras'] === null) $r['extras'] = array_keys(ROOM_EXTRAS);
$std = [];
foreach (ROOM_EXTRAS as $k => $v) $std[] = ['key' => $k, 't' => $v['t'], 'i' => $v['i']];
echo json_encode(['status' => 'success', 'company' => $co, 'rules' => $r, 'standard_extras' => $std, 'companies' => array_values(array_filter(userCompanies($conn), fn($c) => true))], JSON_UNESCAPED_UNICODE);
