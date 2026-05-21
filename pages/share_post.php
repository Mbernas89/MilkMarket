<?php
/**
 * Share Post Page
 * Allows users to share an existing post to their own feed. 
 * Supports AJAX for real-time share count updates.
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

if ($postId > 0) {
    // Fetch the original post content to verify its existence
    $sqlSel = "SELECT content FROM posts WHERE id = ?";
    $stmtSel = mysqli_prepare($conn, $sqlSel);
    mysqli_stmt_bind_param($stmtSel, "i", $postId);
    mysqli_stmt_execute($stmtSel);
    $resSel = mysqli_stmt_get_result($stmtSel);
    $originalPost = ($resSel !== false) ? mysqli_fetch_assoc($resSel) : null;

    if ($originalPost) {
        // Create a new post entry that references the original post ID via shared_post_id
        $shareText = "Shared a dairy update.";
        $sqlIns = "INSERT INTO posts (user_id, content, shared_post_id) VALUES (?, ?, ?)";
        $stmtIns = mysqli_prepare($conn, $sqlIns);
        mysqli_stmt_bind_param($stmtIns, "isi", $_SESSION["user_id"], $shareText, $postId);
        mysqli_stmt_execute($stmtIns);
    }
}

if (is_ajax_request()) {
    $sqlCount = "SELECT COUNT(*) AS share_count FROM posts WHERE shared_post_id = ?";
    $stmtCount = mysqli_prepare($conn, $sqlCount);
    mysqli_stmt_bind_param($stmtCount, "i", $postId);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    $countRow = ($resCount !== false) ? mysqli_fetch_assoc($resCount) : ['share_count' => 0];

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'share_count' => (int) ($countRow['share_count'] ?? 0),
    ]);
    exit;
}

header("Location: home.php");
exit;
