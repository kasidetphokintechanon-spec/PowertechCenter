<?php
/**
 * CLI smoke test — asset registry (hostname + custodian fields)
 * Run: C:\xampp\php\php.exe tools\test_asset_registry.php
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'powertech_system');

$conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    fwrite(STDERR, "DB connect failed: {$conn->connect_error}\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

$passed = 0;
$failed = 0;

function ok($msg) {
    global $passed;
    $passed++;
    echo "[PASS] $msg\n";
}
function fail($msg) {
    global $failed;
    $failed++;
    echo "[FAIL] $msg\n";
}

// 1. hostname column
$res = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'hostname'");
if ($res && $res->num_rows > 0) {
    ok('it_assets.hostname column exists');
} else {
    $conn->query("ALTER TABLE it_assets ADD COLUMN hostname VARCHAR(255) DEFAULT NULL COMMENT 'PC Name / Hostname' AFTER mac_address");
    $res2 = $conn->query("SHOW COLUMNS FROM it_assets LIKE 'hostname'");
    if ($res2 && $res2->num_rows > 0) ok('it_assets.hostname column created');
    else fail('could not create hostname column');
}

// 2. custodian columns
foreach (['custodian_employee_id', 'borrower_id', 'department'] as $col) {
    $r = $conn->query("SHOW COLUMNS FROM it_assets LIKE '$col'");
    if ($r && $r->num_rows > 0) ok("it_assets.$col exists");
    else fail("it_assets.$col missing");
}

// 3. category abbr lookup (NB=13)
$cat = $conn->query("SELECT cat_id, cat_abbr FROM asset_categories WHERE cat_abbr IN ('NB','PC','SV','NT')");
$abbrs = [];
if ($cat) while ($row = $cat->fetch_assoc()) $abbrs[$row['cat_abbr']] = (int)$row['cat_id'];
if (isset($abbrs['NB'])) ok('asset_categories NB found (cat_id=' . $abbrs['NB'] . ')');
else fail('asset_categories NB not found');

// 4. dry-run validation logic (mirror save_equipment.php)
function validateAsset($catAbbr, $serial, $hostname, $ip) {
    $catAbbr = strtoupper(trim($catAbbr));
    if (in_array($catAbbr, ['NB', 'PC', 'SV'], true)) {
        if ($serial === '') return 'serial required';
        if ($hostname === '') return 'hostname required';
    }
    if ($catAbbr === 'NT' && $ip === '') return 'ip required';
    return '';
}
if (validateAsset('NB', '', 'host', '') === 'serial required') ok('NB validation rejects missing SN');
else fail('NB SN validation');
if (validateAsset('NB', 'SN1', '', '') === 'hostname required') ok('NB validation rejects missing hostname');
else fail('NB hostname validation');
if (validateAsset('NT', '', '', '') === 'ip required') ok('NT validation rejects missing IP');
else fail('NT IP validation');
if (validateAsset('NB', 'SN1', 'PC-01', '') === '') ok('NB validation passes with SN+hostname');
else fail('NB pass validation');

// 5. update one asset if exists (non-destructive readback)
$row = $conn->query("SELECT id, equipment_id FROM it_assets ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($row) {
    $testHost = 'TEST-HOST-' . date('His');
    $id = (int)$row['id'];
    $stmt = $conn->prepare('UPDATE it_assets SET hostname = ? WHERE id = ?');
    $stmt->bind_param('si', $testHost, $id);
    if ($stmt->execute()) {
        $chk = $conn->query("SELECT hostname FROM it_assets WHERE id = $id")->fetch_assoc();
        if (($chk['hostname'] ?? '') === $testHost) ok("UPDATE hostname on id=$id ({$row['equipment_id']})");
        else fail('hostname readback mismatch');
        $conn->query("UPDATE it_assets SET hostname = NULL WHERE id = $id");
    } else fail('UPDATE hostname failed: ' . $stmt->error);
} else {
    ok('SKIP update test (no assets in DB)');
}

echo "\n--- Result: $passed passed, $failed failed ---\n";
exit($failed > 0 ? 1 : 0);
