<?php
/**
 * print_job.php — ใบแจ้งซ่อม / IT Work Order (ISO-Ready)
 * Usage: print_job.php?id={job_ticket_id}
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
    $c = mysqli_init(); if (!$c) continue;
    $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    @$c->real_connect($host, DB_USER, DB_PASS, DB_NAME);
    if (!$c->connect_error) { $conn = $c; break; }
}
if (!$conn) die('Database connection failed');
$conn->set_charset('utf8mb4');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Missing id parameter');

$stmt = $conn->prepare("SELECT * FROM job_tickets WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) { http_response_code(404); die('ไม่พบ Job Ticket เลขที่ ' . $id); }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function thDate($s) {
    if (!$s || $s === '0000-00-00') return '-';
    $t = strtotime($s);
    if (!$t) return h($s);
    $th_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $d = (int)date('d',$t); $m = (int)date('m',$t); $y = (int)date('Y',$t) + 543;
    return $d . ' ' . $th_months[$m] . ' ' . $y;
}

// Status
$statusText = 'รอดำเนินการ'; $statusClass = 'status-pending'; $statusIcon = 'fa-clock';
if ($job['is_done'])    { $statusText = 'เสร็จสิ้น';   $statusClass = 'status-approved'; $statusIcon = 'fa-check-circle'; }
elseif ($job['is_approve']) { $statusText = 'อนุมัติแล้ว'; $statusClass = 'status-approved'; $statusIcon = 'fa-check-circle'; }
elseif ($job['is_doing']) { $statusText = 'กำลังดำเนินการ'; $statusClass = 'status-pending'; $statusIcon = 'fa-spinner'; }
elseif ($job['is_send'])  { $statusText = 'ส่งงานแล้ว';  $statusClass = 'status-pending'; $statusIcon = 'fa-paper-plane'; }
elseif ($job['is_receive']) { $statusText = 'รับงานแล้ว'; $statusClass = 'status-pending'; $statusIcon = 'fa-inbox'; }

$co = strtoupper($job['company'] ?? 'PTA');
$coTheme = ['PTA' => 'theme-pta', 'PT4' => 'theme-pt4', 'PTE' => ''];
$coColors = ['PTA' => '#059669', 'PT4' => '#dc2626', 'PTE' => '#2563eb'];
$themeClass  = $coTheme[$co] ?? '';
$accentColor = $coColors[$co] ?? '#2563eb';

$coNames = [
    'PTA' => ['Powertech Engine Assembly Co., Ltd.', 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด'],
    'PT4' => ['Powertech 2004 Co., Ltd.', 'บริษัท พาวเวอร์เทค 2004 จำกัด'],
    'PTE' => ['Powertech Energy Solutions Co., Ltd.', 'บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด'],
];
$coNameEn = $coNames[$co][0] ?? $co;
$coNameTh = $coNames[$co][1] ?? $co;

// Company logo + address from DB
$coLogos = ['PTA' => 'uploads/logos/pta_logo.png', 'PT4' => 'uploads/logos/pt4_logo.png', 'PTE' => 'uploads/logos/pte_logo.png'];
$coLogoUrl = $coLogos[$co] ?? '';
$stmtCo = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
$coKey  = 'company_info_' . $co;
$stmtCo->bind_param('s', $coKey);
$stmtCo->execute();
$coInfoRow  = $stmtCo->get_result()->fetch_assoc();
$stmtCo->close();
$coInfoData = $coInfoRow ? (json_decode($coInfoRow['setting_value'], true) ?? []) : [];
$coAddressTh = trim($coInfoData['address_th'] ?? '');
$coAddressEn = trim($coInfoData['address_en'] ?? '');
$coAddressDisplay = $coAddressTh ?: $coAddressEn;
$coPhone    = trim($coInfoData['phone'] ?? '');
if (!empty($coInfoData['name_en'])) $coNameEn = $coInfoData['name_en'];
if (!empty($coInfoData['name_th'])) $coNameTh = $coInfoData['name_th'];

$docNo = 'WO-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
$printDate = thDate(date('Y-m-d'));

$baseUrl  = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/PowertechCenter';
$qrData   = urlencode($baseUrl . '/log.html?id=' . $id);
$qrUrl    = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={$qrData}";

// Check if deadline is overdue
$deadlineClass = '';
if (!empty($job['deadline']) && !$job['is_done']) {
    $deadlineClass = (strtotime($job['deadline']) < time()) ? 'style="color:#dc2626;font-weight:700"' : '';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบแจ้งซ่อม #<?= $id ?> — <?= h($docNo) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
<?php include __DIR__ . '/css/print-forms.css'; ?>

.theme-pta .doc-header   { border-color: #059669; }
.theme-pta .doc-title-bar { background: linear-gradient(135deg,#065f46,#059669); }
.theme-pta .doc-table thead tr { background: #059669; }
.theme-pta .doc-no { color: #059669; }
.theme-pta .company-info { border-color: #059669; }

.theme-pt4 .doc-header   { border-color: #dc2626; }
.theme-pt4 .doc-title-bar { background: linear-gradient(135deg,#991b1b,#dc2626); }
.theme-pt4 .doc-table thead tr { background: #dc2626; }
.theme-pt4 .doc-no { color: #dc2626; }
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

<div class="toolbar">
    <h1><i class="fas fa-tools" style="color:<?= h($accentColor) ?>;margin-right:6px"></i>
        ใบแจ้งซ่อม / IT Work Order — <?= h($docNo) ?></h1>
    <div class="copy-btns">
        <button class="copy-btn selected-orig" onclick="setCopy('orig',this)">ต้นฉบับ</button>
        <button class="copy-btn" onclick="setCopy('copy1',this)">สำเนา 1 — ช่าง</button>
        <button class="copy-btn" onclick="setCopy('copy2',this)">สำเนา 2 — ผู้แจ้ง</button>
    </div>
    <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> พิมพ์ / PDF</button>
    <a class="close-btn" href="log.html"><i class="fas fa-times"></i> ปิด</a>
</div>

<div class="a4" id="a4doc" style="position:relative">
    <div class="copy-watermark" id="copy-wm" style="display:none">COPY</div>

    <div class="doc-header">
        <div class="company-info">
            <?php if ($coLogoUrl): ?><img src="<?= h($coLogoUrl) ?>" alt="<?= h($co) ?>" style="height:48px;width:auto;object-fit:contain;" onerror="this.style.display='none'"><?php endif; ?>
            <div>
                <div class="company-name"><?= h($coNameEn) ?></div>
                <div class="company-sub"><?= h($coNameTh) ?></div>
                <?php if ($coAddressDisplay): ?>
                <div class="company-addr"><?= h($coAddressDisplay) ?></div>
                <?php else: ?>
                <div class="company-addr">IT Support &amp; Maintenance Department</div>
                <?php endif; ?>
                <?php if ($coPhone): ?><div class="company-addr"><?= h($coPhone) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-meta-box">
                <div class="doc-no-label">เลขที่ใบแจ้งซ่อม / WO No.</div>
                <div class="doc-no"><?= h($docNo) ?></div>
                <div class="doc-date">วันที่บันทึก: <?= thDate($job['last_update'] ?? '') ?></div>
                <div style="margin-top:4px">
                    <span class="status-badge <?= h($statusClass) ?>">
                        <i class="fas <?= h($statusIcon) ?>"></i> <?= h($statusText) ?>
                    </span>
                </div>
            </div>
            <div class="iso-ctrl">
                <table>
                    <tr><td class="lbl">Form No.</td><td class="val">FM-IT-001</td></tr>
                    <tr><td class="lbl">Revision</td><td class="val rev">Rev. 03</td></tr>
                    <tr><td class="lbl">Eff. Date</td><td class="val">01/01/2569</td></tr>
                </table>
            </div>
            <div class="qr-wrap">
                <img src="<?= h($qrUrl) ?>" alt="QR">
                <span>Scan เพื่อดูสถานะ</span>
            </div>
        </div>
    </div>

    <div class="doc-title-bar">
        <h2>ใบแจ้งซ่อม / IT Work Order</h2>
        <p>IT Support &amp; Maintenance Dept. | FM-IT-001 Rev.03</p>
    </div>

    <div class="info-grid">
        <div class="info-cell">
            <span class="info-label">ชื่องาน</span>
            <span class="info-value" style="font-weight:600"><?= h($job['job_name']) ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">บริษัท</span>
            <span class="info-value"><?= h($co) ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">แผนก</span>
            <span class="info-value"><?= h($job['department'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">อาคาร / สถานที่</span>
            <span class="info-value"><?= h($job['building'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">ผู้รับผิดชอบ</span>
            <span class="info-value"><?= h($job['responsible'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">ผู้แจ้ง</span>
            <span class="info-value"><?= h($job['created_by_name'] ?? '-') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">วันที่รับงาน</span>
            <span class="info-value"><?= thDate($job['last_update'] ?? '') ?></span>
        </div>
        <div class="info-cell">
            <span class="info-label">กำหนดเสร็จ</span>
            <span class="info-value" <?= $deadlineClass ?>><?= thDate($job['deadline'] ?? '') ?></span>
        </div>
    </div>

    <!-- Problem description area -->
    <div class="desc-box">
        <div class="lbl">รายละเอียดงาน / Job Description</div>
        <div style="min-height:48px;padding-top:4px"><?= h($job['job_name']) ?></div>
    </div>

    <!-- Action log table -->
    <table class="doc-table">
        <thead><tr>
            <th class="center" style="width:28px">#</th>
            <th>รายการดำเนินการ / Action Taken</th>
            <th class="center" style="width:70px">เวลาเริ่ม</th>
            <th class="center" style="width:70px">เวลาสิ้นสุด</th>
            <th class="center" style="width:52px">ชม.</th>
            <th>ผลการดำเนินการ</th>
        </tr></thead>
        <tbody>
            <?php for ($r = 1; $r <= 4; $r++): ?>
            <tr class="empty-row">
                <td class="center"><?= $r ?></td>
                <td>—</td>
                <td class="center">—</td>
                <td class="center">—</td>
                <td class="center">—</td>
                <td>—</td>
            </tr>
            <?php endfor; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="4" style="text-align:right;font-size:.7rem">รวมชั่วโมงทำงาน</td>
            <td class="center">—</td>
            <td>ชั่วโมง</td>
        </tr></tfoot>
    </table>

    <div class="notes-box">
        <div class="notes-label">สรุปผลการซ่อม / Resolution Summary</div>
        <div class="notes-line"></div>
        <div class="notes-line"></div>
    </div>

    <div class="sig-row">
        <?php foreach ([
            ['th' => 'ผู้แจ้งซ่อม',    'en' => 'Reporter',    'name' => $job['created_by_name'] ?? ''],
            ['th' => 'ช่างผู้ซ่อม',    'en' => 'Technician',  'name' => $job['responsible'] ?? ''],
            ['th' => 'หัวหน้าแผนก IT', 'en' => 'Head of IT Department', 'name' => ''],
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

    <div class="doc-footer">
        <div class="doc-footer-left">
            ออกโดยระบบ Powertech System Center · พิมพ์: <?= $printDate ?> ·
            <span id="copy-label">ต้นฉบับ (Original)</span>
        </div>
        <div class="doc-footer-right">FM-IT-001 Rev.03 · หน้า 1/1 · <?= h($docNo) ?></div>
    </div>
</div>

<script>
const copyLabels = { orig: 'ต้นฉบับ (Original)', copy1: 'สำเนา 1 — ช่าง', copy2: 'สำเนา 2 — ผู้แจ้ง' };
const wmTexts   = { orig: '', copy1: 'COPY 1', copy2: 'COPY 2' };
function setCopy(type, btn) {
    document.querySelectorAll('.copy-btn').forEach(b => b.classList.remove('selected','selected-orig'));
    btn.classList.add(type === 'orig' ? 'selected-orig' : 'selected');
    const wm = document.getElementById('copy-wm');
    document.getElementById('copy-label').textContent = copyLabels[type];
    if (type === 'orig') wm.style.display = 'none';
    else { wm.style.display = 'block'; wm.textContent = wmTexts[type]; }
}
</script>
</body>
</html>
