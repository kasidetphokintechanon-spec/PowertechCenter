<?php
// api/actions/get_kb_data.php
$categories_sql = "SELECT * FROM kb_categories ORDER BY sort_order ASC, name ASC";
$cat_res = $conn->query($categories_sql);
$categories = $cat_res ? $cat_res->fetch_all(MYSQLI_ASSOC) : [];

$articles_sql = "SELECT id, category_id, title, view_count, updated_at FROM kb_articles WHERE is_published = 1 ORDER BY view_count DESC, updated_at DESC";
$art_res = $conn->query($articles_sql);
$articles = $art_res ? $art_res->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode(['status' => 'success', 'categories' => $categories, 'articles' => $articles]);
?>