<?php
/**
 * API Endpoint: Get Notifications
 * Retrieves a list of recent notifications for the logged-in user.
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

$sql = "
    SELECT n.id, n.type, n.message, n.is_read, n.created_at, n.related_id, fr.status as fr_status 
    FROM notifications n
    LEFT JOIN friend_requests fr ON n.type = 'friend_request' AND n.related_id = fr.id
    WHERE n.user_id = ? AND n.id > ?
    ORDER BY n.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $userId, $lastId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    echo json_encode(["status" => "error", "message" => "Query failed", "details" => mysqli_error($conn)]);
    exit;
}

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

echo json_encode(["status" => "success", "data" => $notifications]);
