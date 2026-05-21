<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$hasAttachmentPathColumn = true; // Columns exist in MySQL schema
$hasIsReadColumn = true;
$recipientId = (int) ($_GET['recipient_id'] ?? 0);

if ($recipientId > 0) {
    $updSql = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?";
    $updStmt = mysqli_prepare($conn, $updSql);
    mysqli_stmt_bind_param($updStmt, "ii", $recipientId, $_SESSION['user_id']);
    mysqli_stmt_execute($updStmt);
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
$postId = (int) ($_GET['post_id'] ?? 0);
$message = '';
$error = '';

// Fetch all users who have a conversation history with the current user
$convSql = "
    SELECT 
        u.id, 
        u.username, 
        u.profile_image,
        (SELECT message_text FROM messages 
         WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) 
         ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(id) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u
    WHERE u.id IN (
        SELECT sender_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id FROM messages WHERE sender_id = ?
    )
";
$convStmt = mysqli_prepare($conn, $convSql);
// Bind current user ID to all placeholders (?) in the query
mysqli_stmt_bind_param($convStmt, "iiiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
mysqli_stmt_execute($convStmt);
$convRes = mysqli_stmt_get_result($convStmt);

$conversationUsers = [];
if ($convRes !== false) {
    while ($row = mysqli_fetch_assoc($convRes)) {
        $conversationUsers[] = $row;
    }
}

$recipient = null;
if ($recipientId > 0) {
    $recSql = "SELECT id, username, profile_image FROM users WHERE id = ?";
    $recStmt = mysqli_prepare($conn, $recSql);
    mysqli_stmt_bind_param($recStmt, "i", $recipientId);
    mysqli_stmt_execute($recStmt);
    $recRes = mysqli_stmt_get_result($recStmt);
    $recipient = ($recRes !== false) ? mysqli_fetch_assoc($recRes) : null;
    if (!$recipient || $recipient['id'] == $_SESSION['user_id']) {
        $recipient = null;
        $recipientId = 0;
    }
}

$contextPost = null;
if ($recipient && $postId > 0) {
    $ctxSql = "SELECT id, product_name, price, category FROM posts WHERE id = ? AND user_id = ?";
    $ctxStmt = mysqli_prepare($conn, $ctxSql);
    mysqli_stmt_bind_param($ctxStmt, "ii", $postId, $recipientId);
    mysqli_stmt_execute($ctxStmt);
    $ctxRes = mysqli_stmt_get_result($ctxStmt);
    $contextPost = ($ctxRes !== false) ? mysqli_fetch_assoc($ctxRes) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int) ($_POST['recipient_id'] ?? 0);
    $postId = (int) ($_POST['post_id'] ?? 0);
    $messageText = trim($_POST['message_text'] ?? '');

    $recCheckSql = "SELECT id, username, profile_image FROM users WHERE id = ?";
    $recCheckStmt = mysqli_prepare($conn, $recCheckSql);
    mysqli_stmt_bind_param($recCheckStmt, "i", $recipientId);
    mysqli_stmt_execute($recCheckStmt);
    $recCheckRes = mysqli_stmt_get_result($recCheckStmt);
    $recipient = ($recCheckRes !== false) ? mysqli_fetch_assoc($recCheckRes) : null;

    $attachmentPath = null;
    $attachmentUpload = $_FILES['attachment'] ?? null;
    $hasAttachment = $attachmentUpload && $attachmentUpload['error'] !== UPLOAD_ERR_NO_FILE;
    $allowedAttachmentTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    if (!$recipient || $recipient['id'] == $_SESSION['user_id']) {
        $error = 'Choose a valid user to message.';
    } elseif ($messageText === '' && !$hasAttachment) {
        $error = 'Message cannot be empty.';
    } elseif ($hasAttachment && $attachmentUpload['error'] !== UPLOAD_ERR_OK) {
        $error = 'Could not upload the selected attachment.';
    } elseif ($hasAttachment && $attachmentUpload['size'] > 5 * 1024 * 1024) {
        $error = 'Attachment must be 5 MB or smaller.';
    } else {
        if ($hasAttachment) {
            $extension = strtolower(pathinfo($attachmentUpload['name'], PATHINFO_EXTENSION));
            $detectedMime = mime_content_type($attachmentUpload['tmp_name']) ?: '';

            if (!isset($allowedAttachmentTypes[$extension]) || !in_array($detectedMime, $allowedAttachmentTypes[$extension], true)) {
                $error = 'Attachment must be a jpg, png, gif, webp, pdf, doc, docx, zip, or txt file.';
            } else {
                $uploadDir = '../uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $filename = uniqid('msg_', true) . '.' . $extension;
                $destPath = $uploadDir . $filename;

                if (move_uploaded_file($attachmentUpload['tmp_name'], $destPath)) {
                    $attachmentPath = $destPath;
                } else {
                    $error = 'Could not save the selected attachment.';
                }
            }
        }

        $validPostId = null;
        if ($postId > 0) {
            $postCheckSql = "SELECT id, product_name, price, category FROM posts WHERE id = ?";
            $postCheckStmt = mysqli_prepare($conn, $postCheckSql);
            mysqli_stmt_bind_param($postCheckStmt, "i", $postId);
            mysqli_stmt_execute($postCheckStmt);
            $postCheckRes = mysqli_stmt_get_result($postCheckStmt);
            $contextPost = ($postCheckRes !== false) ? mysqli_fetch_assoc($postCheckRes) : null;
            $validPostId = $contextPost ? $postId : null;
        }

        if ($error === '') {
            $messageTextForDb = $messageText !== '' ? $messageText : ' ';

            $insSql = "INSERT INTO messages (sender_id, receiver_id, post_id, message_text, attachment_path) VALUES (?, ?, ?, ?, ?)";
            $insStmt = mysqli_prepare($conn, $insSql);
            mysqli_stmt_bind_param($insStmt, "iiiss", $_SESSION['user_id'], $recipientId, $validPostId, $messageTextForDb, $attachmentPath);
            $insertSuccess = mysqli_stmt_execute($insStmt);

            if (!$insertSuccess) {
                $error = 'Could not send your message right now.';
            } else {
                $ntMsg = "You received a new message from " . $_SESSION['username'] . ".";
                $notifSql = "INSERT INTO notifications (user_id, type, message) VALUES (?, 'message', ?)";
                $notifStmt = mysqli_prepare($conn, $notifSql);
                mysqli_stmt_bind_param($notifStmt, "is", $recipientId, $ntMsg);
                mysqli_stmt_execute($notifStmt);

                header('Location: messages.php?recipient_id=' . $recipientId . ($validPostId ? '&post_id=' . $validPostId : ''));
                exit;
            }
        }
    }
}

$messages = [];
if ($recipient) {
    $messagesSql = "
        SELECT
            messages.id,
            messages.sender_id,
            messages.receiver_id,
            messages.post_id,
            messages.message_text,
            messages.attachment_path,
            messages.created_at,
            sender.username AS sender_username,
            sender.profile_image AS sender_profile_image,
            post_ref.product_name AS post_product_name,
            post_ref.price AS post_price
        FROM messages
        INNER JOIN users sender ON messages.sender_id = sender.id
        LEFT JOIN posts post_ref ON messages.post_id = post_ref.id
        WHERE (messages.sender_id = ? AND messages.receiver_id = ?)
           OR (messages.sender_id = ? AND messages.receiver_id = ?)
        ORDER BY messages.created_at ASC
    ";
    $messagesStmt = mysqli_prepare($conn, $messagesSql);
    mysqli_stmt_bind_param($messagesStmt, "iiii", $_SESSION['user_id'], $recipient['id'], $recipient['id'], $_SESSION['user_id']);
    mysqli_stmt_execute($messagesStmt);
    $messagesRes = mysqli_stmt_get_result($messagesStmt);
    if ($messagesRes !== false) {
        while ($row = mysqli_fetch_assoc($messagesRes)) {
            $messages[] = $row;
        }
    }

    $existsInList = false;
    foreach ($conversationUsers as $userRow) {
        if ((int) $userRow['id'] === (int) $recipient['id']) {
            $existsInList = true;
            break;
        }
    }
    if (!$existsInList) {
        array_unshift($conversationUsers, $recipient);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?php echo time(); ?>">
</head>
<body class="messages-page">
    <header class="taskbar">
        <div class="taskbar-inner">
            <a class="taskbar-brand taskbar-home-link" href="home.php" aria-label="Go to Milk Market homepage">
                <img class="taskbar-logo-image" src="../assets/milk-market-logo.png" alt="Milk Market logo">
                <div>
                    <strong>All About Milk</strong>
                    <p>Messages</p>
                </div>
            </a>
            <form class="taskbar-search" method="get" action="search_users.php">
                <input type="search" name="q" placeholder="Search users...">
                <button type="submit">Search</button>
            </form>
            <details class="taskbar-menu">
                <summary class="taskbar-trigger account-trigger" aria-label="Open account menu">
                    <?php if (!empty($_SESSION['profile_image'])): ?>
                        <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Your profile picture">
                    <?php else: ?>
                        <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                    <?php endif; ?>
                    <?php if (($unreadNotifCount + $unreadMsgCount) > 0): ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity"></span>
                    <?php else: ?>
                        <span id="global-nav-unread-dot" class="account-alert-dot" aria-label="Unread activity" style="display:none;"></span>
                    <?php endif; ?>
                    <span class="taskbar-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
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

    <div class="messages-shell">
        <aside class="messages-sidebar">
            <div class="messages-sidebar-card">
                <h2>Conversations</h2>
                <?php if (count($conversationUsers) === 0): ?>
                    <p class="muted">No conversations yet. Message a seller from a product post.</p>
                <?php else: ?>
                    <div class="conversation-list">
                        <?php foreach ($conversationUsers as $userRow): ?>
                            <a class="conversation-item <?php echo ($recipient && (int) $recipient['id'] === (int) $userRow['id']) ? 'active-conversation' : ''; ?>" href="messages.php?recipient_id=<?php echo (int) $userRow['id']; ?>">
                                <?php if (!empty($userRow['profile_image'])): ?>
                                    <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($userRow['profile_image']); ?>" alt="<?php echo htmlspecialchars($userRow['username']); ?> profile picture">
                                <?php else: ?>
                                    <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($userRow['username'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <div>
                                    <strong style="display:inline-block;"><?php echo htmlspecialchars($userRow['username']); ?></strong>
                                    <?php if (!empty($userRow['unread_count']) && (int)$userRow['unread_count'] > 0): ?>
                                        <span class="red-dot"><?php echo (int)$userRow['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <main class="messages-main">
            <div class="messages-card">
                <?php if (!$recipient): ?>
                    <div class="messages-empty">
                        <h2>Select a conversation</h2>
                        <p class="muted">Choose a user from the conversations list, search for a user, or click Message Seller on a product post.</p>
                        <a class="message-seller-button" href="friends.php">Find someone to message</a>
                    </div>
                <?php else: ?>
                    <div class="messages-header">
                        <div class="active-chat-identity profile-preview-trigger" data-user-id="<?php echo (int)$recipientId; ?>" style="cursor:pointer;">
                            <?php if (!empty($recipient['profile_image'])): ?>
                                <img class="avatar-circle small-avatar avatar-image" src="<?php echo htmlspecialchars($recipient['profile_image']); ?>" alt="Profile">
                            <?php else: ?>
                                <div class="avatar-circle small-avatar"><?php echo strtoupper(substr($recipient["username"], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div class="active-chat-info">
                                <h3><?php echo htmlspecialchars($recipient['username']); ?></h3>
                                <p class="muted">Active conversation</p>
                            </div>
                        </div>
                    </div>

                    <?php if ($contextPost): ?>
                        <div class="message-context-box">
                            <strong>About product</strong>
                            <p><?php echo htmlspecialchars($contextPost['product_name'] ?: 'Product inquiry'); ?></p>
                            <?php if (!empty($contextPost['category'])): ?><span class="product-category-badge inline-badge"><?php echo htmlspecialchars($contextPost['category']); ?></span><?php endif; ?>
                            <?php if (!empty($contextPost['price'])): ?><span class="product-price-tag"><?php echo htmlspecialchars($contextPost['price']); ?></span><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($message !== ''): ?>
                        <p class="message success"><?php echo htmlspecialchars($message); ?></p>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <p class="message error settings-error"><?php echo htmlspecialchars($error); ?></p>
                    <?php endif; ?>

                    <div class="messages-thread">
                        <?php if (count($messages) === 0): ?>
                            <p class="muted">No messages yet. Start the conversation below.</p>
                        <?php else: ?>
                            <?php foreach ($messages as $row): ?>
                                <div class="message-row <?php echo ((int) $row['sender_id'] === (int) $_SESSION['user_id']) ? 'sent-row' : 'received-row'; ?>">
                                    <div class="message-bubble-card" data-id="<?php echo (int)$row['id']; ?>">
                                        <?php if (!empty($row['post_id']) && !empty($row['post_product_name'])): ?>
                                            <div class="message-post-chip">
                                                About: <?php echo htmlspecialchars($row['post_product_name']); ?><?php if (!empty($row['post_price'])): ?> - <?php echo htmlspecialchars($row['post_price']); ?><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <p><?php echo nl2br(htmlspecialchars($row['message_text'])); ?></p>
                                        <?php if (!empty($row['attachment_path'])): ?>
                                            <?php if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $row['attachment_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($row['attachment_path']); ?>" alt="Image attachment" style="max-width:100%; border-radius:8px; margin-bottom:8px; display:block;">
                                            <?php else: ?>
                                                <div style="margin-bottom:8px; padding:10px; background:rgba(0,0,0,0.04); border-radius:8px;">
                                                    <a href="<?php echo htmlspecialchars($row['attachment_path']); ?>" target="_blank" download style="color:var(--fb-blue); font-weight:600; text-decoration:none;">Download File</a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <small class="muted"><?php echo htmlspecialchars($row['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="message-compose-form">
                        <input type="hidden" name="recipient_id" value="<?php echo (int) $recipient['id']; ?>">
                        <input type="hidden" name="post_id" value="<?php echo (int) ($contextPost['id'] ?? $postId); ?>">
                        <textarea name="message_text" rows="1" placeholder="Write your message..." style="min-height: 46px; padding-top: 10px;"></textarea>
                        
                        <div class="composer-actions-row" style="grid-column: 1 / -1; display:flex; justify-content:space-between; align-items:center;">
                            <label class="upload-icon-button">
                                <span class="upload-icon">+</span>
                                <span class="upload-text" id="attach-label-text">Attach file</span>
                                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.zip,.txt" style="display:none;" onchange="document.getElementById('attach-label-text').innerText = this.files.length > 0 ? this.files[0].name.substring(0, 20) + '...' : 'Attach file';">
                            </label>
                            <button type="submit" class="primary-button">Send</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messageThread = document.querySelector('.messages-thread');
            const composeForm = document.querySelector('.message-compose-form');
            const recipientId = <?php echo (int)($recipient['id'] ?? 0); ?>;
            const currentUserId = <?php echo (int)$_SESSION['user_id']; ?>;
            let lastMessageId = 0;

            // Find the last message ID from existing messages
            const existingMessages = messageThread.querySelectorAll('.message-bubble-card');
            // We need a way to get the actual ID from the DB. 
            // Let's adjust the PHP to include the ID in a data attribute.
            
            function scrollToBottom() {
                messageThread.scrollTop = messageThread.scrollHeight;
            }

            scrollToBottom();

            // Function to fetch new messages
            async function fetchNewMessages() {
                if (!recipientId) return;

                // Find the latest message ID currently in the DOM
                const messageBubbles = document.querySelectorAll('.message-bubble-card[data-id]');
                if (messageBubbles.length > 0) {
                    lastMessageId = Math.max(...Array.from(messageBubbles).map(b => parseInt(b.dataset.id)));
                }

                try {
                    const response = await fetch(`../api/get_messages.php?recipient_id=${recipientId}&last_id=${lastMessageId}`);
                    const result = await response.json();

                    if (result.status === 'success' && result.data.length > 0) {
                        result.data.forEach(msg => {
                            appendMessage(msg);
                        });
                        scrollToBottom();
                    }
                } catch (error) {
                    console.error('Error fetching messages:', error);
                }
            }

            function appendMessage(msg) {
                // Prevent duplicate messages in the DOM
                if (document.querySelector(`.message-bubble-card[data-id="${msg.id}"]`)) return;

                const isSent = parseInt(msg.sender_id) === currentUserId;
                const row = document.createElement('div');
                row.className = `message-row ${isSent ? 'sent-row' : 'received-row'}`;

                let attachmentHtml = '';
                if (msg.attachment_path) {
                    if (msg.attachment_path.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                        attachmentHtml = `<img src="${msg.attachment_path}" alt="Image attachment" style="max-width:100%; border-radius:8px; margin-bottom:8px; display:block;">`;
                    } else {
                        attachmentHtml = `
                            <div style="margin-bottom:8px; padding:10px; background:rgba(0,0,0,0.04); border-radius:8px;">
                                <a href="${msg.attachment_path}" target="_blank" download style="color:var(--fb-blue); font-weight:600; text-decoration:none;">Download File</a>
                            </div>`;
                    }
                }

                let postChipHtml = '';
                if (msg.post_id && msg.post_product_name) {
                    postChipHtml = `
                        <div class="message-post-chip">
                            About: ${msg.post_product_name}${msg.post_price ? ' - ' + msg.post_price : ''}
                        </div>`;
                }

                row.innerHTML = `
                    <div class="message-bubble-card" data-id="${msg.id}">
                        ${postChipHtml}
                        <p>${msg.message_text.replace(/\n/g, '<br>')}</p>
                        ${attachmentHtml}
                        <small class="muted">${msg.created_at}</small>
                    </div>
                `;
                
                // Remove the "No messages yet" placeholder if it exists
                const emptyMsg = messageThread.querySelector('.muted');
                if (emptyMsg && emptyMsg.textContent.includes('No messages yet')) {
                    emptyMsg.remove();
                }

                messageThread.appendChild(row);
            }

            // Handle AJAX form submission
            if (composeForm) {
                const submitBtn = composeForm.querySelector('button[type="submit"]');

                composeForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const messageText = formData.get('message_text').trim();
                    const hasAttachment = formData.get('attachment').size > 0;

                    if (!messageText && !hasAttachment) return;

                    // Prevent double submission
                    if (submitBtn) submitBtn.disabled = true;

                    // Clear input immediately for better UX
                    const textArea = this.querySelector('textarea');
                    const originalText = textArea.value;
                    textArea.value = '';
                    const fileInput = this.querySelector('input[type="file"]');
                    const attachLabel = document.getElementById('attach-label-text');
                    if (fileInput) fileInput.value = '';
                    if (attachLabel) attachLabel.innerText = 'Attach file';

                    try {
                        const response = await fetch('../api/send_message.php', {
                            method: 'POST',
                            body: formData
                        });
                        const text = await response.text();
                        let result;
                        try {
                            result = JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid server response');
                        }

                        if (result.status === 'success') {
                            fetchNewMessages();
                        } else {
                            textArea.value = originalText;
                            alert('Error: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Error sending message:', error);
                        textArea.value = originalText;
                        alert('Could not send message. Please try again.');
                    } finally {
                        if (submitBtn) submitBtn.disabled = false;
                    }
                });

                // Send message on Enter (but allow Shift+Enter for new line)
                const textArea = composeForm.querySelector('textarea');
                if (textArea) {
                    textArea.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            // Trigger the form submit event
                            const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
                            if (composeForm.dispatchEvent(submitEvent)) {
                                // If not prevented, call the async handler manually or just click the button
                                if (typeof composeForm.requestSubmit === 'function') {
                                    composeForm.requestSubmit();
                                } else {
                                    submitBtn.click();
                                }
                            }
                        }
                    });
                }
            }

            // Start polling
            if (recipientId) {
                setInterval(fetchNewMessages, 3000);
            }
        });
    </script>
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

