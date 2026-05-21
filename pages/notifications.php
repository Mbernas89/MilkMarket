<?php
/**
 * Notifications Page
 * Displays a list of all user notifications (likes, comments, friend requests) 
 * and marks them as read once the page is loaded.
 */
session_start();
require_once "../config/db.php";

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Automatically mark all current notifications as read for the logged-in user
// We exclude 'message' types as those are handled separately in the messages page.
$updNotifSql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0 AND type != 'message'";
$updNotifStmt = mysqli_prepare($conn, $updNotifSql);
mysqli_stmt_bind_param($updNotifStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($updNotifStmt);

$myId = (int) $_SESSION['user_id'];
$messageHeader = '';

// Handle friend request actions directly from the notifications page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifAction = $_POST['notif_action'] ?? '';
    $requestId = (int) ($_POST['request_id'] ?? 0);

    if ($requestId > 0) {
        if ($notifAction === 'accept') {
            $accSql = "UPDATE friend_requests SET status = 'accepted' WHERE id = ? AND receiver_id = ?";
            $accStmt = mysqli_prepare($conn, $accSql);
            mysqli_stmt_bind_param($accStmt, "ii", $requestId, $myId);
            mysqli_stmt_execute($accStmt);
            $messageHeader = "Friend request accepted!";
        } elseif ($notifAction === 'reject') {
            $rejSql = "UPDATE friend_requests SET status = 'rejected' WHERE id = ? AND receiver_id = ?";
            $rejStmt = mysqli_prepare($conn, $rejSql);
            mysqli_stmt_bind_param($rejStmt, "ii", $requestId, $myId);
            mysqli_stmt_execute($rejStmt);
            $messageHeader = "Friend request rejected!";
        }
    }
}

