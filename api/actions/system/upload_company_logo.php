<?php
// api/actions/system/upload_company_logo.php

if (($_SESSION['role'] ?? '') !== 'admin') {
    throw new Exception("Access denied.");
}

/**
 * Bump PWA manifest icon URLs so new installs / manifest refetch pick up the new file.
 * Note: existing desktop/PWA shortcuts may still show the old icon until re-pinned (OS cache).
 */
function ptc_sync_pwa_manifest_icons(string $rootDir, string $manifestFile, string $iconRelativePath, int $version): array {
    $manifestPath = $rootDir . DIRECTORY_SEPARATOR . $manifestFile;
    if (!is_file($manifestPath)) {
        return ['ok' => false, 'reason' => 'manifest missing'];
    }
    $raw = file_get_contents($manifestPath);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'reason' => 'manifest invalid json'];
    }
    $base = ltrim(str_replace('\\', '/', $iconRelativePath), '/');
    $iconWithVer = $base . '?v=' . $version;
    // PNG only — do not keep pwa-icon.svg (default "PT" placeholder wins on Windows PWA if listed first)
    $json['icons'] = [
        ['src' => $iconWithVer, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $iconWithVer, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => $iconWithVer, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
    ];
    $updated = 3;
    $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($manifestPath, $encoded . "\n") === false) {
        return ['ok' => false, 'reason' => 'manifest write failed'];
    }
    return ['ok' => true, 'icons_updated' => $updated, 'manifest' => $manifestFile, 'icon' => $iconWithVer];
}

function ptc_copy_app_favicon(string $rootDir, string $iconRelativePath): bool {
    $src = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $iconRelativePath);
    $dst = $rootDir . DIRECTORY_SEPARATOR . 'favicon.ico';
    if (!is_file($src)) return false;
    return @copy($src, $dst);
}

/**
 * favicon.ico และ <link rel="icon"> ทุกหน้าถูก request ด้วย URL คงที่ (ไม่มี ?v=)
 * ทำให้ browser แคชไว้ถาวรแม้ไฟล์บนดิสก์จะเปลี่ยนแล้ว — เขียนไฟล์เวอร์ชันเล็กๆ ไว้ให้
 * js/favicon-version.js ทุกหน้าไปอ่านแล้วแปะ ?v= ต่อท้าย href เอง ตอน runtime แทน
 */
function ptc_write_favicon_version(string $rootDir, int $version): bool {
    $path = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logos' . DIRECTORY_SEPARATOR . 'favicon-version.txt';
    return @file_put_contents($path, (string)$version) !== false;
}

$company = $_POST['company'] ?? '';
$valid_companies = ['PTA', 'PT4', 'PTE', 'DIR', 'APP'];

if (!in_array($company, $valid_companies)) {
    throw new Exception("Invalid company code.");
}

if (!isset($_FILES['logoFile']) || $_FILES['logoFile']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("No file uploaded or upload error.");
}

$tmpPath = $_FILES['logoFile']['tmp_name'];
$mimeType = mime_content_type($tmpPath);
$allowedMimes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif', 'image/svg+xml'];

if (!in_array($mimeType, $allowedMimes)) {
    throw new Exception("ไฟล์ต้องเป็น PNG, JPG, WEBP หรือ SVG เท่านั้น (ตรวจพบ: {$mimeType})");
}

$targetDir = "uploads/logos/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$fileName = match($company) {
    'DIR' => 'directory_app_icon.png',
    'APP' => 'powertech_app_icon.png',
    default => strtolower($company) . '_logo.png'
};
$targetFilePath = $targetDir . $fileName;

// SVG: save as-is (rename to .svg for SVG, but manifests expect .png — save as .svg separately)
// For non-SVG: convert to real PNG using GD
if ($mimeType === 'image/svg+xml') {
    // SVG: just save directly (SVG can't be re-encoded as PNG without rasterizer)
    if (file_exists($targetFilePath)) unlink($targetFilePath);
    if (!move_uploaded_file($tmpPath, $targetFilePath)) {
        throw new Exception("Failed to save SVG file.");
    }
    $rootDir = dirname(__DIR__, 3);
    $iconVersion = time();
    $pwaSync = null;
    if ($company === 'APP') {
        $pwaSync = ptc_sync_pwa_manifest_icons($rootDir, 'app-manifest.json', 'uploads/logos/powertech_app_icon.png', $iconVersion);
        ptc_copy_app_favicon($rootDir, 'uploads/logos/powertech_app_icon.png');
        ptc_write_favicon_version($rootDir, $iconVersion);
    } elseif ($company === 'DIR') {
        $pwaSync = ptc_sync_pwa_manifest_icons($rootDir, 'directory-manifest.json', 'uploads/logos/directory_app_icon.png', $iconVersion);
    }
    echo json_encode([
        'status' => 'success',
        'url' => $targetFilePath . '?t=' . $iconVersion,
        'format' => 'svg',
        'pwa_sync' => $pwaSync,
        'icon_version' => $iconVersion,
        'needs_repin' => in_array($company, ['APP', 'DIR'], true),
    ]);
    return;
}

