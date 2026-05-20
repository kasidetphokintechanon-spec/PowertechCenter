<?php
// api/actions/upload_signature.php
// รองรับทั้ง POST (FormData) และ JSON
$img = $_POST['signature_data'] ?? '';
$prefix = $_POST['prefix'] ?? 'sig';

if (empty($img)) {
    $json_data = json_decode(file_get_contents('php://input'), true);
    $img = $json_data['signature_data'] ?? '';
    $prefix = $json_data['prefix'] ?? 'sig';
}

if (preg_match('/^data:image\/(\w+);base64,/', $img, $type)) {
    $img = substr($img, strpos($img, ',') + 1);
    $type = strtolower($type[1]); // jpg, png, gif

    if (!in_array($type, [ 'jpg', 'jpeg', 'png', 'gif' ])) throw new Exception('Invalid image type');
    $img = base64_decode($img);
    if ($img === false) throw new Exception('base64_decode failed');
} else {
    throw new Exception('Did not match data URI with image data');
}

$targetDir = "uploads/signatures/";
if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

$filename = $prefix . '-' . uniqid() . '.' . $type;
$filepath = $targetDir . $filename;

if (file_put_contents($filepath, $img)) {
    echo json_encode(['status' => 'success', 'filePath' => $filepath]);
} else {
    throw new Exception('Failed to save signature file.');
}
?>