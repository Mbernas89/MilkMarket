<?php
/**
 * API Endpoint: Get User Preview
 * Fetches bio, profile image, and friendship status for a specific user.
 */
header('Content-Type: application/json');
require_once "../config/db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];
$targetUserId = (int) ($_GET['user_id'] ?? 0);

if ($targetUserId === 0) {
    echo json_encode(["status" => "error", "message" => "user_id is required"]);
    exit;
}

// Fetch user info
$userSql = "SELECT id, username, profile_image, bio FROM users WHERE id = ?";
$userStmt = mysqli_prepare($conn, $userSql);
mysqli_stmt_bind_param($userStmt, "i", $targetUserId);
mysqli_stmt_execute($userStmt);
$userRes = mysqli_stmt_get_result($userStmt);
$user = ($userRes !== false) ? mysqli_fetch_assoc($userRes) : null;

if (!$user) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

// Check friendship status
$friendSql = "SELECT status, sender_id FROM friend_requests WHERE 
    (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
$friendStmt = mysqli_prepare($conn, $friendSql);
mysqli_stmt_bind_param($friendStmt, "iiii", $currentUserId, $targetUserId, $targetUserId, $currentUserId);
mysqli_stmt_execute($friendStmt);
$friendRes = mysqli_stmt_get_result($friendStmt);
$friendship = ($friendRes !== false) ? mysqli_fetch_assoc($friendRes) : null;

$status = 'none'; // none, pending_sent, pending_received, accepted
if ($friendship) {
    if ($friendship['status'] === 'accepted') {
        $status = 'accepted';
    } else if ($friendship['status'] === 'pending') {
        if ((int) $friendship['sender_id'] === $currentUserId) {
            $status = 'pending_sent';
        } else {
            $status = 'pending_received';
        }
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "id" => $user['id'],
        "username" => $user['username'],
        "profile_image" => $user['profile_image'],
        "bio" => $user['bio'] ?? "No bio available.",
        "friendship_status" => $status,
        "is_self" => ($currentUserId === $targetUserId)
    ]
]);
