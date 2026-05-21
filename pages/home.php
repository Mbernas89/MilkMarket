<?php
/**
 * Home Page - Main Social Feed
 * This page displays the user's feed, including original posts, shared posts, 
 * product listings, and weather updates based on user location.
 */
session_start();
require_once "../config/db.php";
require_once "../config/openweather_helpers.php";

// Redirect to login if user is not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Fetch the count of unread messages for the notification badge
$unreadMsgCount = 0;
$unreadSql = "SELECT COUNT(id) as count FROM messages WHERE receiver_id = ? AND is_read = 0";
$unreadStmt = mysqli_prepare($conn, $unreadSql);
mysqli_stmt_bind_param($unreadStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($unreadStmt);
$unreadRes = mysqli_stmt_get_result($unreadStmt);
if ($unreadRes !== false && $unreadRow = mysqli_fetch_assoc($unreadRes)) {
    $unreadMsgCount = (int) $unreadRow['count'];
}

// Fetch the count of unread notifications (likes, friend requests, etc.)
$unreadNotifCount = 0;
$notifSql = "SELECT COUNT(id) as count FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'message'";
$notifStmt = mysqli_prepare($conn, $notifSql);
mysqli_stmt_bind_param($notifStmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($notifStmt);
$notifRes = mysqli_stmt_get_result($notifStmt);
if ($notifRes !== false && $notifRow = mysqli_fetch_assoc($notifRes)) {
    $unreadNotifCount = (int) $notifRow['count'];
}

// Fetch the latest location from the current user's posts to provide personalized weather for the sidebar.
// Fall back to the most recent location posted by anyone if the user has no location posts.
$weatherLocation = "";
$locSql = "SELECT location_text FROM posts WHERE user_id = ? AND location_text IS NOT NULL AND TRIM(location_text) <> '' ORDER BY created_at DESC LIMIT 1";
$locStmt = mysqli_prepare($conn, $locSql);
if ($locStmt !== false) {
    mysqli_stmt_bind_param($locStmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($locStmt);
    $locRes = mysqli_stmt_get_result($locStmt);
    if ($locRes !== false && $locRow = mysqli_fetch_assoc($locRes)) {
        $weatherLocation = trim((string) ($locRow['location_text'] ?? ''));
    }
}

if ($weatherLocation === "") {
    // fallback to the latest location posted by anyone
    $fallbackSql = "SELECT location_text FROM posts WHERE location_text IS NOT NULL AND TRIM(location_text) <> '' ORDER BY created_at DESC LIMIT 1";
    $fallbackRes = mysqli_query($conn, $fallbackSql);
    if ($fallbackRes !== false && $fbRow = mysqli_fetch_assoc($fallbackRes)) {
        $weatherLocation = trim((string) ($fbRow['location_text'] ?? ''));
    }
}

// Fetch current weather using the helper function
$currentWeather = $weatherLocation !== '' ? openweather_fetch_current($weatherLocation) : null;

// Main Feed Query: Fetch all posts, shared content details, and engagement counts (likes, comments, shares)
$feedItems = [];
$sql = "
    SELECT
        posts.id,
        posts.user_id,
        posts.content,
        posts.image_path,
        posts.product_name,
        posts.category,
        posts.price,
        posts.quantity_available,
        posts.location_text,
        posts.contact_number,
        posts.created_at,
        posts.shared_post_id,
        users.username,
        users.profile_image,
        shared_users.username AS shared_username,
        shared_users.id AS shared_user_id,
        shared_users.profile_image AS shared_profile_image,
        shared_posts.content AS shared_content,
        shared_posts.image_path AS shared_image_path,
        shared_posts.product_name AS shared_product_name,
        shared_posts.category AS shared_category,
        shared_posts.price AS shared_price,
        shared_posts.quantity_available AS shared_quantity_available,
        shared_posts.location_text AS shared_location_text,
        shared_posts.contact_number AS shared_contact_number,
        shared_posts.created_at AS shared_created_at,
        (SELECT COUNT(*) FROM post_likes WHERE post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) AS comment_count,
        (SELECT COUNT(*) FROM posts child_posts WHERE child_posts.shared_post_id = posts.id) AS share_count,
        CASE WHEN EXISTS (
            SELECT 1 FROM post_likes WHERE post_id = posts.id AND user_id = ?
        ) THEN 1 ELSE 0 END AS user_liked
    FROM posts
    INNER JOIN users ON posts.user_id = users.id
    LEFT JOIN posts AS shared_posts ON posts.shared_post_id = shared_posts.id
    LEFT JOIN users AS shared_users ON shared_posts.user_id = shared_users.id
    ORDER BY posts.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result !== false) {
    // Iterate through each post in the feed
    while ($row = mysqli_fetch_assoc($result)) {
        // For each post, fetch the top 3 most recent comments
        $commentsSql = "SELECT comments.id, comments.user_id, comments.content, comments.created_at, users.username, users.profile_image
             FROM comments
             INNER JOIN users ON comments.user_id = users.id
             WHERE comments.post_id = ?
             ORDER BY comments.created_at ASC
             LIMIT 3";
        $commentsStmt = mysqli_prepare($conn, $commentsSql);
        mysqli_stmt_bind_param($commentsStmt, "i", $row["id"]);
        mysqli_stmt_execute($commentsStmt);
        $commentsRes = mysqli_stmt_get_result($commentsStmt);

        $comments = [];
        if ($commentsRes !== false) {
            while ($cRow = mysqli_fetch_assoc($commentsRes)) {
                $comments[] = $cRow;
            }
        }

        $row["comments"] = $comments;
        $feedItems[] = $row;
    }
}

$postError = $_SESSION["post_error"] ?? "";
unset($_SESSION["post_error"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milk Market - Feed</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<?php
// Calculate the absolute latest comment ID to start polling from
$maxCSql = "SELECT MAX(id) as max_id FROM comments";
$maxCRes = mysqli_query($conn, $maxCSql);
$maxCommentId = ($maxCRes && $maxCRow = mysqli_fetch_assoc($maxCRes)) ? (int)$maxCRow['max_id'] : 0;
?>
<body class="feed-body" data-user-id="<?php echo (int)$_SESSION['user_id']; ?>" data-last-comment-id="<?php echo $maxCommentId; ?>">
    <header class="taskbar">
        <div class="taskbar-inner">
            <a class="taskbar-brand taskbar-home-link" href="home.php" aria-label="Go to Milk Market homepage">
                <img class="taskbar-logo-image" src="../assets/milk-market-logo.png" alt="Milk Market logo">
                <div>
                    <strong>Milk Market</strong>
                    <p>Community Feed</p>
                </div>
            </a>

            <form class="taskbar-search" method="get" action="search_users.php">
                <input type="search" name="q" placeholder="Search users...">
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
                    <a href="notifications.php">Notifications <span id="nav-notif-count" class="red-dot" <?php if ($unreadNotifCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadNotifCount; ?></span></a>
                    <a href="friends.php">Find Friends</a>
                    <a href="messages.php">Messages <span id="nav-msg-count" class="red-dot" <?php if ($unreadMsgCount <= 0): ?>style="display:none;"<?php endif; ?>><?php echo $unreadMsgCount; ?></span></a>
                    <a href="settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <div class="feed-shell with-taskbar">
        <aside class="feed-sidebar left-sidebar">
            <div class="sidebar-card brand-card">
                <h2>Milk Market</h2>
                <p>A social space for sellers and dairy enthusiasts.</p>
            </div>
            <div class="sidebar-card profile-card">
                <?php if (!empty($_SESSION["profile_image"])): ?>
                    <img class="avatar-circle avatar-image" src="<?php echo htmlspecialchars($_SESSION["profile_image"]); ?>" alt="Your profile picture">
                <?php else: ?>
                    <div class="avatar-circle"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                <?php endif; ?>
                <h3><?php echo htmlspecialchars($_SESSION["username"]); ?></h3>
                <p class="muted">Share milk products, offers, and dairy tips.</p>
                <a class="sidebar-link" href="settings.php">Edit profile</a>
            </div>
        </aside>

        <main class="feed-main">
            <?php if ($postError !== ""): ?>
                <p class="message error settings-error"><?php echo htmlspecialchars($postError); ?></p>
            <?php endif; ?>

            <?php if ($currentWeather): ?>
                <section class="weather-card">
                    <div class="weather-card-top">
                        <div>
                            <span class="weather-chip">OpenWeather Live</span>
                            <h3>Current weather near <?php echo htmlspecialchars($currentWeather['location_name'] ?: $weatherLocation); ?></h3>
                            <p class="muted weather-copy">Helpful for delivery timing, storage planning, and fresh milk handling.</p>
                        </div>
                        <div class="weather-temp-block">
                            <strong><?php echo htmlspecialchars((string) $currentWeather['temperature']); ?>&deg;C</strong>
                            <span><?php echo htmlspecialchars(ucwords($currentWeather['description'] ?: $currentWeather['condition'])); ?></span>
                        </div>
                    </div>
                    <div class="weather-metrics-grid">
                        <span><strong>Feels like:</strong> <?php echo htmlspecialchars((string) $currentWeather['feels_like']); ?>&deg;C</span>
                        <span><strong>Humidity:</strong> <?php echo htmlspecialchars((string) $currentWeather['humidity']); ?>%</span>
                        <span><strong>Wind:</strong> <?php echo htmlspecialchars((string) $currentWeather['wind_speed']); ?> m/s</span>
                    </div>
                </section>
            <?php endif; ?>

            <section class="composer-box">
                <div class="composer-top">
                    <?php if (!empty($_SESSION["profile_image"])): ?>
                        <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($_SESSION["profile_image"]); ?>" alt="Your profile picture">
                    <?php else: ?>
                        <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                    <?php endif; ?>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION["username"]); ?></strong>
                        <p class="muted small-copy" data-composer-helper>Create a marketplace-style milk post with real product details.</p>
                    </div>
                </div>

                <form method="post" action="create_post.php" class="composer-form" enctype="multipart/form-data">
                    <div class="composer-mode-tabs" role="radiogroup" aria-label="Post type">
                        <label class="composer-mode-option">
                            <input type="radio" name="composer_mode" value="post" data-composer-mode>
                            <span>Post</span>
                        </label>
                        <label class="composer-mode-option">
                            <input type="radio" name="composer_mode" value="product" data-composer-mode checked>
                            <span>Product</span>
                        </label>
                    </div>

                    <div class="product-grid" data-product-fields>
                        <div>
                            <label for="product_name">Product Name</label>
                            <input type="text" id="product_name" name="product_name" placeholder="Fresh Carabao Milk">
                        </div>
                        <div>
                            <label for="category">Category</label>
                            <select id="category" name="category">
                                <option value="">Select category</option>
                                <option value="Fresh Milk">Fresh Milk</option>
                                <option value="Flavored Milk">Flavored Milk</option>
                                <option value="Yogurt">Yogurt</option>
                                <option value="Cheese">Cheese</option>
                                <option value="Butter">Butter</option>
                                <option value="Dairy Tips">Dairy Tips</option>
                            </select>
                        </div>
                        <div>
                            <label for="price">Price</label>
                            <div class="input-prefix-wrapper">
                            <span class="prefix">₱</span>
                            <input type="text" id="price" name="price" placeholder="120 per liter">
                        </div>
                        </div>
                        <div>
                            <label for="quantity_available">Quantity</label>
                            <input type="text" id="quantity_available" name="quantity_available" placeholder="20 bottles available">
                        </div>
                        <div>
                            <label for="location_text">Location</label>
                            <input type="text" id="location_text" name="location_text" placeholder="Quezon City">
                        </div>
                        <div>
                            <label for="contact_number">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" placeholder="09XXXXXXXXX">
                        </div>
                    </div>

                    <label for="content" data-content-label>Description or update</label>
                    <textarea id="content" name="content" rows="4" placeholder="Tell buyers what makes this product special, when it is available, or how to order."></textarea>
                    <div class="composer-actions-row compact-actions-row">
                        <div class="photo-upload-control">
                            <input type="file" id="post_image" name="post_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
                            <label for="post_image" class="upload-icon-button" title="Add photo" aria-label="Add photo">
                                <span class="upload-icon">+</span>
                                <span class="upload-text">Photo</span>
                            </label>
                            <span class="selected-file-indicator" id="composer_selected_file_name"></span>
                        </div>
                        <button type="button" class="primary-button" data-preview-trigger data-preview-state="preview">Post Product</button>
                    </div>

                    <div class="preview-modal-shell" data-composer-preview hidden>
                        <div class="preview-modal-backdrop" data-preview-close></div>
                        <div class="preview-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="post-preview-title">
                            <div class="preview-modal-header">
                                <div>
                                    <h4 id="post-preview-title">Post Preview</h4>
                                    <p class="muted preview-helper-copy">Review your post before publishing it to the feed.</p>
                                </div>
                                <button type="button" class="preview-close-button" data-preview-close aria-label="Close preview">&times;</button>
                            </div>

                            <div class="composer-preview-card">
                                <div class="post-identity">
                                    <?php if (!empty($_SESSION["profile_image"])): ?>
                                        <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($_SESSION["profile_image"]); ?>" alt="Your profile picture">
                                    <?php else: ?>
                                        <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <h3><?php echo htmlspecialchars($_SESSION["username"]); ?></h3>
                                        <p class="muted small-copy">Previewing your milk market post</p>
                                    </div>
                                </div>

                                <div class="product-card-box" data-preview-product-card>
                                    <div class="product-card-head">
                                        <div>
                                            <span class="product-category-badge" data-preview-category hidden></span>
                                            <h4 class="preview-product-title" data-preview-product-name>Dairy Product</h4>
                                        </div>
                                        <span class="product-price-tag" data-preview-price hidden></span>
                                    </div>
                                    <div class="preview-meta-line">
                                        <span data-preview-quantity hidden></span>
                                        <span data-preview-location hidden></span>
                                        <span data-preview-contact hidden></span>
                                    </div>
                                </div>

                                <p class="preview-content-copy" data-preview-content>Your description or update will appear here.</p>

                                <div class="preview-media-wrap" data-preview-image-wrap hidden>
                                    <div class="preview-media-card">
                                        <img class="preview-media-image" data-preview-image alt="Selected post image preview">
                                    </div>
                                    <p class="preview-file-name" data-preview-file-name></p>
                                </div>

                                <p class="preview-empty-state" data-preview-empty hidden>Add a caption, product details, or a photo to build your preview.</p>
                            </div>

                            <div class="preview-modal-actions">
                                <button type="button" class="back-link preview-secondary-button" data-preview-close>Edit Post</button>
                                <button type="button" class="primary-button preview-confirm-button" data-preview-confirm>Confirm Post</button>
                            </div>
                        </div>
                    </div>
                </form>
            </section>

            <section class="feed-list">
                <?php if (count($feedItems) === 0): ?>
                    <div class="social-post empty-feed">
                        <h3>No posts yet</h3>
                        <p class="muted">Start the community by sharing the first milk update.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($feedItems as $item): ?>
                        <article class="social-post" id="post-<?php echo (int) $item["id"]; ?>" data-post-id="<?php echo (int) $item["id"]; ?>">
                            <div class="post-top">
                                <a href="profile.php?user_id=<?php echo (int) $item['user_id']; ?>" class="post-identity" style="text-decoration:none; color:inherit;">
                                    <?php if (!empty($item["profile_image"])): ?>
                                        <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($item["profile_image"]); ?>" alt="<?php echo htmlspecialchars($item["username"]); ?> profile picture">
                                    <?php else: ?>
                                        <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($item["username"], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <h3><?php echo htmlspecialchars($item["username"]); ?></h3>
                                        <p class="muted small-copy">
                                            <?php echo htmlspecialchars($item["created_at"]); ?>
                                        </p>
                                    </div>
                                </a>

                                <?php if ((int) $item["user_id"] === (int) $_SESSION["user_id"]): ?>
                                    <details class="post-menu">
                                        <summary class="menu-button" aria-label="Post actions">&#8226;&#8226;&#8226;</summary>
                                        <div class="menu-panel">
                                            <a class="menu-link" href="edit_post.php?post_id=<?php echo (int) $item["id"]; ?>">Edit</a>
                                            <form method="post" action="delete_post.php" class="menu-form">
                                                <input type="hidden" name="post_id" value="<?php echo (int) $item["id"]; ?>">
                                                <button type="submit" class="menu-delete">Delete</button>
                                            </form>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($item["shared_post_id"])): ?>
                                <p class="shared-label">Shared a post from <?php echo htmlspecialchars($item["shared_username"] ?? "another seller"); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($item["product_name"]) || !empty($item["category"]) || !empty($item["price"]) || !empty($item["quantity_available"]) || !empty($item["location_text"]) || !empty($item["contact_number"])): ?>
                                <div class="product-card-box">
                                    <div class="product-card-head">
                                        <div>
                                            <?php if (!empty($item["category"])): ?>
                                                <span class="product-category-badge"><?php echo htmlspecialchars($item["category"]); ?></span>
                                            <?php endif; ?>
                                            <h4><?php echo htmlspecialchars($item["product_name"] ?: 'Dairy Product'); ?></h4>
                                        </div>
                                        <?php if (!empty($item["price"])): ?>
                                            <span class="product-price-tag">₱<?php echo htmlspecialchars($item["price"]); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-meta-grid">
                                        <?php if (!empty($item["quantity_available"])): ?><span><strong>Quantity:</strong> <?php echo htmlspecialchars($item["quantity_available"]); ?></span><?php endif; ?>
                                        <?php if (!empty($item["location_text"])): ?><span><strong>Location:</strong> <?php echo htmlspecialchars($item["location_text"]); ?></span><?php endif; ?>
                                        <?php if (!empty($item["contact_number"])): ?><span><strong>Contact:</strong> <?php echo htmlspecialchars($item["contact_number"]); ?></span><?php endif; ?>
                                    </div>
                                    <?php if ((int) $item['user_id'] !== (int) $_SESSION['user_id']): ?>
                                        <div class="product-card-actions">
                                            <a class="message-seller-button" href="messages.php?recipient_id=<?php echo (int) $item['user_id']; ?>&post_id=<?php echo (int) $item['id']; ?>">Message Seller</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($item["content"] !== ""): ?>
                                <p class="post-content"><?php echo nl2br(htmlspecialchars($item["content"])); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($item["image_path"])): ?>
                                <img class="post-image" src="<?php echo htmlspecialchars($item["image_path"]); ?>" alt="Post image">
                            <?php endif; ?>

                            <?php if (!empty($item["shared_post_id"])): ?>
                                <div class="shared-post-box profile-preview-trigger" data-user-id="<?php echo (int) $item["shared_user_id"]; ?>" style="cursor:pointer;">
                                    <strong><?php echo htmlspecialchars($item["shared_username"] ?? "Original Post"); ?></strong>
                                    <?php if (!empty($item["shared_category"])): ?><span class="product-category-badge inline-badge"><?php echo htmlspecialchars($item["shared_category"]); ?></span><?php endif; ?>
                                    <?php if (!empty($item["shared_product_name"])): ?><h4><?php echo htmlspecialchars($item["shared_product_name"]); ?></h4><?php endif; ?>
                                    <?php if (!empty($item["shared_price"])): ?><p><strong>Price:</strong> ₱<?php echo htmlspecialchars($item["shared_price"]); ?></p><?php endif; ?>
                                    <?php if (!empty($item["shared_quantity_available"])): ?><p><strong>Quantity:</strong> <?php echo htmlspecialchars($item["shared_quantity_available"]); ?></p><?php endif; ?>
                                    <?php if (!empty($item["shared_location_text"])): ?><p><strong>Location:</strong> <?php echo htmlspecialchars($item["shared_location_text"]); ?></p><?php endif; ?>
                                    <?php if (!empty($item["shared_contact_number"])): ?><p><strong>Contact:</strong> <?php echo htmlspecialchars($item["shared_contact_number"]); ?></p><?php endif; ?>
                                    <?php if (!empty($item["shared_content"])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($item["shared_content"])); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item["shared_image_path"])): ?>
                                        <img class="shared-post-image" src="<?php echo htmlspecialchars($item["shared_image_path"]); ?>" alt="Shared post image">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="post-metrics">
                                <span class="metric-like"><?php echo (int) $item["like_count"]; ?> like<?php echo (int) $item["like_count"] === 1 ? "" : "s"; ?></span>
                                <span class="metric-comment"><?php echo (int) $item["comment_count"]; ?> comment<?php echo (int) $item["comment_count"] === 1 ? "" : "s"; ?></span>
                                <span class="metric-share"><?php echo (int) $item["share_count"]; ?> share<?php echo (int) $item["share_count"] === 1 ? "" : "s"; ?></span>
                            </div>

                            <div class="post-action-bar">
                                <form method="post" action="toggle_like.php" class="action-form third-width js-async-form" data-action-type="like">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $item["id"]; ?>">
                                    <button type="submit" class="action-button <?php echo (int) $item["user_liked"] === 1 ? "active-action" : ""; ?>">Like</button>
                                </form>
                                <a href="#comment-<?php echo (int) $item["id"]; ?>" class="action-link comment-link third-width">Comment</a>
                                <form method="post" action="share_post.php" class="action-form third-width js-async-form" data-action-type="share">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $item["id"]; ?>">
                                    <button type="submit" class="action-button">Share</button>
                                </form>
                            </div>

                            <div class="comment-list">
                                <?php foreach ($item["comments"] as $comment): ?>
                                    <div class="comment-row">
                                        <div class="profile-preview-trigger" data-user-id="<?php echo (int) $comment["user_id"]; ?>" style="cursor:pointer; display:flex; gap:12px; align-items:flex-start; flex:1;">
                                            <?php if (!empty($comment["profile_image"])): ?>
                                                <img class="avatar-circle comment-avatar avatar-image" src="<?php echo htmlspecialchars($comment["profile_image"]); ?>" alt="<?php echo htmlspecialchars($comment["username"]); ?> profile picture">
                                            <?php else: ?>
                                                <div class="avatar-circle comment-avatar"><?php echo strtoupper(substr($comment["username"], 0, 1)); ?></div>
                                            <?php endif; ?>
                                            <div class="comment-bubble">
                                                <strong><?php echo htmlspecialchars($comment["username"]); ?></strong>
                                                <p><?php echo nl2br(htmlspecialchars($comment["content"])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <form method="post" action="add_comment.php" class="comment-form js-async-form" data-action-type="comment" id="comment-<?php echo (int) $item["id"]; ?>">
                                <input type="hidden" name="post_id" value="<?php echo (int) $item["id"]; ?>">
                                <input type="text" name="content" placeholder="Write a comment...">
                                <button type="submit" class="comment-submit">Comment</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>

        <aside class="feed-sidebar right-sidebar">
            <div class="sidebar-card weather-side-card">
                <h3>Weather Watch</h3>
                <?php if ($currentWeather): ?>
                    <p class="muted">Currently tracking <strong><?php echo htmlspecialchars($currentWeather['location_name'] ?: $weatherLocation); ?></strong></p>
                    <div class="weather-side-temp"><?php echo htmlspecialchars((string) $currentWeather['temperature']); ?>&deg;C</div>
                    <p class="weather-side-status"><?php echo htmlspecialchars(ucwords($currentWeather['description'] ?: $currentWeather['condition'])); ?></p>
                    <ul class="tip-list weather-tip-list">
                        <li>Feels like <?php echo htmlspecialchars((string) $currentWeather['feels_like']); ?>&deg;C</li>
                        <li>Humidity <?php echo htmlspecialchars((string) $currentWeather['humidity']); ?>%</li>
                        <li>Wind <?php echo htmlspecialchars((string) $currentWeather['wind_speed']); ?> m/s</li>
                    </ul>
                <?php else: ?>
                    <p class="muted">Add a post location like Quezon City to start showing live weather.</p>
                <?php endif; ?>
            </div>

            <div class="sidebar-card tip-card">
                <h3>Posting ideas</h3>
                <ul class="tip-list">
                    <li>Post milk availability and prices</li>
                    <li>List yogurt or cheese bundles</li>
                    <li>Share delivery areas and schedules</li>
                    <li>Offer dairy recipes or tips</li>
                </ul>
            </div>
        </aside>
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
    <script src="../assets/composer-preview.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/realtime_feed.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/realtime_notifications.js?v=<?php echo time(); ?>"></script>
</body>
</html>