// Fetch unread messages count for the navbar badge
$unreadMsgCount = 0;
$unreadSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unreadStmt = mysqli_prepare($conn, $unreadSql);
mysqli_stmt_bind_param($unreadStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($unreadStmt);
$unreadRes = mysqli_stmt_get_result($unreadStmt);
if ($unreadRes !== false && $unreadRow = mysqli_fetch_assoc($unreadRes)) {
    $unreadMsgCount = (int) $unreadRow['count'];
}

// Retrieve the notification history for the user (including related friend request info)
$notifications = [];
$notifSql = "
    SELECT n.id, n.type, n.message, n.created_at, n.related_id, fr.status as fr_status
    FROM notifications n
    LEFT JOIN friend_requests fr ON n.type = 'friend_request' AND n.related_id = fr.id
    WHERE n.user_id = ? AND n.type != 'message' 
    ORDER BY n.created_at DESC
";
$stmtN = mysqli_prepare($conn, $notifSql);
mysqli_stmt_bind_param($stmtN, "i", $myId);
mysqli_stmt_execute($stmtN);
$resN = mysqli_stmt_get_result($stmtN);

if ($resN !== false) {
    while ($r = mysqli_fetch_assoc($resN)) {
        $notifications[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Milk Market</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
    <style>
        .notif-container { max-width: 800px; margin: 40px auto; padding: 0 16px; }
        .notif-card { background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; box-shadow: var(--shadow); }
        .notif-card h2 { margin-top: 0; margin-bottom: 24px; }
        .notif-list { display: flex; flex-direction: column; gap: 12px; }
        .notif-item { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px; border-radius: 12px; background: rgba(0,0,0,0.02); border: 1px solid var(--line); }
        .notif-content strong { color: var(--ink); display: block; margin-bottom: 4px; }
        .notif-time { color: var(--muted); font-size: 0.85rem; }
        .empty-state { text-align: center; padding: 40px 0; color: var(--muted); }
        .notif-actions { display: flex; gap: 8px; }
        .action-btn-small { padding: 6px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border: 0; transition: transform 0.1s ease; }
        .action-btn-small:active { transform: scale(0.96); }
        .confirm-btn { background: var(--fb-blue); color: #fff; }
        .deny-btn { background: #fff2ca; color: var(--fb-blue-deep); border: 1px solid #ead9a9; }
        .status-badge { font-size: 0.8rem; font-weight: 700; color: var(--muted); background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 999px; }
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
                    <?php if (($unreadMsgCount) > 0): ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity"></span>
                    <?php else: ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity" style="display:none;"></span>
                    <?php endif; ?>
                    <span class="taskbar-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></span>
                </summary>
                <div class="taskbar-dropdown">
                    <a href="home.php">Feed</a>
                    <a href="notifications.php">Notifications <span id="nav-notif-count" class="red-dot" style="display:none;">0</span></a>
                    <a href="friends.php">Find Friends</a>
                    <a href="messages.php">Messages <span id="nav-msg-count" class="red-dot" <?php if ($unreadMsgCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadMsgCount; ?></span></a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <div class="notif-container">
        <?php if ($messageHeader): ?>
            <p class="message success"><?php echo htmlspecialchars($messageHeader); ?></p>
        <?php endif; ?>

        <div class="notif-card">
            <h2>Notifications</h2>
            
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <h3>All caught up!</h3>
                    <p>You don't have any recent activity to review.</p>
                </div>
            <?php else: ?>
                <div class="notif-list">
                    <?php foreach ($notifications as $n): ?>
                        <div class="notif-item" data-id="<?php echo (int)$n['id']; ?>">
                            <div class="notif-content">
                                <strong><?php echo htmlspecialchars($n['message']); ?></strong>
                                <span class="notif-time">
                                    <?php echo htmlspecialchars($n['created_at']); ?>
                                </span>
                            </div>
                            
                            <?php if ($n['type'] === 'friend_request' && $n['related_id'] && $n['fr_status'] === 'pending'): ?>
                                <div class="notif-actions">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="notif_action" value="accept">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $n['related_id']; ?>">
                                        <button type="submit" class="action-btn-small confirm-btn">Confirm</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="notif_action" value="reject">
                                        <input type="hidden" name="request_id" value="<?php echo (int) $n['related_id']; ?>">
                                        <button type="submit" class="action-btn-small deny-btn">Deny</button>
                                    </form>
                                </div>
                            <?php elseif ($n['type'] === 'friend_request' && $n['fr_status']): ?>
                                <span class="status-badge"><?php echo ucfirst(htmlspecialchars($n['fr_status'])); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const emptyState = document.querySelector('.empty-state');
            const notifCard = document.querySelector('.notif-card');
            let lastNotifId = 0;

            function updateLastId() {
                const items = document.querySelectorAll('.notif-item[data-id]');
                if (items.length > 0) {
                    lastNotifId = Math.max(...Array.from(items).map(i => parseInt(i.dataset.id)));
                }
            }

            updateLastId();

            async function fetchNewNotifications() {
                try {
                    const response = await fetch(`../api/get_notifications.php?last_id=${lastNotifId}`);
                    const result = await response.json();

                    if (result.status === 'success' && result.data.length > 0) {
                        const emptyStateNow = document.querySelector('.empty-state');
                        if (emptyStateNow) emptyStateNow.remove();
                        
                        let list = document.querySelector('.notif-list');
                        if (!list) {
                            list = document.createElement('div');
                            list.className = 'notif-list';
                            notifCard.appendChild(list);
                        }

                        result.data.forEach(notif => {
                            appendNotification(notif, list);
                        });
                        updateLastId();
                    }
                } catch (error) {
                    console.error('Error fetching notifications:', error);
                }
            }

            function appendNotification(n, list) {
                // Check if already exists
                if (document.querySelector(`.notif-item[data-id="${n.id}"]`)) return;

                const item = document.createElement('div');
                item.className = 'notif-item';
                item.dataset.id = n.id;

                let actionHtml = '';
                if (n.type === 'friend_request' && n.related_id && n.fr_status === 'pending') {
                    actionHtml = `
                        <div class="notif-actions">
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="notif_action" value="accept">
                                <input type="hidden" name="request_id" value="${n.related_id}">
                                <button type="submit" class="action-btn-small confirm-btn">Confirm</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="notif_action" value="reject">
                                <input type="hidden" name="request_id" value="${n.related_id}">
                                <button type="submit" class="action-btn-small deny-btn">Deny</button>
                            </form>
                        </div>`;
                } else if (n.type === 'friend_request' && n.fr_status) {
                    actionHtml = `<span class="status-badge">${n.fr_status.charAt(0).toUpperCase() + n.fr_status.slice(1)}</span>`;
                }

                item.innerHTML = `
                    <div class="notif-content">
                        <strong>${n.message}</strong>
                        <span class="notif-time">${n.created_at}</span>
                    </div>
                    ${actionHtml}
                `;

                list.insertBefore(item, list.firstChild);
            }

            setInterval(fetchNewNotifications, 5000);
        });
    </script>
    <script src="../assets/realtime_notifications.js"></script>
</body>
</html>
