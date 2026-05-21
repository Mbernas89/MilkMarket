<?php
/**
 * API Endpoint: Mark All Notifications as Read
 * Updates the 'is_read' status for all notifications of the logged-in user.
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

$sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $userId);
$updateSuccess = mysqli_stmt_execute($stmt);

if (!$updateSuccess) {
    echo json_encode(["status" => "error", "message" => "Failed to update notifications", "details" => mysqli_error($conn)]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Notifications marked as read"]);
