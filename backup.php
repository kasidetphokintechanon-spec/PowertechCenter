<?php
session_start();
// ตรวจสอบสิทธิ์ Admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Access Denied. Admin only.");
}

// เพิ่มเวลา execution time และ memory สำหรับไฟล์ใหญ่
set_time_limit(600); 
ini_set('memory_limit', '512M');

$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'powertech_system';
$filename = "backup_powertech_" . date("Y-m-d_H-i") . ".zip";
$filepath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

// 1. สำรองฐานข้อมูล (Database Dump)
$mysqli = new mysqli($host, $user, $pass, $dbname);
$mysqli->set_charset("utf8mb4");

$sqlScript = "-- Powertech System Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n";
$tables = [];
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) { $tables[] = $row[0]; }

foreach ($tables as $table) {
    $res = $mysqli->query("SELECT * FROM $table");
    $row2 = $mysqli->query("SHOW CREATE TABLE $table")->fetch_row();
    $sqlScript .= "\n" . $row2[1] . ";\n";
    
    while ($row = $res->fetch_row()) {
        $sqlScript .= "INSERT INTO $table VALUES(";
        for ($j = 0; $j < $res->field_count; $j++) {
            if (is_null($row[$j])) $sqlScript .= "NULL";
            else $sqlScript .= '"' . $mysqli->real_escape_string($row[$j]) . '"';
            if ($j < ($res->field_count - 1)) $sqlScript .= ',';
        }
        $sqlScript .= ");\n";
    }
}
$sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";

// 2. สร้างไฟล์ ZIP (Files + SQL)
$zip = new ZipArchive();
if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
    die("Cannot create zip file");
}

// เพิ่มไฟล์ SQL ลงใน Zip
$zip->addFromString("database_backup.sql", $sqlScript);

// เพิ่มไฟล์ในโฟลเดอร์โปรเจกต์ (Recursive)
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $realPath = $file->getRealPath();
        $relativePath = substr($realPath, strlen(__DIR__) + 1);
        // ข้ามไฟล์ที่ไม่จำเป็น
        if (strpos($relativePath, '.git') !== false || $relativePath == $filename || strpos($relativePath, 'node_modules') !== false) continue;
        $zip->addFile($realPath, $relativePath);
    }
}
$zip->close();

// 3. ส่งไฟล์ให้ดาวน์โหลด
if (file_exists($filepath)) {
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    unlink($filepath); // ลบไฟล์ temp หลังโหลดเสร็จ
} else { echo "Error creating backup."; }
?>