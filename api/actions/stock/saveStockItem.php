<?php
// api/actions/stock/saveStockItem.php — admin add/edit catalog item
require_once __DIR__ . '/initStockTables.php';
initStockTables($conn);

if (!isset($_SESSION['user_id'])) throw new Exception("Unauthorized");
$role = $_SESSION['role'] ?? 'guest';
$isApprover = ($role === 'admin' || !empty($_SESSION['can_manage_stock']));
if (!$isApprover) throw new Exception("Access Denied");

$id            = intval($_POST['id'] ?? 0);
$item_code     = trim($_POST['item_code']      ?? '');
$name          = trim($_POST['name']           ?? '');
$unit          = trim($_POST['unit']           ?? 'ชิ้น');
$price         = floatval($_POST['price_per_unit'] ?? 0);
$min_stock     = intval($_POST['min_stock']    ?? 0);
$max_stock     = intval($_POST['max_stock']    ?? 0);
$type          = trim($_POST['type']           ?? 'stationery');
$company       = trim($_POST['company']        ?? '');
$is_active     = intval($_POST['is_active']    ?? 1);
$category      = trim($_POST['category']       ?? 'other');
$description   = trim($_POST['description']    ?? '');
$image_url     = trim($_POST['existing_image_url'] ?? '');
$has_expiry    = intval($_POST['has_expiry']   ?? 0);
$pack_unit     = trim($_POST['pack_unit']     ?? '');
$pack_size     = max(1, intval($_POST['pack_size'] ?? 1));

$allowed_cats = ['office','it','safety','cleaning','laundry','other'];
if (!in_array($category, $allowed_cats)) $category = 'other';

if (!$item_code || !$name) throw new Exception("item_code และ name จำเป็นต้องระบุ");
if (!$type) throw new Exception("ประเภทไม่ถูกต้อง");
if (!in_array($company, ['PTA','PT4','PTE'])) throw new Exception("บริษัทไม่ถูกต้อง");
if (!inScope($conn, $company)) throw new Exception("ไม่มีสิทธิ์ดูแลสต็อกของ {$company}");
// แก้สินค้าเดิม: ห้ามย้ายบริษัท (ยอด/ประวัติผูกกับบริษัทเดิม) และต้องเป็นบริษัทที่ดูแล
if ($id > 0) {
    $own = $conn->prepare("SELECT company FROM stock_items WHERE id = ?"); $own->bind_param('i', $id); $own->execute();
    $was = $own->get_result()->fetch_row()[0] ?? null;
    if ($was === null) throw new Exception('ไม่พบสินค้า');
    if ($was !== $company) throw new Exception('ย้ายสินค้าข้ามบริษัทไม่ได้ — เพิ่มเป็นสินค้าใหม่ของบริษัทนั้นแทน');
}

// Handle image upload
if (!empty($_FILES['item_image']['name'])) {
    $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) throw new Exception("ไฟล์รูปไม่รองรับ (ใช้ jpg/png/webp)");
    if ($_FILES['item_image']['size'] > 2 * 1024 * 1024) throw new Exception("ไฟล์รูปต้องไม่เกิน 2MB");
    $uploadDir = __DIR__ . '/../../../uploads/stock/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $safeCode = preg_replace('/[^a-zA-Z0-9\-]/', '_', $item_code);
    $filename = $company . '_' . $safeCode . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $dest)) throw new Exception("อัปโหลดรูปไม่สำเร็จ");
    $image_url = 'uploads/stock/' . $filename;
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE stock_items SET item_code=?, name=?, unit=?, price_per_unit=?, min_stock=?, max_stock=?, type=?, company=?, is_active=?, category=?, description=?, image_url=?, has_expiry=?, pack_unit=?, pack_size=? WHERE id=?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("sssdiississsisii", $item_code, $name, $unit, $price, $min_stock, $max_stock, $type, $company, $is_active, $category, $description, $image_url, $has_expiry, $pack_unit, $pack_size, $id);
} else {
    $stmt = $conn->prepare("INSERT INTO stock_items (item_code, name, unit, price_per_unit, min_stock, max_stock, type, company, is_active, category, description, image_url, has_expiry, pack_unit, pack_size) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    $stmt->bind_param("sssdiississsisi", $item_code, $name, $unit, $price, $min_stock, $max_stock, $type, $company, $is_active, $category, $description, $image_url, $has_expiry, $pack_unit, $pack_size);
}

$stmt->execute();
$new_id = ($id > 0) ? $id : $conn->insert_id;
echo json_encode(['status' => 'success', 'id' => $new_id]);
?>