// Use GD to read and re-export as proper PNG
if (!function_exists('imagecreatefromstring')) {
    // GD not available — fallback: save as-is
    if (file_exists($targetFilePath)) unlink($targetFilePath);
    if (!move_uploaded_file($tmpPath, $targetFilePath)) {
        throw new Exception("Failed to save file.");
    }
    echo json_encode(['status' => 'success', 'url' => $targetFilePath . '?t=' . time(), 'format' => 'raw']);
    return;
}

$rawData = file_get_contents($tmpPath);
$srcImage = @imagecreatefromstring($rawData);
if (!$srcImage) {
    throw new Exception("ไม่สามารถอ่านรูปภาพได้ กรุณาใช้ไฟล์ PNG, JPG หรือ WEBP");
}

$origW = imagesx($srcImage);
$origH = imagesy($srcImage);

$isSquare = ($origW === $origH);

// Cap all logos at 512px max dimension to avoid memory issues with large uploads
$maxDim = 512;
$isAppIcon = in_array($company, ['DIR', 'APP'], true);

if ($isAppIcon) {
    // app-manifest.json ประกาศ icon เป็น 512x512/192x192 (สี่เหลี่ยมจัตุรัสเป๊ะ) เสมอ —
    // Chrome เช็คว่าขนาดจริงของไฟล์ตรงกับที่ manifest ประกาศไหม ถ้าไม่ตรง (เช่น โลโก้ต้นฉบับ
    // ไม่ใช่สี่เหลี่ยมจัตุรัส) จะถือว่า icon ใช้ไม่ได้ แล้ว fallback ไปโชว์ตัวอักษรแทนทันที
    // ("P" ที่เห็นใน dialog Install app) จึงต้อง scale-to-fit ลงบน canvas สี่เหลี่ยมจัตุรัส
    // เสมอ (ไม่ยืดภาพ ไม่ตัดภาพ แค่เติมขอบโปร่งใสถ้าไม่ใช่สี่เหลี่ยมจัตุรัสอยู่แล้ว)
    $newW = $maxDim;
    $newH = $maxDim;
    $fitRatio = min($maxDim / $origW, $maxDim / $origH);
    $fitW = (int)round($origW * $fitRatio);
    $fitH = (int)round($origH * $fitRatio);
    $offsetX = (int)round(($maxDim - $fitW) / 2);
    $offsetY = (int)round(($maxDim - $fitH) / 2);
} elseif ($origW > $maxDim || $origH > $maxDim) {
    $ratio = $origW / $origH;
    if ($origW >= $origH) { $newW = $maxDim; $newH = (int)round($maxDim / $ratio); }
    else                  { $newH = $maxDim; $newW = (int)round($maxDim * $ratio); }
} else {
    $newW = $origW;
    $newH = $origH;
}

// Create output canvas (transparent background for PNG)
$outImage = imagecreatetruecolor($newW, $newH);
imagealphablending($outImage, false);
imagesavealpha($outImage, true);
$transparent = imagecolorallocatealpha($outImage, 0, 0, 0, 127);
imagefill($outImage, 0, 0, $transparent);
imagealphablending($outImage, true);

// Copy/resize
if ($isAppIcon) {
    imagecopyresampled($outImage, $srcImage, $offsetX, $offsetY, 0, 0, $fitW, $fitH, $origW, $origH);
} else {
    imagecopyresampled($outImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
}

// Save as PNG
if (file_exists($targetFilePath)) unlink($targetFilePath);
$saved = imagepng($outImage, $targetFilePath, 6); // compression 6 = good balance
imagedestroy($srcImage);
imagedestroy($outImage);

if (!$saved) {
    throw new Exception("Failed to write PNG file.");
}

$rootDir = dirname(__DIR__, 3);
$iconVersion = time();
$pwaSync = null;
if ($company === 'APP') {
    $pwaSync = ptc_sync_pwa_manifest_icons($rootDir, 'app-manifest.json', 'uploads/logos/powertech_app_icon.png', $iconVersion);
    ptc_copy_app_favicon($rootDir, 'uploads/logos/powertech_app_icon.png');
    ptc_write_favicon_version($rootDir, $iconVersion);
} elseif ($company === 'DIR') {
    $pwaSync = ptc_sync_pwa_manifest_icons($rootDir, 'directory-manifest.json', 'uploads/logos/directory_app_icon.png', $iconVersion);
}

echo json_encode([
    'status'   => 'success',
    'url'      => $targetFilePath . '?t=' . $iconVersion,
    'format'   => 'png',
    'original' => "{$origW}x{$origH}",
    'saved'    => "{$newW}x{$newH}",
    'square'   => $isSquare,
    'pwa_sync' => $pwaSync,
    'icon_version' => $iconVersion,
    'needs_repin' => in_array($company, ['APP', 'DIR'], true),
]);
?>
