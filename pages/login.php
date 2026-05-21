<?php
/**
 * Login Page
 * Handles user authentication via local credentials (username/email and password)
 * or Google OAuth integration.
 */
session_start();
require_once "../config/db.php";

// Redirect to home if user is already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit;
}

$message = "";

// Handle status messages from redirect parameters
if (isset($_GET["registered"])) {
    $message = "Registration successful. You can log in now.";
} elseif (isset($_GET['google_error'])) {
    // Map Google OAuth error codes to user-friendly messages
    $googleError = $_GET['google_error'];
    $message = match ($googleError) {
        'not_configured' => 'Google login is not configured yet. Add your Client ID and Client Secret first.',
        'access_denied' => 'Google login was cancelled.',
        'invalid_state' => 'Google login session expired. Please try again.',
        default => 'Google login could not be completed right now.',
    };
}

// Handle local login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identity = trim($_POST["email"] ?? ""); // Can be username or email
    $password = trim($_POST["password"] ?? "");

    if ($identity === "" || $password === "") {
        $message = "Please enter your username or email and password.";
    } else {
        // Query user by email OR username (role column removed from users table)
        $sql = "SELECT id, username, password, profile_image FROM users WHERE email = ? OR username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $identity, $identity);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result === false) {
            $message = "Login failed.";
        } else {
            $user = mysqli_fetch_assoc($result);

            // Verify password hash using built-in PHP function
            if (!$user || !password_verify($password, $user["password"])) {
                $message = "Invalid username/email or password.";
            } else {
                // Set session variables and redirect to home
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["profile_image"] = $user["profile_image"] ?? null;
                // Role column is not present in the database; default to 'user'
                $_SESSION["role"] = 'user';

                header("Location: home.php");
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
    <title>Milk Market Login</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="cool-login-page">
    <div class="cool-login-shell">
        <section class="cool-login-showcase">
            <span class="cool-login-badge">Milk Market</span>
            <h1>Fresh dairy updates, all in one place.</h1>
            <p>Log in to discover product announcements, milk availability, community tips, and shared posts from sellers and enthusiasts.</p>
            <div class="cool-login-highlights">
                <div>
                    <strong>Seller friendly</strong>
                    <span>Post product photos, prices, and daily stock updates.</span>
                </div>
                <div>
                    <strong>Community driven</strong>
                    <span>Like, comment, share, and follow dairy conversations.</span>
                </div>
            </div>
        </section>

        <section class="cool-login-panel">
            <div class="cool-login-card">
                <span class="login-brand">Milk Market</span>
                <h1>Welcome back</h1>
                <p class="login-copy">Sign in to continue to your feed.</p>

                <?php if ($message !== ""): ?>
                    <p class="message <?php echo isset($_GET["registered"]) ? "success" : "error"; ?><?php echo isset($_GET['google_error']) ? ' settings-error' : ''; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                <?php endif; ?>

                <form method="post" class="stack-form cool-login-form">
                    <label for="email">Username or Email</label>
                    <input type="text" id="email" name="email" placeholder="Enter your username or email" value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>">

                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password">

                    <button type="submit" class="primary-button cool-login-submit">Log In</button>
                </form>

                <div class="oauth-divider"><span>or</span></div>
                <a class="google-auth-button" href="google_login.php">
                    <span class="google-mark">G</span>
                    <span>Continue with Google</span>
                </a>

                <p class="switch-link center-link">No account yet? <a href="register.php">Create one here</a></p>
            </div>
        </section>
    </div>
</body>
</html>
