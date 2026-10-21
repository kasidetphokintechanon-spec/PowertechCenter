<?php
// api/actions/kb/suggest_kb_articles.php
// แนะนำบทความ KB ที่อาจเกี่ยวข้องกับข้อความปัญหา/วิธีแก้ (ใช้ตอนช่างดูหรือปิด ticket)
// LIKE-based match ต่อคำ ไม่ใช้ FULLTEXT — เพียงพอสำหรับจำนวนบทความปัจจุบันที่ยังน้อย

$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
if ($q === '') {
    echo json_encode(['status' => 'success', 'articles' => []]);
    return;
}

$words = preg_split('/[\s,\.\/\\\\\-\(\)]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
$keywords = [];
foreach ($words as $w) {
    if (mb_strlen($w, 'UTF-8') >= 2 && count($keywords) < 6) $keywords[] = $w;
}

if (empty($keywords)) {
    echo json_encode(['status' => 'success', 'articles' => []]);
    return;
}

$conds = [];
$types = '';
$params = [];
foreach ($keywords as $kw) {
    $like = '%' . $kw . '%';
    $conds[] = "(title LIKE ? OR content LIKE ? OR tags LIKE ?)";
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT id, category_id, title, view_count FROM kb_articles
        WHERE is_published = 1 AND (" . implode(' OR ', $conds) . ")
        ORDER BY view_count DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
    return;
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$articles = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode(['status' => 'success', 'articles' => $articles]);
