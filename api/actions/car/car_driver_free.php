<?php
// api/actions/car/car_driver_free.php — คนขับว่างไหมในช่วงเวลาที่จะจอง (ให้คนจองเลือกคนขับที่ต้องการ) · ต้องล็อกอิน (ด่านกลาง)
// บอกแค่ติดงานช่วงไหน คันไหน — ไม่บอกว่าใครจอง ไปไหน
_initCarTables($conn);
[$s, $e] = carCheckTime((string)($_GET['start'] ?? ''), (string)($_GET['end'] ?? ''), true);
$except = (int)($_GET['except'] ?? 0);
$out = [];
foreach ($conn->query("SELECT id, name, nickname FROM cr_drivers WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC) as $d) {
    $x = carDriverClash($conn, (int)$d['id'], $s, $e, $except);
    $out[] = ['id' => (int)$d['id'], 'name' => $d['name'], 'nickname' => $d['nickname'], 'busy' => $x ? ['start_at' => $x['start_at'], 'end_at' => $x['end_at'], 'plate' => $x['plate']] : null];
}
echo json_encode(['status' => 'success', 'drivers' => $out], JSON_UNESCAPED_UNICODE);
