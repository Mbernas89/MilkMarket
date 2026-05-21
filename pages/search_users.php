<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$query = trim($_GET["q"] ?? "");
$users = [];
$message = "";
$error = "";
$friendRequestsReady = false;
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'friend_requests'");
$friendRequestsReady = (mysqli_num_rows($tableCheck) > 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $targetId = (int) ($_POST["target_id"] ?? 0);
    $query = trim($_POST["q"] ?? $query);

    if (!$friendRequestsReady) {
        $error = "Friend requests are not set up in the database yet.";
    } elseif ($action === "add_friend" && $targetId > 0 && $targetId !== (int) $_SESSION["user_id"]) {
        // Check if a friend request or relationship already exists to prevent duplicates
        $checkSql = "SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "iiii", $_SESSION["user_id"], $targetId, $targetId, $_SESSION["user_id"]);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);

        if ($checkRes !== false && mysqli_fetch_assoc($checkRes)) {
            $message = "You already have a friend request or friendship with that user.";
        } else {
            $insSql = "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')";
            $insStmt = mysqli_prepare($conn, $insSql);
            mysqli_stmt_bind_param($insStmt, "ii", $_SESSION["user_id"], $targetId);
            $insertSuccess = mysqli_stmt_execute($insStmt);

            if (!$insertSuccess) {
                $error = "Could not send the friend request right now.";
            } else {
                $ntMsg = $_SESSION["username"] . " wants to add you to be friends.";
                $notifSql = "INSERT INTO notifications (user_id, type, message) VALUES (?, 'friend_request', ?)";
                $notifStmt = mysqli_prepare($conn, $notifSql);
                mysqli_stmt_bind_param($notifStmt, "is", $targetId, $ntMsg);
                mysqli_stmt_execute($notifStmt);
                $message = "Friend request sent.";
            }
        }
    } elseif (($action === "accept_friend" || $action === "reject_friend") && $targetId > 0 && $targetId !== (int) $_SESSION["user_id"]) {
        $newStatus = $action === "accept_friend" ? "accepted" : "rejected";
        $updSql = "UPDATE friend_requests SET status = ? WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
        $updStmt = mysqli_prepare($conn, $updSql);
        mysqli_stmt_bind_param($updStmt, "sii", $newStatus, $targetId, $_SESSION["user_id"]);
        mysqli_stmt_execute($updStmt);

        if (mysqli_stmt_affected_rows($updStmt) < 1) {
            $error = "Could not update that friend request.";
        } else {
            if ($newStatus === "accepted") {
                $ntMsg = $_SESSION["username"] . " accepted your friend request.";
                $notifSql = "INSERT INTO notifications (user_id, type, message) VALUES (?, 'friend_accept', ?)";
                $notifStmt = mysqli_prepare($conn, $notifSql);
                mysqli_stmt_bind_param($notifStmt, "is", $targetId, $ntMsg);
                mysqli_stmt_execute($notifStmt);
                $message = "Friend request accepted.";
            } else {
                $message = "Friend request rejected.";
            }
        }
    }
}

