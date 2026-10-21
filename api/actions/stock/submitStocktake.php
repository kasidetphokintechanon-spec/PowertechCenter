<?php
// api/actions/stock/submitStocktake.php — physical count reconciliation
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$company    = trim($_POST['company']    ?? '');
$stock_type = trim($_POST['stock_type'] ?? '');
$counts_json = $_POST['counts'] ?? '[]';
$counts = json_decode($counts_json, true);

if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
if (!is_array($counts) || empty($counts)) throw new Exception("ไม่มีข้อมูลการนับ");

$created_by = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin';
$now = date('Y-m-d H:i:s');
$adjusted = 0;
$skipped  = 0;

$conn->begin_transaction();
try {
    foreach ($counts as $row) {
        $item_id  = intval($row['item_id']  ?? 0);
        $physical = intval($row['physical'] ?? -1);
        $customNote = trim($row['notes'] ?? '');
        if ($item_id <= 0 || $physical < 0) { $skipped++; continue; }

        if ($stock_type) {
            $typeChk = $conn->prepare("SELECT id FROM stock_items WHERE id = ? AND company = ? AND type = ? LIMIT 1");
            if (!$typeChk) throw new Exception("Prepare failed: " . $conn->error);
            $typeChk->bind_param("iss", $item_id, $company, $stock_type);
            $typeChk->execute();
            if (!$typeChk->get_result()->fetch_assoc()) { $skipped++; continue; }
            $typeChk->close();
        }

        // Get current balance
        $bal = $conn->prepare("
            SELECT COALESCE(SUM(CASE WHEN type='received' THEN quantity
                                     WHEN type='issued'   THEN -quantity
                                     WHEN type='adjusted' THEN quantity
                                     WHEN type='returned' THEN quantity
                                END),0) AS bal
            FROM stock_transactions WHERE item_id=? AND company=?");
        $bal->bind_param("is", $item_id, $company);
        $bal->execute();
        $current = intval($bal->get_result()->fetch_assoc()['bal'] ?? 0);
        $bal->close();

        $delta = $physical - $current;
        if ($delta === 0) { $skipped++; continue; }

        $stmt = $conn->prepare("INSERT INTO stock_transactions (item_id, company, type, quantity, notes, created_by, transaction_date) VALUES (?,?,'adjusted',?,?,?,?)");
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $note = $customNote !== ''
            ? $customNote
            : "นับสต็อก: ระบบ {$current} → จริง {$physical} (diff " . ($delta > 0 ? "+{$delta}" : $delta) . ")";
        $stmt->bind_param("isisss", $item_id, $company, $delta, $note, $created_by, $now);
        if (!$stmt->execute()) throw new Exception("Insert failed for item {$item_id}: " . $stmt->error);
        $stmt->close();
        $adjusted++;
    }
    $conn->commit();
    echo json_encode(['status' => 'success', 'adjusted' => $adjusted, 'skipped' => $skipped]);
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
?>
