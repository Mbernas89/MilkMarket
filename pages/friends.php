<?php
/**
 * Friends Page - Manage Friendships and Discover Users
 * Allows users to view their friends, accept/reject pending requests, 
 * and discover new people to add.
 */
session_start();
require_once "../config/db.php";

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Fetch unread messages count for navbar badge
$unreadMsgCount = 0;
$unreadSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unreadStmt = mysqli_prepare($conn, $unreadSql);
mysqli_stmt_bind_param($unreadStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($unreadStmt);
$unreadRes = mysqli_stmt_get_result($unreadStmt);
if ($unreadRes !== false && $unreadRow = mysqli_fetch_assoc($unreadRes)) {
    $unreadMsgCount = (int) $unreadRow['count'];
}

// Fetch unread notifications count (excluding message alerts)
$unreadNotifCount = 0;
$notifCountSql = "SELECT COUNT(id) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'message'";
$notifCountStmt = mysqli_prepare($conn, $notifCountSql);
mysqli_stmt_bind_param($notifCountStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($notifCountStmt);
$notifCountRes = mysqli_stmt_get_result($notifCountStmt);
if ($notifCountRes !== false && $notifRow = mysqli_fetch_assoc($notifCountRes)) {
    $unreadNotifCount = (int) $notifRow['count'];
}

$myId = (int) $_SESSION['user_id'];
$message = '';

// Handle form submissions for friend actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['target_id'] ?? 0);
    $requestId = (int) ($_POST['request_id'] ?? 0);

    // Action: Send a new friend request
    if ($action === 'add' && $targetId > 0 && $targetId !== $myId) {
        $checkSql = "SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "iiii", $myId, $targetId, $targetId, $myId);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        if (mysqli_num_rows($checkRes) === 0) {
            $insSql = "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')";
            $insStmt = mysqli_prepare($conn, $insSql);
            mysqli_stmt_bind_param($insStmt, "ii", $myId, $targetId);
            mysqli_stmt_execute($insStmt);
            $requestId = mysqli_insert_id($conn);
            
            // Insert a notification for the recipient with the related friend request ID
            $ntMsg = $_SESSION["username"] . " wants to add you to be friends.";
            $notifSql = "INSERT INTO notifications (user_id, type, related_id, message) VALUES (?, 'friend_request', ?, ?)";
            $notifStmt = mysqli_prepare($conn, $notifSql);
            mysqli_stmt_bind_param($notifStmt, "iis", $targetId, $requestId, $ntMsg);
            mysqli_stmt_execute($notifStmt);

            $message = "Friend request sent!";
        }
    } 
    // Action: Accept a pending request
    elseif ($action === 'accept' && $requestId > 0) {
        $accSql = "UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?";
        $accStmt = mysqli_prepare($conn, $accSql);
        mysqli_stmt_bind_param($accStmt, "ii", $requestId, $myId);
        mysqli_stmt_execute($accStmt);
        $message = "Friend request accepted!";
    } 
    // Action: Reject a pending request
    elseif ($action === 'reject' && $requestId > 0) {
        $rejSql = "UPDATE friend_requests SET status = 'rejected' WHERE id = ? AND receiver_id = ?";
        $rejStmt = mysqli_prepare($conn, $rejSql);
        mysqli_stmt_bind_param($rejStmt, "ii", $requestId, $myId);
        mysqli_stmt_execute($rejStmt);
        $message = "Friend request rejected!";
    }
}

// Fetch pending requests FOR me
$pendingRequests = [];
$pendingSql = "
    SELECT fr.id as request_id, u.id as user_id, u.username, u.profile_image 
    FROM friend_requests fr
    INNER JOIN users u ON fr.sender_id = u.id
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
";
$stmtP = mysqli_prepare($conn, $pendingSql);
mysqli_stmt_bind_param($stmtP, "i", $myId);
mysqli_stmt_execute($stmtP);
$resP = mysqli_stmt_get_result($stmtP);
if ($resP !== false) {
    while ($r = mysqli_fetch_assoc($resP)) {
        $pendingRequests[] = $r;
    }
}

// Fetch my friends
$myFriends = [];
$friendsSql = "
    SELECT u.id, u.username, u.profile_image 
    FROM users u
    INNER JOIN friend_requests fr 
    ON (u.id = fr.sender_id OR u.id = fr.receiver_id)
    WHERE fr.status = 'accepted'
    AND (fr.sender_id = ? OR fr.receiver_id = ?)
    AND u.id != ?
";
$stmtF = mysqli_prepare($conn, $friendsSql);
mysqli_stmt_bind_param($stmtF, "iii", $myId, $myId, $myId);
mysqli_stmt_execute($stmtF);
$resF = mysqli_stmt_get_result($stmtF);
$friendIds = [];
if ($resF !== false) {
    while ($r = mysqli_fetch_assoc($resF)) {
        $myFriends[] = $r;
        $friendIds[] = $r['id'];
    }
}

// Fetch all other users to discover
$discoverUsers = [];
$discoverSql = "
    SELECT id, username, profile_image 
    FROM users
    WHERE id != ?
    AND id NOT IN (
        SELECT receiver_id FROM friend_requests WHERE sender_id = ?
        UNION
        SELECT sender_id FROM friend_requests WHERE receiver_id = ?
    )
    LIMIT 20
