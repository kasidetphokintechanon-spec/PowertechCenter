<?php
/**
 * CLI test: setup → it_assets one-way sync
 * Usage: C:\xampp\php\php.exe tools/test_setup_asset_sync.php
 */
$conn = new mysqli('127.0.0.1', 'root', '', 'powertech_system');
if ($conn->connect_error) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/../includes/setup_asset_sync.php';

function logAssetHistory($conn, $assetDbId, $eventType, $details, $userName = 'System') {}

$pass = 0;
$fail = 0;

function ok($msg) { global $pass; $pass++; echo "[PASS] $msg\n"; }
function bad($msg) { global $fail; $fail++; echo "[FAIL] $msg\n"; }

// Pick a known asset
$res = $conn->query("SELECT id, equipment_id, serial_number, hostname FROM it_assets WHERE equipment_id != '' ORDER BY id DESC LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
if (!$row) { bad('no it_assets row to test'); exit(1); }

$code = $row['equipment_id'];
$testHost = 'SETUP-SYNC-' . date('His');
$testSn = 'SN-SYNC-' . date('His');

$result = syncSetupToItAsset($conn, [
    'asset_code' => $code,
    'serial_number' => $testSn,
    'pc_name' => $testHost,
    'ip_address' => '192.168.99.10/24',
    'employee_name' => 'Test Employee',
    'employee_id' => '9999999',
    'department' => 'IT',
    'company' => 'PTE',
    'job_type' => 'ส่งมอบเครื่องใหม่',
]);

if (($result['status'] ?? '') === 'success') ok("syncSetupToItAsset success for $code");
else bad('sync failed: ' . ($result['message'] ?? json_encode($result)));

$chk = $conn->prepare('SELECT serial_number, hostname, ip_address, status, borrower_id FROM it_assets WHERE id = ?');
$chk->bind_param('i', $row['id']);
$chk->execute();
$after = $chk->get_result()->fetch_assoc();

if (($after['serial_number'] ?? '') === $testSn) ok('serial_number updated');
else bad('serial_number mismatch');

if (($after['hostname'] ?? '') === $testHost) ok('hostname updated');
else bad('hostname mismatch');

if (($after['ip_address'] ?? '') === '192.168.99.10') ok('ip normalized (no /24)');
else bad('ip mismatch: ' . ($after['ip_address'] ?? ''));

if (($after['status'] ?? '') === 'ใช้งานปกติ') ok('status set to ใช้งานปกติ');
else bad('status mismatch');

// restore
$origSn = $row['serial_number'];
$origHost = $row['hostname'];
$upd = $conn->prepare('UPDATE it_assets SET serial_number=?, hostname=? WHERE id=?');
$upd->bind_param('ssi', $origSn, $origHost, $row['id']);
$upd->execute();
ok('restored original SN/hostname');

$nf = syncSetupToItAsset($conn, ['asset_code' => '___NO_SUCH_ASSET___']);
if (($nf['status'] ?? '') === 'not_found') ok('not_found for missing code');
else bad('expected not_found');

echo "\n--- Result: $pass passed, $fail failed ---\n";
exit($fail > 0 ? 1 : 0);
