<?php
/**
 * Toggle Like Page
 * Handles liking and unliking of posts. Supports AJAX for real-time 
 * heart button updates and sends notifications to the post author.
 */
session_start();
require_once "../config/db.php";

/**
 * Checks if the current request is an AJAX request.
 */
function is_ajax_request(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// Redirect or return error if not logged in
if (!isset($_SESSION["user_id"])) {
    if (is_ajax_request()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    header("Location: login.php");
    exit;
}

$postId = (int) ($_POST["post_id"] ?? 0);
$liked = false;

if ($postId > 0) {
    // Check if the user has already liked this post
    $sqlCheck = "SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?";
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmtCheck, "ii", $postId, $_SESSION["user_id"]);
    mysqli_stmt_execute($stmtCheck);
    $resCheck = mysqli_stmt_get_result($stmtCheck);

    if ($resCheck !== false && mysqli_fetch_assoc($resCheck)) {
        // If already liked, then "unlike" it (delete the record)
        $sqlDel = "DELETE FROM post_likes WHERE post_id = ? AND user_id = ?";
        $stmtDel = mysqli_prepare($conn, $sqlDel);
        mysqli_stmt_bind_param($stmtDel, "ii", $postId, $_SESSION["user_id"]);
        mysqli_stmt_execute($stmtDel);
        $liked = false;
    } else {
        // If not liked, then "like" it (insert a record)
        $sqlIns = "INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)";
        $stmtIns = mysqli_prepare($conn, $sqlIns);
        mysqli_stmt_bind_param($stmtIns, "ii", $postId, $_SESSION["user_id"]);
        mysqli_stmt_execute($stmtIns);
        $liked = true;

        // Fetch post author to send a notification
        $sqlAuthor = "SELECT user_id FROM posts WHERE id = ?";
        $stmtAuthor = mysqli_prepare($conn, $sqlAuthor);
        mysqli_stmt_bind_param($stmtAuthor, "i", $postId);
        mysqli_stmt_execute($stmtAuthor);
        $resAuthor = mysqli_stmt_get_result($stmtAuthor);
        
        if ($resAuthor !== false && $pRow = mysqli_fetch_assoc($resAuthor)) {
            $postAuthorId = $pRow['user_id'];
            // Notify author only if it's someone else's post
            if ($postAuthorId != $_SESSION["user_id"]) {
                $ntMsg = $_SESSION['username'] . " liked your post.";
                $sqlNotif = "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'like', ?, ?)";
                $stmtNotif = mysqli_prepare($conn, $sqlNotif);
                mysqli_stmt_bind_param($stmtNotif, "isi", $postAuthorId, $ntMsg, $postId);
                mysqli_stmt_execute($stmtNotif);
            }
        }

        // AUTO-MARK AS READ: Specifically for 'post' type notifications
        $clearSql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'post' AND related_id = ? AND is_read = 0";
        $clearStmt = mysqli_prepare($conn, $clearSql);
        mysqli_stmt_bind_param($clearStmt, "ii", $_SESSION["user_id"], $postId);
        mysqli_stmt_execute($clearStmt);
    }
}

if (is_ajax_request()) {
    $sqlCount = "SELECT COUNT(*) AS like_count FROM post_likes WHERE post_id = ?";
    $stmtCount = mysqli_prepare($conn, $sqlCount);
    mysqli_stmt_bind_param($stmtCount, "i", $postId);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $countRow = ($resCount !== false) ? mysqli_fetch_assoc($resCount) : ['like_count' => 0];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => (int) ($countRow['like_count'] ?? 0),
    ]);
    exit;
}

header("Location: home.php");
exit;
