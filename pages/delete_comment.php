<?php
/**
 * Delete Comment Page
 * Allows the comment owner or an admin to delete a comment.
 */
session_start();
require_once "../config/db.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$commentId = (int)($_POST['comment_id'] ?? 0);

if ($commentId > 0) {
    $sql = "SELECT id, user_id, post_id FROM comments WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $commentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $comment = ($res !== false) ? mysqli_fetch_assoc($res) : null;

    if ($comment) {
        $isOwner = ((int)$comment['user_id'] === (int)$_SESSION['user_id']);
        $isAdmin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');

        if ($isOwner || $isAdmin) {
            $delSql = "DELETE FROM comments WHERE id = ?";
            $delStmt = mysqli_prepare($conn, $delSql);
            mysqli_stmt_bind_param($delStmt, "i", $commentId);
            mysqli_stmt_execute($delStmt);
        }
    }
}

// Redirect back to referring page or home
$redirect = $_SERVER['HTTP_REFERER'] ?? 'home.php';
header('Location: ' . $redirect);
exit;
