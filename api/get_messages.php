<?php
/**
 * API Endpoint: Get Chat History
 * Retrieves the full conversation history between the logged-in user and a recipient.
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
$recipientId = (int) ($_GET['recipient_id'] ?? 0);
$lastId = (int) ($_GET['last_id'] ?? 0);

if ($recipientId === 0) {
    echo json_encode(["status" => "error", "message" => "recipient_id is required"]);
    exit;
}

// Auto-mark messages from this recipient to the current user as read
$markMsgsSql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
$markMsgsStmt = mysqli_prepare($conn, $markMsgsSql);
mysqli_stmt_bind_param($markMsgsStmt, "ii", $recipientId, $userId);
mysqli_stmt_execute($markMsgsStmt);

// Also mark notifications for these messages as read
$markNotifSql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'message' AND related_id = ? AND is_read = 0";
$markNotifStmt = mysqli_prepare($conn, $markNotifSql);
mysqli_stmt_bind_param($markNotifStmt, "ii", $userId, $recipientId);
mysqli_stmt_execute($markNotifStmt);

$sql = "
    SELECT 
        m.id, 
        m.sender_id, 
        m.receiver_id, 
        m.post_id, 
        m.message_text, 
        m.attachment_path,
        m.created_at,
        sender.username AS sender_username,
        p.product_name AS post_product_name,
        p.price AS post_price
    FROM messages m
    INNER JOIN users sender ON m.sender_id = sender.id
    LEFT JOIN posts p ON m.post_id = p.id
    WHERE ((m.sender_id = ? AND m.receiver_id = ?)
       OR (m.sender_id = ? AND m.receiver_id = ?))
       AND m.id > ?
    ORDER BY m.created_at ASC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiiii", $userId, $recipientId, $recipientId, $userId, $lastId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    echo json_encode(["status" => "error", "message" => "Query failed", "details" => mysqli_error($conn)]);
    exit;
}

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = $row;
}

echo json_encode(["status" => "success", "data" => $messages]);
