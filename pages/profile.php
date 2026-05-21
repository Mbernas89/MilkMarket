<?php
/**
 * User Profile Page
 * Displays a user's profile info, bio, and their posts.
 */
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$myId = (int) $_SESSION["user_id"];
$profileUserId = isset($_GET["user_id"]) ? (int) $_GET["user_id"] : $myId;

// Fetch unread messages count for badge
$unreadMsgCount = 0;
$unreadSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unreadStmt = mysqli_prepare($conn, $unreadSql);
mysqli_stmt_bind_param($unreadStmt, "i", $myId);
mysqli_stmt_execute($unreadStmt);
$unreadRes = mysqli_stmt_get_result($unreadStmt);
if ($unreadRes !== false && $unreadRow = mysqli_fetch_assoc($unreadRes)) {
    $unreadMsgCount = (int) $unreadRow['count'];
}

// Fetch user info
$profileUser = null;
$userSql = "SELECT id, username, profile_image, bio FROM users WHERE id = ?";
$userStmt = mysqli_prepare($conn, $userSql);
mysqli_stmt_bind_param($userStmt, "i", $profileUserId);
mysqli_stmt_execute($userStmt);
$userRes = mysqli_stmt_get_result($userStmt);
if ($userRes !== false) {
    $profileUser = mysqli_fetch_assoc($userRes);
}

if (!$profileUser) {
    header("Location: home.php");
    exit;
}

// Check friendship status
$friendStatus = 'none';
$friendSql = "SELECT status, sender_id FROM friend_requests WHERE 
    (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
$friendStmt = mysqli_prepare($conn, $friendSql);
mysqli_stmt_bind_param($friendStmt, "iiii", $myId, $profileUserId, $profileUserId, $myId);
mysqli_stmt_execute($friendStmt);
$friendRes = mysqli_stmt_get_result($friendStmt);
$friendship = ($friendRes !== false) ? mysqli_fetch_assoc($friendRes) : null;
if ($friendship) {
    if ($friendship['status'] === 'accepted')
        $friendStatus = 'accepted';
    elseif ($friendship['status'] === 'pending') {
        $friendStatus = ((int) $friendship['sender_id'] === $myId) ? 'pending_sent' : 'pending_received';
    }
}

// Handle add friend action from this page
$actionMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_friend') {
    $reqSql = "INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) 
               VALUES (?, ?, 'pending', NOW())";
    $reqStmt = mysqli_prepare($conn, $reqSql);
    mysqli_stmt_bind_param($reqStmt, "ii", $myId, $profileUserId);
    if (mysqli_stmt_execute($reqStmt)) {
        // Insert notification
        $notifMsg = htmlspecialchars($_SESSION['username']) . " sent you a friend request.";
        $notifSql = "INSERT INTO notifications (user_id, type, message, related_id, created_at) VALUES (?, 'friend_request', ?, LAST_INSERT_ID(), NOW())";
        $notifStmt = mysqli_prepare($conn, $notifSql);
        mysqli_stmt_bind_param($notifStmt, "is", $profileUserId, $notifMsg);
        mysqli_stmt_execute($notifStmt);
        $friendStatus = 'pending_sent';
        $actionMessage = "Friend request sent!";
    }
}

// Fetch user's posts
$posts = [];
$postsSql = "SELECT posts.id, posts.content, posts.image_path, posts.created_at,
                (SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) AS like_count,
                (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS comment_count
             FROM posts WHERE posts.user_id = ?
             ORDER BY posts.created_at DESC LIMIT 20";
$postsStmt = mysqli_prepare($conn, $postsSql);
mysqli_stmt_bind_param($postsStmt, "i", $profileUserId);
mysqli_stmt_execute($postsStmt);
$postsRes = mysqli_stmt_get_result($postsStmt);
if ($postsRes !== false) {
    while ($row = mysqli_fetch_assoc($postsRes)) {
        $posts[] = $row;
    }
}

$isSelf = ($myId === $profileUserId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profileUser['username']); ?>'s Profile - Milk Market</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    
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
                        <img class="avatar-circle small-avatar avatar-image"
                            src="<?php echo htmlspecialchars($_SESSION["profile_image"]); ?>" alt="Your profile picture">
                    <?php else: ?>
                        <div class="avatar-circle small-avatar">
                            <?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                    <?php endif; ?>
                    <?php if ($unreadMsgCount > 0): ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity"></span>
                    <?php else: ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" style="display:none;"></span>
                    <?php endif; ?>
                    <span class="taskbar-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
                </summary>
                <div class="taskbar-dropdown">
                    <a href="home.php">Feed</a>
                    <a href="notifications.php">Notifications <span id="nav-notif-count" class="red-dot"
                            style="display:none;">0</span></a>
                    <a href="friends.php">Find Friends</a>
                    <a href="messages.php">Messages <span id="nav-msg-count" class="red-dot" <?php if ($unreadMsgCount <= 0): ?>style="display:none;" <?php endif; ?>><?php echo $unreadMsgCount; ?></span></a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <div class="profile-page-container">
        <?php if ($actionMessage): ?>
            <p class="message success"><?php echo htmlspecialchars($actionMessage); ?></p>
        <?php endif; ?>

        <div class="profile-hero-card">
            <?php if (!empty($profileUser['profile_image'])): ?>
                <img class="profile-hero-avatar" src="<?php echo htmlspecialchars($profileUser['profile_image']); ?>"
                    alt="Profile picture">
            <?php else: ?>
                <div class="profile-hero-avatar initials"><?php echo strtoupper(substr($profileUser['username'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="profile-hero-info" style="flex:1;">
                <h1><?php echo htmlspecialchars($profileUser['username']); ?></h1>
                <p><?php echo htmlspecialchars($profileUser['bio'] ?? 'No bio yet.'); ?></p>
                <div class="profile-actions">
                    <?php if ($isSelf): ?>
                        <a href="settings.php" class="primary-button">Edit Profile</a>
                    <?php else: ?>
                        <?php if ($friendStatus === 'accepted'): ?>
                            <span class="secondary-action-button"
                                style="display:inline-block;padding:8px 18px;border-radius:8px;">✓ Friends</span>
                        <?php elseif ($friendStatus === 'pending_sent'): ?>
                            <span class="secondary-action-button"
                                style="display:inline-block;padding:8px 18px;border-radius:8px;">Request Sent</span>
                        <?php elseif ($friendStatus === 'pending_received'): ?>
                            <span class="secondary-action-button"
                                style="display:inline-block;padding:8px 18px;border-radius:8px;">Respond in Notifications</span>
                        <?php else: ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="action" value="add_friend">
                                <button type="submit" class="primary-button">Add Friend</button>
                            </form>
                        <?php endif; ?>
                        <a href="messages.php?recipient_id=<?php echo $profileUserId; ?>"
                            class="message-seller-button">Message</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-posts-section">
            <h2><?php echo $isSelf ? 'Your Posts' : htmlspecialchars($profileUser['username']) . "'s Posts"; ?></h2>
            <?php if (empty($posts)): ?>
                <div class="profile-empty">
                    <p><?php echo $isSelf ? "You haven't posted anything yet." : "No posts yet."; ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="profile-post-card">
                        <?php if (!empty($post['content'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($post['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Post image">
                        <?php endif; ?>
                        <div class="profile-post-meta">
                            <span><?php echo htmlspecialchars($post['like_count']); ?> likes</span>
                            <span><?php echo htmlspecialchars($post['comment_count']); ?> comments</span>
                            <span><?php echo htmlspecialchars($post['created_at']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="../assets/realtime_notifications.js"></script>
</body>

</html>