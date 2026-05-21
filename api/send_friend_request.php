<?php
/**
 * API Endpoint: Send Friend Request
 * Creates a new pending friend request between two users and sends a notification.
 */
header('Content-Type: application/json');
require_once "../config/db.php";
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "success" => false, "message" => "Authentication required"]);
    exit;
}

$senderId = (int) $_SESSION['user_id'];
$receiverId = (int) ($_POST['receiver_id'] ?? 0);

if ($receiverId === 0) {
    echo json_encode(["status" => "error", "success" => false, "message" => "receiver_id is required"]);
    exit;
}

if ($senderId === $receiverId) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Cannot add yourself"]);
    exit;
}

$sqlCheck = "SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
$stmtCheck = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmtCheck, "iiii", $senderId, $receiverId, $receiverId, $senderId);
mysqli_stmt_execute($stmtCheck);
$resultCheck = mysqli_stmt_get_result($stmtCheck);

if (mysqli_num_rows($resultCheck) > 0) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Friend request already exists"]);
    exit;
}

$sqlInsert = "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')";
$stmtInsert = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param($stmtInsert, "ii", $senderId, $receiverId);
$insertSuccess = mysqli_stmt_execute($stmtInsert);

if (!$insertSuccess) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Failed to send request", "details" => mysqli_error($conn)]);
} else {
    // Insert notification
    $ntMsg = "User $senderId sent you a friend request.";
    $sqlNotify = "INSERT INTO notifications (user_id, type, message) VALUES (?, 'friend_request', ?)";
    $stmtNotify = mysqli_prepare($conn, $sqlNotify);
    mysqli_stmt_bind_param($stmtNotify, "is", $receiverId, $ntMsg);
    mysqli_stmt_execute($stmtNotify);

    echo json_encode(["status" => "success", "success" => true, "message" => "Friend request sent"]);
}
