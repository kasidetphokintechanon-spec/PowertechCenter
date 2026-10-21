<?php
// api/actions/stock/getIssueVoucherPrint.php — Official issue voucher (print / Save as PDF)
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$requestId = (int)($_GET['request_id'] ?? 0);
if ($requestId <= 0) {
    http_response_code(400);
    echo 'Missing request_id';
    exit;
}

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: text/html; charset=utf-8');

$stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $requestId);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) {
    http_response_code(404);
    echo 'Request not found';
    exit;
}

if ($req['status'] !== 'approved') {
    http_response_code(400);
    echo 'Voucher available for approved requests only';
    exit;
}

$itemsStmt = $conn->prepare("
    SELECT sri.*, si.item_code, si.name, si.unit
    FROM stock_request_items sri
    JOIN stock_items si ON si.id = sri.item_id
    WHERE sri.request_id = ?
    ORDER BY si.item_code
");
$itemsStmt->bind_param('i', $requestId);
$itemsStmt->execute();
$itemsRes = $itemsStmt->get_result();
$items = [];
while ($row = $itemsRes->fetch_assoc()) {
    $items[] = $row;
}
$itemsStmt->close();

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtThDate($s) {
    if (!$s) return '-';
    $t = strtotime($s);
    if (!$t) return h($s);
    return date('d/m/Y H:i', $t);
}

$coColors = [
    'PTA' => '#16a34a',
    'PT4' => '#dc2626',
    'PTE' => '#2563eb',
];
$coColor = $coColors[$req['company']] ?? '#2563eb';
$logoCo = strtolower($req['company'] ?? 'pta');
$webBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$logoUrl = $webBase . "/uploads/logos/{$logoCo}_logo.png";

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเบิกสินค้า #<?= (int)$req['id'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        body { font-family: 'Kanit', sans-serif; color: #0f172a; margin: 0; padding: 24px; }
        .toolbar { margin-bottom: 16px; display: flex; gap: 8px; }
        .toolbar button { font-family: inherit; padding: 8px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-weight: 600; }
        .toolbar .primary { background: <?= h($coColor) ?>; color: #fff; border-color: <?= h($coColor) ?>; }
        .head { display: flex; justify-content: space-between; gap: 16px; border-bottom: 3px solid <?= h($coColor) ?>; padding-bottom: 12px; margin-bottom: 16px; }
        .head img { height: 52px; object-fit: contain; }
        h1 { margin: 0; font-size: 20px; }
        .sub { color: #64748b; font-size: 13px; margin-top: 4px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 24px; font-size: 13px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 24px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; }
        th { background: #f1f5f9; text-align: left; }
        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 32px; font-size: 12px; }
        .sign-box { border-top: 1px solid #94a3b8; padding-top: 8px; min-height: 64px; }
        .foot { margin-top: 24px; font-size: 11px; color: #64748b; text-align: center; }
        @media print { .toolbar { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">พิมพ์ / บันทึก PDF</button>
        <button type="button" onclick="window.close()">ปิด</button>
    </div>
    <div class="head">
        <div>
            <h1>ใบเบิกสินค้า (Stock Issue Voucher)</h1>
            <div class="sub">PowertechCenter · เลขที่ #<?= (int)$req['id'] ?></div>
        </div>
        <img src="<?= h($logoUrl) ?>" alt="logo" onerror="this.style.display='none'">
    </div>
    <div class="meta">
        <div><strong>บริษัท:</strong> <?= h($req['company']) ?></div>
        <div><strong>ประเภท:</strong> <?= h($req['stock_type']) ?></div>
        <div><strong>วันที่อนุมัติ:</strong> <?= fmtThDate($req['approved_at'] ?: $req['request_date']) ?></div>
        <div><strong>แผนก:</strong> <?= h($req['department'] ?: '-') ?></div>
        <div><strong>ผู้ขอ:</strong> <?= h($req['requester_name'] ?: '-') ?></div>
        <div><strong>ผู้อนุมัติ:</strong> <?= h($req['approved_by'] ?: '-') ?></div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width:110px;">รหัส</th>
                <th>รายการ</th>
                <th style="width:60px;text-align:center;">หน่วย</th>
                <th style="width:70px;text-align:center;">ขอ</th>
                <th style="width:70px;text-align:center;">อนุมัติ</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
            <?php $approved = $it['qty_approved'] !== null ? (int)$it['qty_approved'] : (int)$it['qty_requested']; ?>
            <tr style="background:<?= $i % 2 ? '#f8fafc' : '#fff' ?>;">
                <td style="font-family:monospace;"><?= h($it['item_code']) ?></td>
                <td><?= h($it['name']) ?></td>
                <td style="text-align:center;"><?= h($it['unit']) ?></td>
                <td style="text-align:center;"><?= (int)$it['qty_requested'] ?></td>
                <td style="text-align:center;font-weight:700;"><?= $approved ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!empty($req['notes'])): ?>
        <p style="font-size:12px;"><strong>หมายเหตุ:</strong> <?= h($req['notes']) ?></p>
    <?php endif; ?>
    <div class="sign">
        <div>
            <div class="sign-box">ลายมือชื่อผู้เบิก<br>(<?= h($req['requester_name'] ?: '................................') ?>)</div>
        </div>
        <div>
            <div class="sign-box">ลายมือชื่อผู้อนุมัติ<br>(<?= h($req['approved_by'] ?: '................................') ?>)</div>
        </div>
    </div>
    <div class="foot">พิมพ์จาก PowertechCenter Stock · <?= date('d/m/Y H:i') ?></div>
</body>
</html>
