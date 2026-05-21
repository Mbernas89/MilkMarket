<?php
/**
 * API Endpoint: Get Friends List
 * Retrieves a list of all users with whom the logged-in user has an 'accepted' friendship status.
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

$sql = "
    SELECT u.id, u.username, u.profile_image 
    FROM users u
    INNER JOIN friend_requests fr 
    ON (u.id = fr.sender_id OR u.id = fr.receiver_id)
    WHERE fr.status = 'accepted'
    AND (fr.sender_id = ? OR fr.receiver_id = ?)
    AND u.id != ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $userId, $userId, $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    echo json_encode(["status" => "error", "message" => "Query failed", "details" => mysqli_error($conn)]);
    exit;
}

$friends = [];
while ($row = mysqli_fetch_assoc($result)) {
    $friends[] = $row;
}

echo json_encode(["status" => "success", "data" => $friends]);