";
$stmtD = mysqli_prepare($conn, $discoverSql);
mysqli_stmt_bind_param($stmtD, "iii", $myId, $myId, $myId);
mysqli_stmt_execute($stmtD);
$resD = mysqli_stmt_get_result($stmtD);
if ($resD !== false) {
    while ($r = mysqli_fetch_assoc($resD)) {
        $discoverUsers[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Friends - Milk Market</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .friends-container { max-width: 800px; margin: 40px auto; display: flex; flex-direction: column; gap: 24px; padding: 0 16px; }
        .friends-section { background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-top: 16px; }
        .user-card { display: flex; flex-direction: column; align-items: center; padding: 16px; border: 1px solid var(--border-color); border-radius: 8px; text-align: center; background: rgba(0,0,0,0.02); }
        .user-card img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; }
        .user-card .avatar-circle { width: 64px; height: 64px; font-size: 24px; margin-bottom: 12px; }
        .user-card strong { display: block; margin-bottom: 12px; font-size: 15px; }
        .action-row { display: flex; gap: 8px; justify-content: center; width: 100%; }
        .action-row button, .action-row a { flex: 1; padding: 6px 0; font-size: 13px; }
    </style>
</head>
<body class="feed-body">
    <header class="taskbar">
        <div class="taskbar-inner">
            <a class="taskbar-brand taskbar-home-link" href="home.php">
                <img class="taskbar-logo-image" src="../assets/milk-market-logo.png" alt="Milk Market logo">
                <div>
                    <strong>Milk Market</strong>
                    <p>Community</p>
                </div>
            </a>
            <form class="taskbar-search" method="get" action="search_users.php">
                <input type="search" name="q" placeholder="Search users...">
                <button type="submit">Search</button>
            </form>
            <details class="taskbar-menu">
                <summary class="taskbar-trigger account-trigger">
                    <?php if (!empty($_SESSION["profile_image"])): ?>
                        <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($_SESSION["profile_image"]); ?>" alt="Your profile picture">
                    <?php else: ?>
                        <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                    <?php endif; ?>
                    <?php if (($unreadNotifCount + $unreadMsgCount) > 0): ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity"></span>
                    <?php else: ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity" style="display:none;"></span>
                    <?php endif; ?>
                    <span class="taskbar-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
                </summary>
                <div class="taskbar-dropdown">
                    <a href="home.php">Feed</a>
                    <a href="notifications.php">Notifications <span id="nav-notif-count" class="red-dot" <?php if ($unreadNotifCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadNotifCount; ?></span></a>
                    <a href="messages.php">Messages <span id="nav-msg-count" class="red-dot" <?php if ($unreadMsgCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadMsgCount; ?></span></a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <div class="friends-container">
        <?php if ($message): ?>
            <p class="message success"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($pendingRequests)): ?>
        <section class="friends-section">
            <h2>Friend Requests</h2>
            <div class="user-grid">
                <?php foreach ($pendingRequests as $user): ?>
                    <div class="user-card profile-preview-trigger" data-user-id="<?php echo $user['user_id']; ?>" style="cursor:pointer;">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="avatar-circle"><?php echo strtoupper(substr($user["username"], 0, 1)); ?></div>
                        <?php endif; ?>
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <div class="action-row">
                            <form method="post" style="flex:1;">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="request_id" value="<?php echo $user['request_id']; ?>">
                                <button class="primary-button" style="width:100%;">Accept</button>
                            </form>
                            <form method="post" style="flex:1;">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="request_id" value="<?php echo $user['request_id']; ?>">
                                <button class="primary-button" style="width:100%; background-color: #dc3545; border-color: #dc3545;">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="friends-section">
            <h2>My Friends</h2>
            <?php if (empty($myFriends)): ?>
                <p class="muted">You haven't added any friends yet.</p>
            <?php else: ?>
                <div class="user-grid">
                    <?php foreach ($myFriends as $user): ?>
                        <div class="user-card profile-preview-trigger" data-user-id="<?php echo $user['id']; ?>" style="cursor:pointer;">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-circle"><?php echo strtoupper(substr($user["username"], 0, 1)); ?></div>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <div class="action-row">
                                <a href="messages.php?recipient_id=<?php echo $user['id']; ?>" class="primary-button" style="text-align:center; display:block; box-sizing:border-box;">Message</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="friends-section">
            <h2>Discover Users</h2>
            <?php if (empty($discoverUsers)): ?>
                <p class="muted">No new users to discover right now.</p>
            <?php else: ?>
                <div class="user-grid">
                    <?php foreach ($discoverUsers as $user): ?>
                        <div class="user-card profile-preview-trigger" data-user-id="<?php echo $user['id']; ?>" style="cursor:pointer;">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-circle"><?php echo strtoupper(substr($user["username"], 0, 1)); ?></div>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <form method="post" style="width:100%;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="target_id" value="<?php echo $user['id']; ?>">
                                <button class="primary-button" style="width:100%;">Add Friend</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="preview-modal-shell" id="profile-preview-modal" hidden>
        <div class="preview-modal-backdrop"></div>
        <div class="preview-modal-dialog profile-preview-card">
            <div class="preview-modal-header">
                <div>
                    <h4>User Profile</h4>
                </div>
                <button type="button" class="preview-close-button">&times;</button>
            </div>
            <div class="profile-preview-content">
                <div class="profile-preview-top">
                    <div id="pp-avatar-container"></div>
                    <div>
                        <h2 id="pp-username"></h2>
                        <p id="pp-bio" class="muted"></p>
                    </div>
                </div>
                <div class="profile-preview-actions">
                    <button type="button" id="pp-add-friend-btn" class="primary-button">Add Friend</button>
                    <a id="pp-message-link" href="#" class="message-seller-button">Message</a>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/profile_preview.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/feed.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/realtime_notifications.js"></script>
</body>
</html>
