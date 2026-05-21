<?php
/**
 * API Endpoint: Accept or Reject Friend Request
 * Updates the status of a pending friend request and notifies the sender.
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

$userId = (int) $_SESSION['user_id'];
$requestId = (int) ($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? 'accept'; // Can be 'accept' or 'reject'

if ($requestId === 0) {
    echo json_encode(["status" => "error", "success" => false, "message" => "request_id is required"]);
    exit;
}

$status = ($action === 'reject') ? 'rejected' : 'accepted';

$sql = "UPDATE friend_requests SET status = ? WHERE id = ? AND receiver_id = ? AND status = 'pending'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sii", $status, $requestId, $userId);
$updateSuccess = mysqli_stmt_execute($stmt);

if (!$updateSuccess) {
    echo json_encode(["status" => "error", "success" => false, "message" => "Update failed", "details" => mysqli_error($conn)]);
    exit;
}

$rowsAffected = mysqli_stmt_affected_rows($stmt);

if ($rowsAffected > 0) {
    if ($status === 'accepted') {
        // Find original sender to notify them
        $sSql = "SELECT sender_id FROM friend_requests WHERE id = ?";
        $sStmt = mysqli_prepare($conn, $sSql);
        mysqli_stmt_bind_param($sStmt, "i", $requestId);
        mysqli_stmt_execute($sStmt);
        $sResult = mysqli_stmt_get_result($sStmt);
        
        if ($sRow = mysqli_fetch_assoc($sResult)) {
            $origSender = $sRow['sender_id'];
            // Lookup acceptor username
            $acceptorName = null;
            $aSql = "SELECT username FROM users WHERE id = ? LIMIT 1";
            if ($aStmt = mysqli_prepare($conn, $aSql)) {
                mysqli_stmt_bind_param($aStmt, "i", $userId);
                mysqli_stmt_execute($aStmt);
                $aResult = mysqli_stmt_get_result($aStmt);
                if ($aRow = mysqli_fetch_assoc($aResult)) {
                    $acceptorName = $aRow['username'];
                }
            }

            $displayName = $acceptorName ? $acceptorName : "User $userId";
            $ntMsg = "$displayName accepted your friend request.";
            $nSql = "INSERT INTO notifications (user_id, type, message) VALUES (?, 'friend_accept', ?)";
            $nStmt = mysqli_prepare($conn, $nSql);
            mysqli_stmt_bind_param($nStmt, "is", $origSender, $ntMsg);
            mysqli_stmt_execute($nStmt);
        }
    }
    echo json_encode(["status" => "success", "message" => "Request $status"]);
} else {
    echo json_encode(["status" => "error", "message" => "Request not found or already processed OR you are not the receiver."]);
}
