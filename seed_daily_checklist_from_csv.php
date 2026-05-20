<?php
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Access Denied (admin only)";
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbName = DB_NAME;

$conn = new mysqli($host, $user, $pass, $dbName);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$csvPath = __DIR__ . DIRECTORY_SEPARATOR . 'DB' . DIRECTORY_SEPARATOR . 'ds2.csv';
if (!file_exists($csvPath)) {
    die("CSV file not found: " . htmlspecialchars($csvPath));
}

echo "<h2>Seed Daily Checklist From CSV</h2>";
echo "<p>Using file: " . htmlspecialchars($csvPath) . "</p>";

$buildingCompanyMap = [
    'F4'  => 'PTA',
    'F16' => 'PTA',
    'F17' => 'PTA',
    'F20' => 'PTA',
    'F5'  => 'PT4',
    'F6'  => 'PT4',
    'F7'  => 'PT4',
    'F8'  => 'PT4',
    'PTE' => 'PTE',
    'PTE HALL' => 'PTE',
];

$handle = fopen($csvPath, 'r');
if (!$handle) {
    die("Cannot open CSV file.");
}

$header = fgetcsv($handle);
if (!$header) {
    fclose($handle);
    die("CSV file is empty or invalid.");
}

$lastCompany = '';
$lastBuilding = '';
$inserted = 0;
$skipped = 0;
$orderByBuilding = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 3) continue;
    $csvCompany = trim($row[0]);
    $csvBuilding = trim($row[1]);
    $description = trim($row[2]);

    if ($description === '') continue;

    if ($csvCompany !== '') {
        $lastCompany = strtoupper($csvCompany);
    }
    if ($csvBuilding !== '') {
        $lastBuilding = strtoupper($csvBuilding);
    }

    $company = $lastCompany;
    $building = $lastBuilding;

    if ($building === '' && preg_match('/F-?\s?(\d{1,2})/ui', $description, $m)) {
        $num = $m[1];
        $building = 'F' . $num;
        $lastBuilding = $building;
    }

    if ($company === '' && $building !== '' && isset($buildingCompanyMap[$building])) {
        $company = $buildingCompanyMap[$building];
        $lastCompany = $company;
    }

    if ($company === '' || $building === '') {
        $skipped++;
        continue;
    }

    $title = $description;
    $category = $building;

    if (!isset($orderByBuilding[$category])) {
        $orderByBuilding[$category] = 1;
    } else {
        $orderByBuilding[$category]++;
    }
    $sortOrder = $orderByBuilding[$category];

    $stmtCheck = $conn->prepare("SELECT id FROM daily_checklist_items WHERE company = ? AND category = ? AND title = ? LIMIT 1");
    if (!$stmtCheck) {
        echo "<p style='color:red;'>Prepare check failed: " . htmlspecialchars($conn->error) . "</p>";
        break;
    }
    $stmtCheck->bind_param("sss", $company, $category, $title);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) {
        $stmtCheck->close();
        $skipped++;
        continue;
    }
    $stmtCheck->close();

    $stmt = $conn->prepare("INSERT INTO daily_checklist_items (company, title, description, category, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
    if (!$stmt) {
        echo "<p style='color:red;'>Prepare insert failed: " . htmlspecialchars($conn->error) . "</p>";
        break;
    }
    $stmt->bind_param("ssssi", $company, $title, $description, $category, $sortOrder);
    if ($stmt->execute()) {
        $inserted++;
    } else {
        echo "<p style='color:red;'>Insert failed for " . htmlspecialchars($company . ' ' . $category . ' ' . $title) . ": " . htmlspecialchars($stmt->error) . "</p>";
    }
    $stmt->close();
}

fclose($handle);

echo "<p style='color:green;'>Inserted items: " . (int)$inserted . "</p>";
echo "<p>Skipped items: " . (int)$skipped . "</p>";
echo "<p><a href='daily_checklist.html'>Go to Daily Checklist</a></p>";

$conn->close();

