<?php
// api/actions/stock/stock_count.php — นับสต็อก / ปรับยอด · บันทึกเฉพาะรายการที่ยอดจริงต่างจากระบบ · ต้องมีเหตุผล (ตรวจย้อนได้)
// สิทธิ์ stock.manage · ไม่แก้/ลบประวัติเดิม — เพิ่มรายการ "ปรับยอด" ใหม่แทน
_initStockFlow($conn);
$co = stockCompanyScope((string)($_POST['company'] ?? ''));
if (!$co) throw new Exception('เลือกบริษัทก่อน');
$reason = mb_substr(trim(strip_tags((string)($_POST['reason'] ?? ''))), 0, 300);
if ($reason === '') throw new Exception('กรุณาระบุเหตุผล เช่น นับสต็อกประจำเดือน / ของเสียหาย');
$rows = json_decode((string)($_POST['rows'] ?? '[]'), true);
if (!is_array($rows)) throw new Exception('ข้อมูลรายการไม่ถูกต้อง — ลองใหม่อีกครั้ง');
$want = [];
foreach ($rows as $x) { $id = (int)($x['item_id'] ?? 0); if ($id > 0 && isset($x['counted']) && $x['counted'] !== '' && (int)$x['counted'] >= 0) $want[$id] = (int)$x['counted']; }
if (!$want) throw new Exception('ยังไม่ได้กรอกยอดนับ');
$ids = array_keys($want);
$ph = implode(',', array_fill(0, count($ids), '?'));
$st = $conn->prepare("SELECT id, name, unit, price_per_unit FROM stock_items WHERE company = ? AND id IN ($ph)");
$p = array_merge([$co], $ids);
$st->bind_param('s' . str_repeat('i', count($ids)), ...$p); $st->execute();
$items = array_column($st->get_result()->fetch_all(MYSQLI_ASSOC), null, 'id');
foreach ($ids as $id) if (!isset($items[$id])) throw new Exception('มีรายการที่ไม่ใช่ของบริษัท ' . $co);
$bal = stockBalances($conn, $ids);
$res = stockReserved($conn, $ids);
$by = stockActor();
$out = [];
$conn->begin_transaction();
try {
    $tx = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, unit_cost, notes, created_by) VALUES (?,?,'adjusted',?,?,?,?)");
    foreach ($want as $id => $cnt) {
        $diff = $cnt - $bal[$id];
        $out[] = ['name' => $items[$id]['name'], 'unit' => $items[$id]['unit'], 'system' => $bal[$id], 'counted' => $cnt, 'diff' => $diff, 'short_reserved' => $cnt < $res[$id]];
        if ($diff === 0) continue;
        $cost = (float)$items[$id]['price_per_unit'];
        $note = "ปรับยอด {$bal[$id]} → {$cnt}: {$reason}";
        $tx->bind_param('isidss', $id, $co, $diff, $cost, $note, $by); $tx->execute();
    }
    $conn->commit();
} catch (Throwable $e) { $conn->rollback(); throw $e; }
$changed = count(array_filter($out, fn($x) => $x['diff'] !== 0));
echo json_encode(['status' => 'success', 'changed' => $changed, 'rows' => $out, 'by' => $by, 'company' => $co], JSON_UNESCAPED_UNICODE);
