<?php
// api/actions/kb/create_kb_draft_from_ticket.php
// สร้างบทความ KB แบบร่าง (unpublished) จากวิธีแก้ของ ticket ที่เพิ่งปิด แล้วผูก kb_article_id กลับเข้า ticket นั้น
// ไม่ใช้ path เดียวกับ add_item.php(key=kb_articles) เพราะจุดนั้นต้องมีสิทธิ์ kb.manage ซึ่งช่างทั่วไปมักไม่มี —
// endpoint นี้เปิดให้ผู้ใช้ที่ login แล้วทุกคนสร้างได้ เพราะสร้างได้แค่ "ร่าง" (is_published=0) admin ต้องมาตรวจ/เผยแพร่เองอยู่ดี

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    return;
}

$ticketId = trim((string)($_POST['ticket_id'] ?? ''));
$title    = trim((string)($_POST['title'] ?? ''));
$content  = trim((string)($_POST['content'] ?? ''));
$tags     = trim((string)($_POST['tags'] ?? ''));

if ($ticketId === '' || $title === '' || $content === '') {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    return;
}

$title = mb_substr($title, 0, 250, 'UTF-8');
$defaultCategoryId = 1; // "General IT" — admin ปรับหมวดหมู่เองตอนตรวจร่าง
$authorId = $_SESSION['user_id'];

// ผูกกลับเข้า ticket (kb_article_id คอลัมน์นี้ถูกสร้างไว้แล้วโดย ticket/add_item.php,update_item.php แต่กันไว้เผื่อ)
$colRes = $conn->query("SHOW COLUMNS FROM it_logs LIKE 'kb_article_id'");
if (!$colRes || intval($colRes->num_rows) === 0) {
    $conn->query("ALTER TABLE it_logs ADD COLUMN kb_article_id INT(11) DEFAULT NULL AFTER solution");
}

$conn->begin_transaction();
try {
    // ถ้า ticket นี้เคยผูกกับ KB record ไว้แล้ว (เช่น reopen แล้วปิดซ้ำ) และ record นั้นยังเป็นแค่ร่าง
    // (admin ยังไม่ได้เผยแพร่) ให้ "อัปเดตแทนที่จะสร้างใหม่" กันไม่ให้มี draft กำพร้าสะสม
    // แต่ถ้าถูกเผยแพร่ไปแล้ว ห้ามเขียนทับของที่ตรวจแล้ว — สร้างร่างใหม่แยกต่างหากแทน
    $existingArticleId = null;
    $stmtCur = $conn->prepare("SELECT kb_article_id FROM it_logs WHERE id = ? LIMIT 1");
    $stmtCur->bind_param("s", $ticketId);
    $stmtCur->execute();
    $curRow = $stmtCur->get_result()->fetch_assoc();
    $linkedId = $curRow['kb_article_id'] ?? null;

    if (!empty($linkedId)) {
        $stmtArt = $conn->prepare("SELECT id, is_published FROM kb_articles WHERE id = ? LIMIT 1");
        $stmtArt->bind_param("i", $linkedId);
        $stmtArt->execute();
        $artRow = $stmtArt->get_result()->fetch_assoc();
        if ($artRow && (int)$artRow['is_published'] === 0) {
            $existingArticleId = (int)$artRow['id'];
        }
    }

    if ($existingArticleId) {
        $stmt = $conn->prepare("UPDATE kb_articles SET title=?, content=?, tags=? WHERE id=?");
        if (!$stmt) throw new Exception("Prepare failed (Update KB Draft): " . $conn->error);
        $stmt->bind_param("sssi", $title, $content, $tags, $existingArticleId);
        if (!$stmt->execute()) throw new Exception("Execute failed (Update KB Draft): " . $stmt->error);
        $newArticleId = $existingArticleId;
    } else {
        $stmt = $conn->prepare("INSERT INTO kb_articles (category_id, title, content, tags, author_id, is_published) VALUES (?,?,?,?,?,0)");
        if (!$stmt) throw new Exception("Prepare failed (Insert KB Draft): " . $conn->error);
        $stmt->bind_param("issss", $defaultCategoryId, $title, $content, $tags, $authorId);
        if (!$stmt->execute()) throw new Exception("Execute failed (Insert KB Draft): " . $stmt->error);
        $newArticleId = (int)$conn->insert_id;

        $stmtLink = $conn->prepare("UPDATE it_logs SET kb_article_id = ? WHERE id = ?");
        if (!$stmtLink) throw new Exception("Prepare failed (Link Ticket): " . $conn->error);
        $stmtLink->bind_param("is", $newArticleId, $ticketId);
        if (!$stmtLink->execute()) throw new Exception("Execute failed (Link Ticket): " . $stmtLink->error);
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'article_id' => $newArticleId, 'reused_existing_draft' => (bool)$existingArticleId]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