$unreadMsgCount = 0;
$unreadSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unreadStmt = mysqli_prepare($conn, $unreadSql);
mysqli_stmt_bind_param($unreadStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($unreadStmt);
$unreadRes = mysqli_stmt_get_result($unreadStmt);
if ($unreadRes !== false && $unreadRow = mysqli_fetch_assoc($unreadRes)) {
    $unreadMsgCount = (int) $unreadRow['count'];
}

$unreadNotifCount = 0;
$notifCountSql = "SELECT COUNT(id) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'message'";
$notifCountStmt = mysqli_prepare($conn, $notifCountSql);
mysqli_stmt_bind_param($notifCountStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($notifCountStmt);
$notifCountRes = mysqli_stmt_get_result($notifCountStmt);
if ($notifCountRes !== false && $notifRow = mysqli_fetch_assoc($notifCountRes)) {
    $unreadNotifCount = (int) $notifRow['count'];
}

if ($query !== "") {
    $searchTerm = "%" . $query . "%";
    $searchSql = "SELECT id, username, email, profile_image FROM users WHERE id != ? AND (username LIKE ? OR email LIKE ?) ORDER BY username ASC LIMIT 20";
    $stmt = mysqli_prepare($conn, $searchSql);
    mysqli_stmt_bind_param($stmt, "iss", $_SESSION["user_id"], $searchTerm, $searchTerm);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result !== false) {
        while ($row = mysqli_fetch_assoc($result)) {
            $row["friend_status"] = null;
            $row["friend_sender_id"] = null;
            $row["friend_receiver_id"] = null;

            if ($friendRequestsReady) {
                $friendSql = "SELECT sender_id, receiver_id, status FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id DESC LIMIT 1";
                $fStmt = mysqli_prepare($conn, $friendSql);
                mysqli_stmt_bind_param($fStmt, "iiii", $_SESSION["user_id"], $row["id"], $row["id"], $_SESSION["user_id"]);
                mysqli_stmt_execute($fStmt);
                $fRes = mysqli_stmt_get_result($fStmt);
                $friendRow = ($fRes !== false) ? mysqli_fetch_assoc($fRes) : null;

                if ($friendRow) {
                    $row["friend_status"] = $friendRow["status"];
                    $row["friend_sender_id"] = $friendRow["sender_id"];
                    $row["friend_receiver_id"] = $friendRow["receiver_id"];
                }
            }

            $users[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Search - Milk Market</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body class="feed-body">
    <header class="taskbar">
        <div class="taskbar-inner">
            <a class="taskbar-brand taskbar-home-link" href="home.php" aria-label="Go to Milk Market homepage">
                <img class="taskbar-logo-image" src="../assets/milk-market-logo.png" alt="Milk Market logo">
                <div>
                    <strong>Milk Market</strong>
                    <p>User Search</p>
                </div>
            </a>

            <form class="taskbar-search" method="get" action="search_users.php">
                <input type="search" name="q" placeholder="Search users..." value="<?php echo htmlspecialchars($query); ?>">
                <button type="submit">Search</button>
            </form>

            <details class="taskbar-menu">
                <summary class="taskbar-trigger account-trigger" aria-label="Open account menu">
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
                    <a href="friends.php">Find Friends</a>
                    <a href="messages.php">Messages <span id="nav-msg-count" class="red-dot" <?php if ($unreadMsgCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadMsgCount; ?></span></a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <main class="search-users-shell">
        <section class="search-users-card">
            <h1>Find Users</h1>
            <?php if ($message !== ""): ?>
                <p class="message success"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
            <?php if ($error !== ""): ?>
                <p class="message error settings-error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if ($query === ""): ?>
                <p class="muted">Search by username or email to start a conversation.</p>
            <?php elseif (count($users) === 0): ?>
                <p class="muted">No users found for "<?php echo htmlspecialchars($query); ?>".</p>
            <?php else: ?>
                <div class="search-users-list">
                    <?php foreach ($users as $user): ?>
                        <article class="search-user-row">
                            <div class="post-identity profile-preview-trigger" data-user-id="<?php echo (int) $user["id"]; ?>" style="cursor:pointer;">
                                <?php if (!empty($user["profile_image"])): ?>
                                    <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($user["profile_image"]); ?>" alt="<?php echo htmlspecialchars($user["username"]); ?> profile picture">
                                <?php else: ?>
                                    <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($user["username"], 0, 1)); ?></div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($user["username"]); ?></strong>
                                    <p class="muted small-copy"><?php echo htmlspecialchars($user["email"]); ?></p>
                                </div>
                            </div>
                            <div class="search-user-actions">
                                <?php if ($friendRequestsReady && empty($user["friend_status"])): ?>
                                    <form method="post" class="inline-action-form">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                                        <input type="hidden" name="action" value="add_friend">
                                        <input type="hidden" name="target_id" value="<?php echo (int) $user["id"]; ?>">
                                        <button type="submit" class="primary-button">Add Friend</button>
                                    </form>
                                <?php elseif (($user["friend_status"] ?? "") === "accepted"): ?>
                                    <span class="relationship-badge">Friends</span>
                                <?php elseif (($user["friend_status"] ?? "") === "pending" && (int) ($user["friend_sender_id"] ?? 0) === (int) $_SESSION["user_id"]): ?>
                                    <span class="relationship-badge">Request sent</span>
                                <?php elseif (($user["friend_status"] ?? "") === "pending"): ?>
                                    <form method="post" class="inline-action-form">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                                        <input type="hidden" name="action" value="accept_friend">
                                        <input type="hidden" name="target_id" value="<?php echo (int) $user["id"]; ?>">
                                        <button type="submit" class="primary-button">Accept</button>
                                    </form>
                                    <form method="post" class="inline-action-form">
                                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                                        <input type="hidden" name="action" value="reject_friend">
                                        <input type="hidden" name="target_id" value="<?php echo (int) $user["id"]; ?>">
                                        <button type="submit" class="secondary-action-button">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span class="relationship-badge">Already added</span>
                                <?php endif; ?>
                                <a class="message-seller-button" href="messages.php?recipient_id=<?php echo (int) $user["id"]; ?>">Message</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
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
