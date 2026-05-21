<?php
/**
 * Delete Post Page
 * Handles the removal of posts from the database. 
 * Includes logic to delete associated images from Cloudinary and ensures 
 * only the post owner can perform the deletion.
 */
session_start();
require_once "../config/db.php";
require_once "../config/cloudinary_helpers.php";

// Redirect to login if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Retrieve the post ID to be deleted
$postId = (int) ($_POST["post_id"] ?? 0);

if ($postId > 0) {
    // Fetch post to determine owner and image path
    $sqlSelect = "SELECT id, user_id, image_path FROM posts WHERE id = ?";
    $stmtSelect = mysqli_prepare($conn, $sqlSelect);
    mysqli_stmt_bind_param($stmtSelect, "i", $postId);
    mysqli_stmt_execute($stmtSelect);
    $resultSelect = mysqli_stmt_get_result($stmtSelect);
    $post = ($resultSelect !== false) ? mysqli_fetch_assoc($resultSelect) : null;

    if ($post) {
        $isOwner = ((int)$post['user_id'] === (int)($_SESSION['user_id'] ?? 0));
        $isAdmin = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin');

        if ($isOwner || $isAdmin) {
            // 1. Delete associated likes
            $sqlLikes = "DELETE FROM post_likes WHERE post_id = ?";
            $stmtLikes = mysqli_prepare($conn, $sqlLikes);
            mysqli_stmt_bind_param($stmtLikes, "i", $postId);
            mysqli_stmt_execute($stmtLikes);

            // 2. Delete associated comments
            $sqlComments = "DELETE FROM comments WHERE post_id = ?";
            $stmtComments = mysqli_prepare($conn, $sqlComments);
            mysqli_stmt_bind_param($stmtComments, "i", $postId);
            mysqli_stmt_execute($stmtComments);

            // 3. Delete related notifications
            // related_id stores post_id for 'like', 'comment', and 'post' types
            $sqlNotifs = "DELETE FROM notifications WHERE related_id = ? AND type IN ('like', 'comment', 'post')";
            $stmtNotifs = mysqli_prepare($conn, $sqlNotifs);
            mysqli_stmt_bind_param($stmtNotifs, "i", $postId);
            mysqli_stmt_execute($stmtNotifs);

            // 4. Update messages referencing this post (set post_id to NULL)
            $sqlMsgs = "UPDATE messages SET post_id = NULL WHERE post_id = ?";
            $stmtMsgs = mysqli_prepare($conn, $sqlMsgs);
            mysqli_stmt_bind_param($stmtMsgs, "i", $postId);
            mysqli_stmt_execute($stmtMsgs);

            // 5. Update shared posts referencing this post (set shared_post_id to NULL)
            $sqlShares = "UPDATE posts SET shared_post_id = NULL WHERE shared_post_id = ?";
            $stmtShares = mysqli_prepare($conn, $sqlShares);
            mysqli_stmt_bind_param($stmtShares, "i", $postId);
            mysqli_stmt_execute($stmtShares);

            // 6. Delete the main post record from the database
            $sqlDel = "DELETE FROM posts WHERE id = ?";
            $stmtDel = mysqli_prepare($conn, $sqlDel);
            mysqli_stmt_bind_param($stmtDel, "i", $postId);
            mysqli_stmt_execute($stmtDel);

            // If the post had an image, delete it from Cloudinary
            if (!empty($post["image_path"])) {
                cloudinary_delete_by_url($post["image_path"]);
            }
        }
    }
}

header("Location: home.php");
exit;
