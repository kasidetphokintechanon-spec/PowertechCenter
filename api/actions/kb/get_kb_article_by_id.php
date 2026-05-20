<?php
// api/actions/get_kb_article_by_id.php
$id = $_GET['id'] ?? 0;
if (!$id) throw new Exception("Article ID is required.");

// Increment view count
$conn->query("UPDATE kb_articles SET view_count = view_count + 1 WHERE id = " . intval($id));

$stmt = $conn->prepare("SELECT a.*, c.name as category_name, e.name as author_name 
                        FROM kb_articles a 
                        LEFT JOIN kb_categories c ON a.category_id = c.id
                        LEFT JOIN employees e ON a.author_id = e.id
                        WHERE a.id = ? AND a.is_published = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();

if ($article) {
    echo json_encode(['status' => 'success', 'article' => $article]);
} else {
    throw new Exception("Article not found or not published.");
}
?>