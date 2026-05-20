<?php
require __DIR__ . '/../config.php';
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    fwrite(STDERR, "DB_CONNECT_ERROR: " . $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

function printSection($title) {
    echo $title . PHP_EOL;
}

function printRows($result, $formatter) {
    if (!$result || $result->num_rows === 0) {
        echo "NONE" . PHP_EOL;
        return;
    }
    while ($row = $result->fetch_assoc()) {
        echo $formatter($row) . PHP_EOL;
    }
}

printSection("DUP_EQUIPMENT_ID");
$q1 = $conn->query("SELECT equipment_id, COUNT(*) c
                    FROM it_assets
                    WHERE equipment_id IS NOT NULL AND LENGTH(TRIM(equipment_id)) > 0
                    GROUP BY equipment_id
                    HAVING COUNT(*) > 1
                    ORDER BY c DESC, equipment_id
                    LIMIT 50");
printRows($q1, fn($r) => $r['equipment_id'] . "\t" . $r['c']);

printSection("DUP_SERIAL_NUMBER");
$q2 = $conn->query("SELECT serial_number, COUNT(*) c
                    FROM it_assets
                    WHERE serial_number IS NOT NULL AND LENGTH(TRIM(serial_number)) > 0
                    GROUP BY serial_number
                    HAVING COUNT(*) > 1
                    ORDER BY c DESC, serial_number
                    LIMIT 50");
printRows($q2, fn($r) => $r['serial_number'] . "\t" . $r['c']);

printSection("BAD_PARENT_LINKS");
$q3 = $conn->query("SELECT a.id, a.equipment_id, a.parent_id
                    FROM it_assets a
                    LEFT JOIN it_assets p ON p.id = a.parent_id
                    WHERE a.parent_id IS NOT NULL AND a.parent_id <> 0 AND p.id IS NULL
                    ORDER BY a.id DESC
                    LIMIT 50");
printRows($q3, fn($r) => $r['id'] . "\t" . ($r['equipment_id'] ?? '-') . "\tparent=" . $r['parent_id']);

printSection("SELF_PARENT_LINKS");
$q4 = $conn->query("SELECT id, equipment_id, parent_id
                    FROM it_assets
                    WHERE parent_id IS NOT NULL AND parent_id = id
                    ORDER BY id DESC
                    LIMIT 50");
printRows($q4, fn($r) => $r['id'] . "\t" . ($r['equipment_id'] ?? '-') . "\tparent=" . $r['parent_id']);

printSection("SUMMARY");
$tot = $conn->query("SELECT COUNT(*) c FROM it_assets");
$totalAssets = $tot ? intval(($tot->fetch_assoc()['c'] ?? 0)) : 0;
echo "TOTAL_ASSETS\t" . $totalAssets . PHP_EOL;
