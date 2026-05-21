<?php
/**
 * API Endpoint: Get New Posts
 * Retrieves posts created after a specific ID for real-time feed updates.
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
$lastId = (int) ($_GET['last_id'] ?? 0);

// Use the same comprehensive query as home.php but with a last_id filter
$sql = "
    SELECT
        posts.id,
        posts.user_id,
        posts.content,
        posts.image_path,
        posts.product_name,
        posts.category,
        posts.price,
        posts.quantity_available,
        posts.location_text,
        posts.contact_number,
        posts.created_at,
        posts.shared_post_id,
        users.username,
        users.profile_image,
        shared_users.username AS shared_username,
        shared_users.id AS shared_user_id,
        shared_users.profile_image AS shared_profile_image,
        shared_posts.content AS shared_content,
        shared_posts.image_path AS shared_image_path,
        shared_posts.product_name AS shared_product_name,
        shared_posts.category AS shared_category,
        shared_posts.price AS shared_price,
        shared_posts.quantity_available AS shared_quantity_available,
        shared_posts.location_text AS shared_location_text,
        shared_posts.contact_number AS shared_contact_number,
        shared_posts.created_at AS shared_created_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS comment_count,
        (SELECT COUNT(*) FROM posts child_posts WHERE child_posts.shared_post_id = posts.id) AS share_count,
        CASE WHEN EXISTS (
            SELECT 1 FROM post_likes WHERE post_id = posts.id AND user_id = ?
        ) THEN 1 ELSE 0 END AS user_liked
    FROM posts
    INNER JOIN users ON posts.user_id = users.id
    LEFT JOIN posts AS shared_posts ON posts.shared_post_id = shared_posts.id
    LEFT JOIN users AS shared_users ON shared_posts.user_id = shared_users.id
    WHERE posts.id > ?
    ORDER BY posts.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $userId, $lastId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$posts = [];
if ($result !== false) {
    while ($row = mysqli_fetch_assoc($result)) {
        // For each post, fetch the top 3 most recent comments (keeping it consistent with home.php)
        $postId = (int) $row['id'];
        $commentSql = "
            SELECT c.*, u.username, u.profile_image 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.post_id = ? 
            ORDER BY c.created_at DESC 
            LIMIT 3
        ";
        $cStmt = mysqli_prepare($conn, $commentSql);
        mysqli_stmt_bind_param($cStmt, "i", $postId);
        mysqli_stmt_execute($cStmt);
        $cResult = mysqli_stmt_get_result($cStmt);
        
        $row['recent_comments'] = [];
        while ($cRow = mysqli_fetch_assoc($cResult)) {
            $row['recent_comments'][] = $cRow;
        }
        
        $posts[] = $row;
    }
}

echo json_encode(["status" => "success", "data" => $posts]);
