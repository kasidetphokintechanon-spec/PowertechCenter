<?php
/**
 * print_stock.php — ใบเบิกพัสดุ / Stock Issue Voucher (ISO-Ready)
 * Usage: print_stock.php?id={stock_request_id}
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('<p style="font-family:sans-serif;color:#dc2626;padding:40px">กรุณาเข้าสู่ระบบก่อนพิมพ์เอกสาร <a href="login.html">Login</a></p>');
}

$hosts = array_values(array_unique(array_filter([DB_HOST, 'localhost', '127.0.0.1', '::1'])));
$conn = null;
foreach ($hosts as $host) {
    $c = mysqli_init();
    if (!$c) continue;
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}
if (!$conn) die('Database connection failed');
$conn->set_charset('utf8mb4');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Missing id parameter');

// Load request
$stmt = $conn->prepare("SELECT * FROM stock_requests WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$req) { http_response_code(404); die('ไม่พบเอกสารเลขที่ ' . $id); }

// Load items
$iStmt = $conn->prepare("
    SELECT sri.*, si.item_code, si.name, si.unit
    FROM stock_request_items sri
    JOIN stock_items si ON si.id = sri.item_id
    WHERE sri.request_id = ?
    ORDER BY si.item_code
");
$iStmt->bind_param('i', $id);
$iStmt->execute();
$items = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$iStmt->close();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function thDate($s, $withTime = false) {
    if (!$s) return '-';
    $t = strtotime($s);
    if (!$t) return h($s);
    $th_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $d = (int)date('d', $t);
    $m = (int)date('m', $t);
    $y = (int)date('Y', $t) + 543;
    $out = $d . ' ' . $th_months[$m] . ' ' . $y;
    if ($withTime) $out .= ' ' . date('H:i', $t);
    return $out;
}

$co = strtoupper($req['company'] ?? 'PTA');
$coColors = ['PTA' => '#059669', 'PT4' => '#dc2626', 'PTE' => '#2563eb'];
$coTheme  = ['PTA' => 'theme-pta', 'PT4' => 'theme-pt4', 'PTE' => ''];
$coNames  = [
    'PTA' => ['Powertech Engine Assembly Co., Ltd.', 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด'],
    'PT4' => ['Powertech 2004 Co., Ltd.', 'บริษัท พาวเวอร์เทค 2004 จำกัด'],
    'PTE' => ['Powertech Engineering Co., Ltd.', 'บริษัท พาวเวอร์เทค เอนจิเนียริ่ง จำกัด'],
];
$coLogos  = [
    'PTA' => 'uploads/logos/pta_logo.png',
    'PT4' => 'uploads/logos/pt4_logo.png',
    'PTE' => 'uploads/logos/pte_logo.png',
];
$coLogoUrl = $coLogos[$co] ?? $coLogos['PTA'];

// Load company info from system_settings
$ciKey  = 'company_info_' . $co;
$ciStmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
$ciStmt->bind_param("s", $ciKey);
$ciStmt->execute();
$ciRow  = $ciStmt->get_result()->fetch_assoc();
$ciStmt->close();
$companyInfo = ($ciRow && $ciRow['setting_value']) ? json_decode($ciRow['setting_value'], true) : [];
$coAddressTh = $companyInfo['address_th'] ?? '';
$coAddressEn = $companyInfo['address_en'] ?? '';
// Use Thai address if filled, otherwise fall back to English
$coAddressDisplay = $coAddressTh ?: $coAddressEn;
$coPhone     = $companyInfo['phone']      ?? '';
$coFax       = $companyInfo['fax']        ?? '';
$coTaxId     = $companyInfo['tax_id']     ?? '';
$accentColor = $coColors[$co] ?? '#2563eb';
$themeClass  = $coTheme[$co] ?? '';
$coNameEn    = $coNames[$co][0] ?? $co;
$coNameTh    = $coNames[$co][1] ?? $co;

$docNo = 'ISS-' . date('Y', strtotime($req['request_date'] ?? 'now')) . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
$statusLabel = match($req['status'] ?? '') {
    'approved' => ['อนุมัติแล้ว', 'status-approved', 'fa-check-circle'],
    'pending'  => ['รออนุมัติ', 'status-pending', 'fa-clock'],
    'denied'   => ['ไม่อนุมัติ', 'status-denied', 'fa-times-circle'],
    default    => [$req['status'] ?? '-', 'status-pending', 'fa-question-circle'],
};

$totalReq = array_sum(array_column($items, 'qty_requested'));
$totalApv = array_sum(array_map(fn($r) => $r['qty_approved'] !== null ? (int)$r['qty_approved'] : (int)$r['qty_requested'], $items));

$printDate = thDate(date('Y-m-d'));
$baseUrl   = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/PowertechCenter';
$qrData    = urlencode($baseUrl . '/stock.html?req=' . $id);
$qrUrl     = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={$qrData}";

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบเบิกพัสดุ #<?= $id ?> — <?= h($docNo) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
<?php include __DIR__ . '/css/print-forms.css'; ?>

/* Theme overrides */
.theme-pta .doc-header   { border-color: #059669; }
.theme-pta .doc-title-bar { background: linear-gradient(135deg,#065f46,#059669); }
.theme-pta .doc-table thead tr { background: #059669; }
.theme-pta .doc-no       { color: #059669; }
.theme-pta .doc-table tfoot tr { background: #f0fdf4; border-top: 2px solid #bbf7d0; }
.theme-pta .doc-table tfoot td { color: #059669; }
.theme-pta .company-info { border-color: #059669; }

.theme-pt4 .doc-header   { border-color: #dc2626; }
.theme-pt4 .doc-title-bar { background: linear-gradient(135deg,#991b1b,#dc2626); }
.theme-pt4 .doc-table thead tr { background: #dc2626; }
.theme-pt4 .doc-no       { color: #dc2626; }
.theme-pt4 .doc-table tfoot tr { background: #fef2f2; border-top: 2px solid #fecaca; }
.theme-pt4 .doc-table tfoot td { color: #dc2626; }
.theme-pt4 .company-info { border-color: #dc2626; }

/* Signature row — unified pattern */
.sig-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding-top: 8px; margin-top: auto; }
.sig-box  { border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.sig-header { padding: 5px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: baseline; gap: 6px; }
.sig-role-th { font-size: .7rem; font-weight: 700; color: #1e293b; }
.sig-role-en { font-size: .58rem; color: #64748b; }
.sig-body { padding: 8px 12px 10px; display: flex; flex-direction: column; align-items: center; gap: 2px; flex: 1; }
.sig-space { flex: 1; min-height: 28px; }
.sig-line-wrap { width: 100%; text-align: center; margin-bottom: 1px; }
.sig-line { border-bottom: 1px solid #475569; margin: 0 6px 3px; }
.sig-label { font-size: .5rem; color: #64748b; letter-spacing: .04em; }
.sig-name { font-size: .58rem; color: #334155; text-align: center; }
.sig-date { font-size: .55rem; color: #475569; text-align: center; margin-top: 2px; }

@media print {
    .sig-row { padding-top: 5px !important; gap: 8px !important; margin-top: auto; page-break-inside: avoid; }
    .sig-box  { min-height: 70px !important; }
    .sig-header { padding: 4px 10px !important; background: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sig-body { padding: 4px 10px 6px !important; gap: 2px !important; }
    .sig-space { min-height: 22px !important; }
    .sig-role-th { font-size: .65rem !important; }
    .sig-role-en { font-size: .55rem !important; }
    .sig-name { font-size: .55rem !important; }
    .sig-date { font-size: .52rem !important; }
}
</style>
</head>
<body class="<?= h($themeClass) ?>">

<!-- Toolbar (hidden on print) -->
<div class="toolbar">
    <h1><i class="fas fa-box-open" style="color:<?= h($accentColor) ?>;margin-right:6px"></i>
        ใบเบิกพัสดุ — <?= h($docNo) ?></h1>
    <div class="copy-btns">
        <button class="copy-btn selected-orig" onclick="setCopy('orig',this)">ต้นฉบับ</button>
        <button class="copy-btn" onclick="setCopy('copy1',this)">สำเนา 1 — คลัง</button>
        <button class="copy-btn" onclick="setCopy('copy2',this)">สำเนา 2 — บัญชี</button>
    </div>
    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> พิมพ์ / PDF</button>
    <a class="close-btn" href="stock.html"><i class="fas fa-times"></i> ปิด</a>
</div>

<!-- A4 Document -->
<div class="a4" id="a4doc" style="position:relative">
    <div class="copy-watermark" id="copy-wm" style="display:none">COPY</div>

    <!-- Header -->
    <div class="doc-header">
        <div class="company-info">
            <img src="<?= h($coLogoUrl) ?>" alt="<?= h($co) ?> logo"
                 style="height:52px;width:auto;object-fit:contain;flex-shrink:0;"
                 onerror="this.style.display='none'">
            <div>
                <div class="company-name"><?= h($coNameEn) ?></div>
                <div class="company-sub"><?= h($coNameTh) ?></div>
                <?php if ($coAddressDisplay): ?>
                <div class="company-addr"><?= h($coAddressDisplay) ?></div>
                <?php endif; ?>
                <?php if ($coPhone || $coFax): ?>
                <div class="company-addr">
                    <?= $coPhone ? 'โทร. ' . h($coPhone) : '' ?>
                    <?= ($coPhone && $coFax) ? ' | ' : '' ?>
                    <?= $coFax ? 'แฟกซ์ ' . h($coFax) : '' ?>
                    <?= $coTaxId ? ' | เลขภาษี ' . h($coTaxId) : '' ?>
                </div>
                <?php else: ?>
                <div class="company-addr">แผนกคลังพัสดุ — Stock Management Dept.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-meta-box">
                <div class="doc-no-label">เลขที่เอกสาร / Doc. No.</div>
                <div class="doc-no"><?= h($docNo) ?></div>
                <div class="doc-date">วันที่ขอ: <?= thDate($req['request_date'] ?? '') ?></div>
                <div style="margin-top:4px">
                    <span class="status-badge <?= h($statusLabel[1]) ?>">
                        <i class="fas <?= h($statusLabel[2]) ?>"></i> <?= h($statusLabel[0]) ?>
                    </span>
                </div>
            </div>
            <div class="iso-ctrl">
                <table>
                    <tr><td class="lbl">Form No.</td><td class="val">FM-STK-001</td></tr>
                    <tr><td class="lbl">Revision</td><td class="val rev">Rev. 02</td></tr>
                    <tr><td class="lbl">Eff. Date</td><td class="val">01/01/2569</td></tr>
                </table>
            </div>
            <div class="qr-wrap">
                <img src="<?= h($qrUrl) ?>" alt="QR">
                <span>Scan เพื่อดูสถานะ</span>
            </div>
        </div>
    </div>

    <!-- Title -->
    <div class="doc-title-bar">
        <h2>ใบเบิกพัสดุ / Material Issue Voucher</h2>
        <p>แผนกคลังพัสดุ — Stock Management Dept. | FM-STK-001 Rev.02</p>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-cell">
            <span class="info-label">แผนกผู้ขอ</span>
            <span class="info-value"><?= h($req['department'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">วันที่ขอเบิก</span>
            <span class="info-value"><?= thDate($req['request_date'] ?? '') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">ผู้ขอเบิก</span>
            <span class="info-value"><?= h($req['requester_name'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">ประเภทสินค้า</span>
            <span class="info-value"><?= h($req['stock_type'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">บริษัท</span>
            <span class="info-value"><?= h($co) ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">วันที่อนุมัติ</span>
            <span class="info-value"><?= thDate($req['approved_at'] ?? '') ?></span>
        </div>
        <?php if (!empty($req['notes'])): ?>
        <div class="info-cell" style="grid-column:span 2">
            <span class="info-label">หมายเหตุ</span>
            <span class="info-value"><?= h($req['notes']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Items Table -->
    <table class="doc-table">
        <thead><tr>
            <th class="center" style="width:32px">ลำดับ</th>
            <th style="width:90px">รหัสพัสดุ</th>
            <th>รายการ / Description</th>
            <th class="center" style="width:55px">จำนวนขอ</th>
            <th class="center" style="width:55px">จำนวนจ่าย</th>
            <th class="center" style="width:44px">หน่วย</th>
        </tr></thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr class="empty-row"><td colspan="6" class="center" style="padding:12px;color:#94a3b8">ไม่มีรายการ</td></tr>
        <?php else: ?>
            <?php foreach ($items as $i => $it):
                $approved = $it['qty_approved'] !== null ? (int)$it['qty_approved'] : (int)$it['qty_requested'];
            ?>
            <tr>
                <td class="center"><?= $i + 1 ?></td>
                <td style="font-family:monospace;font-size:.7rem"><?= h($it['item_code']) ?></td>
                <td><?= h($it['name']) ?></td>
                <td class="center"><?= (int)$it['qty_requested'] ?></td>
                <td class="center" style="font-weight:700"><?= $approved ?></td>
                <td class="center"><?= h($it['unit']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php for ($pad = count($items); $pad < 5; $pad++): ?>
            <tr class="empty-row">
                <td class="center"><?= $pad + 1 ?></td>
                <td>—</td><td>—</td><td class="center">—</td><td class="center">—</td><td class="center">—</td>
            </tr>
            <?php endfor; ?>
        <?php endif; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3" style="text-align:right;font-size:.7rem">รวมทั้งหมด</td>
            <td class="center"><?= $totalReq ?></td>
            <td class="center"><?= $totalApv ?></td>
            <td>รายการ</td>
        </tr></tfoot>
    </table>

    <!-- Signatures -->
    <div class="sig-row">
        <?php foreach ([
            ['th' => 'ผู้ขอเบิก',    'en' => 'Requested By', 'name' => $req['requester_name'] ?? ''],
            ['th' => 'ผู้จ่ายพัสดุ', 'en' => 'Issued By',    'name' => ''],
            ['th' => 'ผู้อนุมัติ',   'en' => 'Approved By',  'name' => $req['approved_by'] ?? ''],
        ] as $s): ?>
        <div class="sig-box">
            <div class="sig-header">
                <span class="sig-role-th"><?= h($s['th']) ?></span>
                <span class="sig-role-en"><?= h($s['en']) ?></span>
            </div>
            <div class="sig-body">
                <div class="sig-space"></div>
                <div class="sig-line-wrap">
                    <div class="sig-line"></div>
                    <div class="sig-label">ลงชื่อ / Signature</div>
                </div>
                <div class="sig-name">( <?= $s['name'] ? h($s['name']) : '..................................................' ?> )</div>
                <div class="sig-date">วันที่ &nbsp;.......... / .......... / ..............</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="doc-footer">
        <div class="doc-footer-left">
            ออกโดยระบบ Powertech System Center · พิมพ์: <?= $printDate ?> ·
            <span id="copy-label">ต้นฉบับ (Original)</span>
        </div>
        <div class="doc-footer-right">FM-STK-001 Rev.02 · หน้า 1/1 · <?= h($docNo) ?></div>
    </div>
</div><!-- /.a4 -->

<script>
const copyLabels = { orig: 'ต้นฉบับ (Original)', copy1: 'สำเนา 1 — คลังพัสดุ', copy2: 'สำเนา 2 — บัญชี' };
const wmTexts   = { orig: '', copy1: 'COPY 1', copy2: 'COPY 2' };
function setCopy(type, btn) {
    document.querySelectorAll('.copy-btn').forEach(b => b.classList.remove('selected','selected-orig'));
    btn.classList.add(type === 'orig' ? 'selected-orig' : 'selected');
    const wm = document.getElementById('copy-wm');
    document.getElementById('copy-label').textContent = copyLabels[type];
    if (type === 'orig') { wm.style.display = 'none'; }
    else { wm.style.display = 'block'; wm.textContent = wmTexts[type]; }
}
</script>
</body>
</html>
