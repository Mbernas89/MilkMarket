<?php
session_start();
require_once "../config/db.php";

function is_ajax_request(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

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
$content = trim($_POST["content"] ?? "");

if ($postId > 0 && $content !== "") {
    // Insert the new comment using a prepared statement
    $sqlIns = "INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)";
    $stmtIns = mysqli_prepare($conn, $sqlIns);
    mysqli_stmt_bind_param($stmtIns, "iis", $postId, $_SESSION["user_id"], $content);
    $insertSuccess = mysqli_stmt_execute($stmtIns);
    
    if ($insertSuccess) {
        // Find the author of the post to send them a notification
        $sqlAuthor = "SELECT user_id FROM posts WHERE id = ?";
        $stmtAuthor = mysqli_prepare($conn, $sqlAuthor);
        mysqli_stmt_bind_param($stmtAuthor, "i", $postId);
        mysqli_stmt_execute($stmtAuthor);
        $resAuthor = mysqli_stmt_get_result($stmtAuthor);
        
        if ($resAuthor !== false && $pRow = mysqli_fetch_assoc($resAuthor)) {
            $postAuthorId = $pRow['user_id'];
            // Send notification only if the commenter is not the post author
            if ($postAuthorId != $_SESSION["user_id"]) {
                $ntMsg = $_SESSION['username'] . " commented on your post.";
                $sqlNotif = "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'comment', ?, ?)";
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
    $sqlCount = "SELECT COUNT(*) AS comment_count FROM comments WHERE post_id = ?";
    $stmtCount = mysqli_prepare($conn, $sqlCount);
    mysqli_stmt_bind_param($stmtCount, "i", $postId);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $countRow = ($resCount !== false) ? mysqli_fetch_assoc($resCount) : ['comment_count' => 0];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'comment_count' => (int) ($countRow['comment_count'] ?? 0),
        'comment' => [
            'username' => $_SESSION['username'] ?? 'User',
            'profile_image' => $_SESSION['profile_image'] ?? null,
            'content' => $content,
        ],
    ]);
    exit;
}

header("Location: home.php");
exit;
