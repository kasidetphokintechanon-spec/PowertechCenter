<?php
// api/actions/stock/stock_receive.php — รับของเข้าหลายรายการในครั้งเดียว · สิทธิ์ stock.manage
// กรอกเป็นแพ็คได้ (in_pack) → แปลงเป็นหน่วยเบิก · ราคาที่กรอกเป็นราคาต่อหน่วยที่กรอก → ทุนต่อหน่วยเบิก
// ราคากลางสินค้า = ถัวเฉลี่ยถ่วงน้ำหนัก (ยอดเดิม × ราคาเดิม + ของเข้า × ราคาใหม่) ÷ ยอดรวม
_initStockFlow($conn);
$co = stockCompanyScope((string)($_POST['company'] ?? ''));
if (!$co) throw new Exception('เลือกบริษัทก่อน');
$rows = json_decode((string)($_POST['rows'] ?? '[]'), true);
if (!is_array($rows)) throw new Exception('ข้อมูลรายการไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$rows = array_values(array_filter($rows, fn($x) => (int)($x['item_id'] ?? 0) > 0 && (float)($x['qty'] ?? 0) > 0));
if (!$rows) throw new Exception('ยังไม่ได้ใส่รายการ');
$supplier = mb_substr(trim(strip_tags((string)($_POST['supplier'] ?? ''))), 0, 255);
$docNo = mb_substr(trim(strip_tags((string)($_POST['doc_no'] ?? ''))), 0, 60);
$notes = mb_substr(trim(strip_tags((string)($_POST['notes'] ?? ''))), 0, 300);

$ids = array_map(fn($x) => (int)$x['item_id'], $rows);
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $conn->prepare("SELECT id, name, unit, price_per_unit, pack_unit, pack_size, has_expiry FROM stock_items WHERE company = ? AND id IN ($ph)");
$p = array_merge([$co], $ids);
$st->bind_param('s' . str_repeat('i', count($ids)), ...$p); $st->execute();
$items = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), null, 'id');
foreach ($ids as $id) if (!isset($items[$id])) throw new Exception('มีรายการที่ไม่ใช่ของบริษัท ' . $co);

$ref = 'RC-' . date('ymd-His');
$by = stockActor();
$out = [];
$conn->begin_transaction();
try {
    $tx = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, notes, created_by, supplier, expiry_date) VALUES (?,?,'received',?,?,?,?,?,?)");
    $up = $conn->prepare("UPDATE stock_items SET price_per_unit = ? WHERE id = ?");
    foreach ($rows as $x) {
        $it = $items[(int)$x['item_id']];
        $pack = !empty($x['in_pack']) && (int)$it['pack_size'] > 1 ? (int)$it['pack_size'] : 1;
        $entered = (int)$x['qty'];
        $qty = $entered * $pack;
        $cost = round(max(0, (float)($x['price'] ?? 0)) / $pack, 4);
        $exp = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($x['expiry'] ?? '')) ? $x['expiry'] : null;
        $old = stockBalances($conn, [$it['id']])[(int)$it['id']];
        $note = trim("{$ref}" . ($docNo ? " · เอกสาร {$docNo}" : '') . ($pack > 1 ? " · {$entered} {$it['pack_unit']}" : '') . ($notes ? " · {$notes}" : ''));
        $tx->bind_param('isidssss', $it['id'], $co, $qty, $cost, $note, $by, $supplier, $exp); $tx->execute();
        if ($cost > 0) {
            // ราคาเดิมเป็น 0 = ยังไม่เคยตั้งราคา → ใช้ราคาใหม่ตรง ๆ ไม่ถัวกับศูนย์
            $base = (float)$it['price_per_unit'] > 0 ? max(0, $old) : 0;
            $avg = round(($base * (float)$it['price_per_unit'] + $qty * $cost) / max(1, $base + $qty), 2);
            $up->bind_param('di', $avg, $it['id']); $up->execute();
        }
        $out[] = ['name' => $it['name'], 'qty' => $qty, 'unit' => $it['unit'], 'entered' => $entered, 'pack_unit' => $pack > 1 ? $it['pack_unit'] : '', 'cost' => $cost, 'expiry' => $exp];
    }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
echo json_encode(['status' => 'success', 'ref' => $ref, 'company' => $co, 'supplier' => $supplier, 'doc_no' => $docNo, 'by' => $by, 'rows' => $out], JSON_UNESCAPED_UNICODE);
