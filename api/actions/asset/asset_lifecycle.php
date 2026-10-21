<?php
// api/actions/asset/asset_lifecycle.php — "พาสปอร์ตเครื่อง": ประวัติรวม + มอบ / คืน / ตัดจำหน่าย
// สิทธิ์ asset.manage ถูกเช็กที่ด่านกลางใน api.php แล้ว
//   GET  op=timeline&id=<db_id>
//   POST op=assign  id, employee_id, note
//   POST op=return  id, reason, condition, wiped, note
//   POST op=retire  id, method, wiped, note
// TISAX: ทุกการเปลี่ยนผู้ถือครองต้องมีร่องรอย (ใคร/เมื่อไร/สภาพ/ล้างข้อมูลหรือยัง) และห้ามลบประวัติ

$op = $_REQUEST['op'] ?? 'timeline';

// เครื่องที่พนักงานคนหนึ่งยังถือครอง — ใช้ในหน้า Offboard ให้ IT เก็บคืนให้ครบก่อนปิดงาน
if ($op === 'held') {
    $empId = trim((string)($_GET['employee_id'] ?? ''));
    if ($empId === '') throw new Exception('ไม่พบรหัสพนักงาน');
    $q = $conn->prepare("SELECT a.id, a.equipment_id, a.name, a.brand, a.model, a.serial_number, a.status, c.cat_name
                         FROM it_assets a LEFT JOIN asset_categories c ON a.asset_category = c.cat_id
                         WHERE (a.custodian_employee_id = ? OR a.borrower_id = ?) AND COALESCE(a.status,'') NOT IN ('ตัดจำหน่าย')
                         ORDER BY a.equipment_id");
    $q->bind_param('ss', $empId, $empId);
    $q->execute();
    echo json_encode(['status' => 'success', 'assets' => $q->get_result()->fetch_all(MYSQLI_ASSOC)], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) throw new Exception('ไม่พบรหัสพัสดุ');

$st = $conn->prepare("SELECT id, equipment_id, name, status, borrower, borrower_id, custodian_employee_id, custodian_type,
                             department, company, purchase_date, parent_id
                      FROM it_assets WHERE id = ? LIMIT 1");
$st->bind_param('i', $id);
$st->execute();
$asset = $st->get_result()->fetch_assoc();
if (!$asset) throw new Exception('ไม่พบพัสดุนี้ในระบบ');

$actor = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'IT';
$holderName = trim((string)$asset['borrower']);
$hasHolder = $holderName !== '' || trim((string)$asset['custodian_employee_id']) !== '';

if ($op === 'timeline') {
    $events = [];
    $add = function ($at, $type, $title, $detail = '', $by = '', $extra = []) use (&$events) {
        if (!$at) return;
        $events[] = array_merge(['at' => $at, 'type' => $type, 'title' => $title, 'detail' => $detail, 'by' => $by], $extra);
    };

    // 1) บันทึกในทะเบียน (ไม่เอา "เปิดดู" — เป็น noise)
    $h = $conn->prepare("SELECT history_id, event_type, details, user_name, timestamp FROM asset_history
                         WHERE asset_db_id = ? AND event_type <> 'ACCESS' ORDER BY timestamp DESC LIMIT 300");
    $h->bind_param('i', $id);
    $h->execute();
    foreach ($h->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $add($r['timestamp'], $r['event_type'], '', (string)$r['details'], (string)$r['user_name'], ['history_id' => (int)$r['history_id']]);
    }

    // 2) งานซ่อม (it_logs ผูกด้วยรหัสพัสดุ)
    $code = (string)$asset['equipment_id'];
    $repairs = 0;
    if ($code !== '') {
        $r = $conn->prepare("SELECT id, date, problem, solution, status, servicedBy, completed_date, repair_cost FROM it_logs WHERE asset_id = ? ORDER BY date DESC LIMIT 100");
        $r->bind_param('s', $code);
        $r->execute();
        foreach ($r->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
            $repairs++;
            $detail = trim((string)$t['problem']);
            if (trim((string)$t['solution']) !== '') $detail .= ' → ' . trim((string)$t['solution']);
            $add($t['date'], 'REPAIR', 'งานซ่อม ' . $t['id'], $detail, (string)$t['servicedBy'], ['status' => $t['status'], 'ref' => $t['id']]);
        }

        // 3) ยืม-คืน
        $l = $conn->prepare("SELECT borrower, purpose, lend_date, return_date, return_condition FROM it_loan_history WHERE equipment_id = ? ORDER BY lend_date DESC LIMIT 100");
        $l->bind_param('s', $code);
        $l->execute();
        foreach ($l->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
            $add($t['lend_date'], 'LOAN_OUT', 'ยืมออก — ' . $t['borrower'], (string)$t['purpose'], '', ['holder' => $t['borrower']]);
            if ($t['return_date']) $add($t['return_date'], 'LOAN_BACK', 'คืนจากการยืม — ' . $t['borrower'], $t['return_condition'] ? 'สภาพ: ' . $t['return_condition'] : '');
        }
    }

    // 4) ใบติดตั้ง / คนออก (setup_records)
    $s = $conn->prepare("SELECT id, employee_name, employee_id, setup_date, created_at, tech_name, job_type, status, offboard_date, offboard_by
                         FROM setup_records WHERE it_asset_id = ? OR (asset_code <> '' AND asset_code = ?) ORDER BY id DESC LIMIT 50");
    $s->bind_param('is', $id, $code);
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
        $add($t['setup_date'] ?: $t['created_at'], 'SETUP', 'ติดตั้งเครื่องให้ ' . $t['employee_name'], (string)$t['job_type'], (string)$t['tech_name'], ['holder' => $t['employee_name'], 'ref' => (int)$t['id']]);
        if ($t['status'] === 'offboarded' && $t['offboard_date']) {
            $add($t['offboard_date'], 'OFFBOARD', $t['employee_name'] . ' พ้นสภาพพนักงาน (ปิดสิทธิ์แล้ว)', '', (string)$t['offboard_by']);
        }
    }

    // เวลาเท่ากัน (บันทึกในวินาทีเดียวกัน) → ใช้ลำดับที่ลงบันทึกจริง
    usort($events, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']) ?: (($b['history_id'] ?? 0) <=> ($a['history_id'] ?? 0)));

    // สรุปบนหัวพาสปอร์ต
    $holders = [];
    foreach ($events as $e) {
        if (!empty($e['holder'])) $holders[mb_strtolower(trim($e['holder']))] = 1;
        if ($e['type'] === 'ASSIGN' && preg_match('/มอบให้\s+(.+?)(?:\s+\(|$)/u', $e['detail'], $m)) $holders[mb_strtolower(trim($m[1]))] = 1;
    }
    if ($holderName !== '' && ($asset['custodian_type'] ?? '') !== 'department') $holders[mb_strtolower($holderName)] = 1;
    $first = $asset['purchase_date'] ?: (end($events)['at'] ?? null);
    $holderSince = null;
    foreach ($events as $e) { // เหตุการณ์ล่าสุดที่ทำให้ได้ผู้ถือคนปัจจุบัน
        if (in_array($e['type'], ['ASSIGN', 'SETUP', 'SETUP_SYNC'], true)) { $holderSince = $e['at']; break; }
    }

    echo json_encode([
        'status' => 'success',
        'summary' => [
            'since' => $first,
            'holders' => count($holders),
            'repairs' => $repairs,
            'holder_since' => $hasHolder ? $holderSince : null,
        ],
        'events' => array_slice($events, 0, 400),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Method not allowed');
$note = trim((string)($_POST['note'] ?? ''));
$note = mb_substr($note, 0, 500);
$WIPED = ['yes' => 'ล้างข้อมูลแล้ว', 'no' => 'ยังไม่ได้ล้างข้อมูล', 'na' => 'ไม่มีข้อมูลต้องล้าง'];

if ($op === 'assign') {
    if (in_array($asset['status'], ['ตัดจำหน่าย', 'เลิกใช้งาน'], true)) throw new Exception('เครื่องนี้ตัดจำหน่ายแล้ว มอบให้ใครไม่ได้');
    if ($hasHolder) throw new Exception('เครื่องยังมีผู้ถือครองอยู่ — กด "คืนเครื่อง" ก่อน');
    $empId = trim((string)($_POST['employee_id'] ?? ''));
    $e = $conn->prepare("SELECT e.id, e.name, (SELECT a.department FROM employee_assignments a WHERE a.employee_id = e.id ORDER BY a.is_primary DESC LIMIT 1) AS dept
                         FROM employees e WHERE e.id = ? AND e.employment_status = 'active' LIMIT 1");
    $e->bind_param('s', $empId);
    $e->execute();
    $emp = $e->get_result()->fetch_assoc();
    if (!$emp) throw new Exception('ไม่พบพนักงานที่ยังทำงานอยู่ตามรหัสนี้');

    $u = $conn->prepare("UPDATE it_assets SET borrower = ?, borrower_id = ?, custodian_employee_id = ?, custodian_type = 'person', custodian_dept_id = NULL,
                                              department = ?, status = 'ใช้งานปกติ' WHERE id = ?");
    $dept = (string)($emp['dept'] ?? '');
    $u->bind_param('ssssi', $emp['name'], $emp['id'], $emp['id'], $dept, $id);
    $u->execute();
    logAssetHistory($conn, $id, 'ASSIGN', 'มอบให้ ' . $emp['name'] . ' (' . $emp['id'] . ($dept ? ' · ' . $dept : '') . ')' . ($note ? ' · ' . $note : ''));
    echo json_encode(['status' => 'success', 'message' => 'มอบเครื่องให้ ' . $emp['name'] . ' แล้ว', 'employee' => $emp], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'return') {
    if (!$hasHolder) throw new Exception('เครื่องนี้ไม่มีผู้ถือครองอยู่แล้ว');
    $REASONS = ['resign' => 'พนักงานลาออก', 'replace' => 'เปลี่ยนเครื่องใหม่', 'move' => 'ย้ายแผนก/บริษัท', 'repair' => 'ส่งซ่อม', 'other' => 'อื่นๆ'];
    $CONDS = ['good' => 'สภาพดี', 'fair' => 'ใช้ได้ มีรอย', 'broken' => 'ชำรุด'];
    $reason = $REASONS[$_POST['reason'] ?? ''] ?? null;
    $cond = $CONDS[$_POST['condition'] ?? ''] ?? null;
    $wiped = $WIPED[$_POST['wiped'] ?? ''] ?? null;
    if (!$reason || !$cond || !$wiped) throw new Exception('กรุณาเลือกเหตุผล สภาพเครื่อง และการล้างข้อมูล');
    // เครื่องชำรุดหรือเหตุผลคือส่งซ่อม → ไม่ควรขึ้นว่า "ว่าง พร้อมใช้"
    $newStatus = ($_POST['condition'] === 'broken') ? 'ชำรุด' : (($_POST['reason'] === 'repair') ? 'รอซ่อม' : 'ว่าง');

    $u = $conn->prepare("UPDATE it_assets SET borrower = '', borrower_id = NULL, custodian_employee_id = NULL, custodian_type = 'person',
                                              custodian_dept_id = NULL, department = NULL, status = ? WHERE id = ?");
    $u->bind_param('si', $newStatus, $id);
    $u->execute();
    $closeNote = 'คืนเครื่อง: ' . $reason . ' · ' . $cond . ' · ' . $wiped;
    $sr = $conn->prepare("UPDATE setup_records SET status='offboarded', offboard_date=CURDATE(), offboard_notes=?, offboard_by=?
                          WHERE status='active' AND (it_asset_id = ? OR (asset_code <> '' AND asset_code = ?))");
    $sr->bind_param('ssis', $closeNote, $actor, $id, $asset['equipment_id']);
    $sr->execute();
    logAssetHistory($conn, $id, 'RETURN', 'คืนจาก ' . ($holderName ?: $asset['custodian_employee_id']) . ' · ' . $reason . ' · ' . $cond . ' · ' . $wiped . ($note ? ' · ' . $note : ''));
    echo json_encode(['status' => 'success', 'message' => 'รับคืนเครื่องแล้ว — สถานะ: ' . $newStatus, 'new_status' => $newStatus], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'retire') {
    if ($hasHolder) throw new Exception('เครื่องยังมีผู้ถือครอง — กด "คืนเครื่อง" ก่อนตัดจำหน่าย');
    $METHODS = ['sell' => 'ขาย', 'destroy' => 'ทำลาย', 'donate' => 'บริจาค', 'vendor' => 'คืนผู้ให้เช่า/ผู้ขาย', 'lost' => 'สูญหาย'];
    $method = $METHODS[$_POST['method'] ?? ''] ?? null;
    $wiped = $WIPED[$_POST['wiped'] ?? ''] ?? null;
    if (!$method || !$wiped) throw new Exception('กรุณาเลือกวิธีตัดจำหน่ายและการล้างข้อมูล');
    // TISAX: ห้ามเครื่องออกจากบริษัทโดยข้อมูลยังอยู่ (ยกเว้นสูญหาย ซึ่งต้องแจ้งเหตุแยกต่างหาก)
    if ($_POST['wiped'] === 'no' && $_POST['method'] !== 'lost') throw new Exception('ต้องล้างข้อมูล/ทำลายดิสก์ก่อนนำเครื่องออกจากบริษัท');

    $u = $conn->prepare("UPDATE it_assets SET status = 'ตัดจำหน่าย', is_loanable = 0 WHERE id = ?");
    $u->bind_param('i', $id);
    $u->execute();
    logAssetHistory($conn, $id, 'RETIRE', 'ตัดจำหน่าย · ' . $method . ' · ' . $wiped . ($note ? ' · ' . $note : ''));
    echo json_encode(['status' => 'success', 'message' => 'ตัดจำหน่ายแล้ว (ประวัติยังเก็บไว้ครบ)'], JSON_UNESCAPED_UNICODE);
    exit;
}

throw new Exception('Unknown op');
