<?php
/**
 * API Endpoint: Send Direct Message
 * Inserts a new message into the database and notifies the recipient.
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

$senderId = (int) $_SESSION['user_id'];
$receiverId = (int) ($_POST['receiver_id'] ?? $_POST['recipient_id'] ?? 0);
$messageText = trim($_POST['message_text'] ?? '');
$postId = (int) ($_POST['post_id'] ?? 0);
$postId = $postId > 0 ? $postId : null;

$attachmentPath = null;
$attachmentUpload = $_FILES['attachment'] ?? null;
$hasAttachment = $attachmentUpload && $attachmentUpload['error'] !== UPLOAD_ERR_NO_FILE;

if ($receiverId === 0 || ($messageText === '' && !$hasAttachment)) {
    echo json_encode(["status" => "error", "message" => "receiver_id and either message_text or attachment are required"]);
    exit;
}

if ($hasAttachment) {
    if ($attachmentUpload['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "Attachment upload failed"]);
        exit;
    }
    if ($attachmentUpload['size'] > 5 * 1024 * 1024) {
        echo json_encode(["status" => "error", "message" => "Attachment too large (max 5MB)"]);
        exit;
    }

    $allowedTypes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'zip' => ['application/zip', 'application/x-zip-compressed']
    ];
    $extension = strtolower(pathinfo($attachmentUpload['name'], PATHINFO_EXTENSION));
    $detectedMime = mime_content_type($attachmentUpload['tmp_name']) ?: '';

    if (!isset($allowedTypes[$extension]) || !in_array($detectedMime, $allowedTypes[$extension], true)) {
        echo json_encode(["status" => "error", "message" => "Invalid file type"]);
        exit;
    }

    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = uniqid('msg_', true) . '.' . $extension;
    $attachmentPath = $uploadDir . $filename;

    if (!move_uploaded_file($attachmentUpload['tmp_name'], $attachmentPath)) {
        echo json_encode(["status" => "error", "message" => "Failed to save attachment"]);
        exit;
    }
}

$sqlInsert = "INSERT INTO messages (sender_id, receiver_id, post_id, message_text, attachment_path) VALUES (?, ?, ?, ?, ?)";
$stmtInsert = mysqli_prepare($conn, $sqlInsert);
$messageTextForDb = $messageText !== '' ? $messageText : ' ';
mysqli_stmt_bind_param($stmtInsert, "iiiss", $senderId, $receiverId, $postId, $messageTextForDb, $attachmentPath);
$insertSuccess = mysqli_stmt_execute($stmtInsert);

if (!$insertSuccess) {
    echo json_encode(["status" => "error", "message" => "Failed to send message", "details" => mysqli_error($conn)]);
} else {
    // Insert Notification - Store senderId in related_id so we can easily clear these notifications when the conversation is opened
    $username = $_SESSION['username'] ?? "User $senderId";
    $ntMsg = "You received a new message from $username.";
    $sqlNotify = "INSERT INTO notifications (user_id, type, related_id, message) VALUES (?, 'message', ?, ?)";
    $stmtNotify = mysqli_prepare($conn, $sqlNotify);
    mysqli_stmt_bind_param($stmtNotify, "iis", $receiverId, $senderId, $ntMsg);
    mysqli_stmt_execute($stmtNotify);

    echo json_encode(["status" => "success", "message" => "Message sent"]);
}
