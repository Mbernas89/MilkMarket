<?php
/**
 * Registration Page
 * Handles new user account creation, including validation 
 * and secure password hashing.
 */
session_start();
require_once "../config/db.php";

// Redirect to home if user is already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit;
}

$message = "";

// Handle user registration form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $bio = trim($_POST["bio"] ?? "");

    if ($username === "" || $email === "" || $password === "") {
        $message = "Please fill in all fields.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\\\|,.<>\/?]).{8,}$/', $password)) {
        $message = "Password must be at least 8 characters long, include an uppercase letter, a lowercase letter, a number, and a special character.";
    } else {
        // Check if the provided email is already registered
        $checkSql = "SELECT id FROM users WHERE email = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $email);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);

        if ($checkResult === false) {
            $message = "Could not check existing users.";
        } elseif (mysqli_fetch_assoc($checkResult)) {
            $message = "That email is already registered.";
        } else {
            // Hash the password using the DEFAULT algorithm (currently bcrypt) for security
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertSql = "INSERT INTO users (username, email, password, bio, auth_provider) VALUES (?, ?, ?, ?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            $provider = 'local';
            mysqli_stmt_bind_param($insertStmt, "sssss", $username, $email, $hashedPassword, $bio, $provider);
            $insertSuccess = mysqli_stmt_execute($insertStmt);

            if (!$insertSuccess) {
                $message = "Registration failed.";
            } else {
                // Redirect to login page with a success flag
                header("Location: login.php?registered=1");
                exit;
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
    <title>Create Milk Market Account</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="register-page">
    <div class="register-card">
        <span class="login-brand">Milk Market</span>
        <h1>Create account</h1>
        <p class="login-copy">Join the dairy community and start posting products, tips, and updates.</p>

        <?php if ($message !== ""): ?>
            <p class="message error settings-error"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="post" class="stack-form">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST["username"] ?? ""); ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>">

            <label for="bio">Bio (Optional)</label>
            <textarea id="bio" name="bio" rows="3" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($_POST["bio"] ?? ""); ?></textarea>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Create a password" 
                   required 
                   pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};':&quot;\\|,.<>\/?]).{8,}$"
                   title="Must contain at least 8 characters, one uppercase, one lowercase, one number, and one special character.">
            <p class="small-copy muted" style="margin-top: 4px; line-height: 1.4;">
                Requirement: 8+ characters, including Uppercase, Lowercase, Number, and Special Character (!@#$...).
            </p>

            <button type="submit" class="primary-button">Create Account</button>
        </form>

        <div class="oauth-divider"><span>or</span></div>
        <a class="google-auth-button" href="google_login.php">
            <span class="google-mark">G</span>
            <span>Continue with Google</span>
        </a>

        <p class="switch-link center-link">Already have an account? <a href="login.php">Log in here</a></p>
    </div>
</body>
</html>
