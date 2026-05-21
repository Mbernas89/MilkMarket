<?php
/**
 * Settings Page - User Profile Management
 * Allows users to update their profile information, including username, 
 * email, password, and profile image (integrated with Cloudinary).
 */
session_start();
require_once "../config/db.php";
require_once "../config/cloudinary_helpers.php";

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Fetch current user data for the forms
$user = null;
$message = "";
$error = "";

$userStmt = mysqli_prepare($conn, "SELECT username, email, bio, profile_image FROM users WHERE id = ?");
mysqli_stmt_bind_param($userStmt, "i", $_SESSION["user_id"]);
mysqli_stmt_execute($userStmt);
$userRes = mysqli_stmt_get_result($userStmt);
if ($userRes !== false) {
    $user = mysqli_fetch_assoc($userRes);
}

if (!$user) {
    header("Location: logout.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $bio = trim($_POST["bio"] ?? "");
    $newPassword = trim($_POST["new_password"] ?? "");
    $removePhoto = isset($_POST["remove_photo"]);
    $profileImagePath = $user["profile_image"] ?? null;

    // Validate required fields
    if ($username === "" || $email === "") {
        $error = "Username and email are required.";
    } else {
        // Check if the new username or email is already taken by another user
        $usernameCheckSql = "SELECT id FROM users WHERE username = ? AND id <> ?";
        $uStmt = mysqli_prepare($conn, $usernameCheckSql);
        mysqli_stmt_bind_param($uStmt, "si", $username, $_SESSION["user_id"]);
        mysqli_stmt_execute($uStmt);
        $uRes = mysqli_stmt_get_result($uStmt);

        $emailCheckSql = "SELECT id FROM users WHERE email = ? AND id <> ?";
        $eStmt = mysqli_prepare($conn, $emailCheckSql);
        mysqli_stmt_bind_param($eStmt, "si", $email, $_SESSION["user_id"]);
        mysqli_stmt_execute($eStmt);
        $eRes = mysqli_stmt_get_result($eStmt);

        $usernameTaken = $uRes !== false && mysqli_fetch_assoc($uRes);
        $emailTaken = $eRes !== false && mysqli_fetch_assoc($eRes);

        if ($usernameTaken) {
            $error = "That username is already taken.";
        } elseif ($emailTaken) {
            $error = "That email is already in use.";
        } else {
            if ($removePhoto && !empty($profileImagePath)) {
                cloudinary_delete_by_url($profileImagePath);
                $profileImagePath = null;
            }

            if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES["profile_image"]["error"] === UPLOAD_ERR_OK) {
                    $allowedExtensions = ["jpg", "jpeg", "png", "gif", "webp"];
                    $fileName = $_FILES["profile_image"]["name"];
                    $tmpName = $_FILES["profile_image"]["tmp_name"];
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    if (in_array($extension, $allowedExtensions, true)) {
                        try {
                            if (!empty($profileImagePath)) {
                                cloudinary_delete_by_url($profileImagePath);
                            }

                            $uploadResult = cloudinary_upload_image($tmpName, $fileName, 'milk_market/avatars', 'avatar');
                            $profileImagePath = $uploadResult['secure_url'] ?? null;
                        } catch (RuntimeException $exception) {
                            $error = $exception->getMessage();
                        }
                    } else {
                        $error = "Profile image must be jpg, jpeg, png, gif, or webp.";
                    }
                } else {
                    $error = "Could not upload the profile image.";
                }
            }

            if ($error === "") {
                if ($newPassword !== "") {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateSql = "UPDATE users SET username = ?, email = ?, bio = ?, password = ?, profile_image = ? WHERE id = ?";
                    $updStmt = mysqli_prepare($conn, $updateSql);
                    mysqli_stmt_bind_param($updStmt, "sssssi", $username, $email, $bio, $hashedPassword, $profileImagePath, $_SESSION["user_id"]);
                } else {
                    $updateSql = "UPDATE users SET username = ?, email = ?, bio = ?, profile_image = ? WHERE id = ?";
                    $updStmt = mysqli_prepare($conn, $updateSql);
                    mysqli_stmt_bind_param($updStmt, "ssssi", $username, $email, $bio, $profileImagePath, $_SESSION["user_id"]);
                }
                
                $updateSuccess = mysqli_stmt_execute($updStmt);

                if (!$updateSuccess) {
                    $error = "Could not save your settings right now.";
                } else {
                    $_SESSION["username"] = $username;
                    $_SESSION["profile_image"] = $profileImagePath;
                    $message = "Settings updated successfully.";
                    $user["username"] = $username;
                    $user["email"] = $email;
                    $user["profile_image"] = $profileImagePath;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="settings-page">
    <div class="settings-card">
        <div class="settings-head">
            <div>
                <span class="login-brand">All About Milk</span>
                <h1>Settings</h1>
                <p class="login-copy">Update your profile details and profile photo.</p>
            </div>
            <a class="back-link" href="home.php">Back to feed</a>
        </div>

        <?php if ($message !== ""): ?>
            <p class="message success"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <p class="message error settings-error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <div class="settings-profile-preview">
            <?php if (!empty($user["profile_image"])): ?>
                <img class="settings-avatar" src="<?php echo htmlspecialchars($user["profile_image"]); ?>" alt="Profile picture">
            <?php else: ?>
                <div class="settings-avatar avatar-fallback"><?php echo strtoupper(substr($user["username"], 0, 1)); ?></div>
            <?php endif; ?>
            <div>
                <strong><?php echo htmlspecialchars($user["username"]); ?></strong>
                <p class="muted"><?php echo htmlspecialchars($user["email"]); ?></p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="stack-form settings-form">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user["username"]); ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user["email"]); ?>">

            <label for="bio">Bio</label>
            <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user["bio"] ?? ""); ?></textarea>

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep your current password">

            <label for="profile_image">Profile Picture</label>
            <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">

            <?php if (!empty($user["profile_image"])): ?>
                <label class="checkbox-row" for="remove_photo">
                    <input type="checkbox" id="remove_photo" name="remove_photo">
                    <span>Remove current profile picture</span>
                </label>
            <?php endif; ?>

            <button type="submit" class="primary-button">Save Settings</button>
        </form>
    </div>
    <script src="../assets/realtime_notifications.js"></script>
</body>
</html>
