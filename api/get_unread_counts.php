<?php
/**
 * API Endpoint: Get Unread Counts
 * Returns the number of unread messages and notifications for the logged-in user.
 */
header('Content-Type: application/json');
require_once "../config/db.php";
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Count unread messages
$msgSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$msgStmt = mysqli_prepare($conn, $msgSql);
mysqli_stmt_bind_param($msgStmt, "i", $userId);
mysqli_stmt_execute($msgStmt);
$msgRes = mysqli_stmt_get_result($msgStmt);
$unreadMessages = ($msgRes && $row = mysqli_fetch_assoc($msgRes)) ? (int)$row['count'] : 0;

// Count unread notifications (excluding messages)
$notifSql = "SELECT COUNT(id) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'message'";
$notifStmt = mysqli_prepare($conn, $notifSql);
mysqli_stmt_bind_param($notifStmt, "i", $userId);
mysqli_stmt_execute($notifStmt);
$notifRes = mysqli_stmt_get_result($notifStmt);
$unreadNotifications = ($notifRes && $row = mysqli_fetch_assoc($notifRes)) ? (int)$row['count'] : 0;

echo json_encode([
    "status" => "success",
    "data" => [
        "unread_messages" => $unreadMessages,
        "unread_notifications" => $unreadNotifications,
        "total_unread" => $unreadMessages + $unreadNotifications
    ]
]);
