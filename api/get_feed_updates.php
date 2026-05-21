<?php
/**
 * API Endpoint: Get Feed Updates
 * Retrieves latest metrics (likes, comments, shares) and new comments for specific posts.
 */
header('Content-Type: application/json');
require_once "../config/db.php";
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$postIdsStr = $_GET['ids'] ?? '';
$lastCommentId = (int) ($_GET['last_comment_id'] ?? 0);

if (empty($postIdsStr)) {
    echo json_encode(["status" => "success", "metrics" => [], "new_comments" => []]);
    exit;
}

// Security: Validate and sanitize post IDs
$postIds = array_filter(array_map('intval', explode(',', $postIdsStr)));
if (empty($postIds)) {
    echo json_encode(["status" => "success", "metrics" => [], "new_comments" => []]);
    exit;
}

$idsPlaceholder = implode(',', $postIds);

// 1. Fetch metrics for all requested posts
$metricsSql = "
    SELECT 
        posts.id,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS comment_count,
        (SELECT COUNT(*) FROM posts child WHERE child.shared_post_id = posts.id) AS share_count,
        CASE WHEN EXISTS (SELECT 1 FROM post_likes WHERE post_id = posts.id AND user_id = ?) THEN 1 ELSE 0 END AS user_liked
    FROM posts
    WHERE posts.id IN ($idsPlaceholder)
";

$stmt = mysqli_prepare($conn, $metricsSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$metrics = [];
while ($row = mysqli_fetch_assoc($res)) {
    $metrics[$row['id']] = $row;
}

// 2. Fetch new comments across any of these posts
$commentsSql = "
    SELECT c.*, u.username, u.profile_image 
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id IN ($idsPlaceholder) AND c.id > ?
    ORDER BY c.created_at ASC
";

$cStmt = mysqli_prepare($conn, $commentsSql);
mysqli_stmt_bind_param($cStmt, "i", $lastCommentId);
mysqli_stmt_execute($cStmt);
$cRes = mysqli_stmt_get_result($cStmt);

$newComments = [];
while ($cRow = mysqli_fetch_assoc($cRes)) {
    $newComments[] = $cRow;
}

echo json_encode([
    "status" => "success",
    "metrics" => $metrics,
    "new_comments" => $newComments
]);
