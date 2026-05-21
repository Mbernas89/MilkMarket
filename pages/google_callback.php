<?php
/**
 * Google OAuth Callback Page
 * Processes the authorization code returned by Google, fetches the user's 
 * profile information, and handles either logging in existing users or 
 * registering new ones via their Google account.
 */
session_start();
require_once "../config/db.php";

$config = require __DIR__ . '/../config/google_oauth.php';

/**
 * Redirects the user back to the login page with a specific error code.
 */
function redirect_with_error(string $code): void
{
    header('Location: login.php?google_error=' . urlencode($code));
    exit;
}

function post_form(string $url, array $data): ?array
{
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
}

function get_json(string $url, string $accessToken): ?array
{
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\n",
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === false) {
        return null;
    }

    $decoded = json_decode($result, true);
    return is_array($decoded) ? $decoded : null;
}

// Verify Google OAuth configuration
if (empty($config['client_id']) || $config['client_id'] === 'YOUR_GOOGLE_CLIENT_ID') {
    redirect_with_error('not_configured');
}

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

// Check if there was an error during authentication
if ($error !== '') {
    redirect_with_error('access_denied');
}

// Verify the 'state' token to prevent CSRF attacks
if ($state === '' || !hash_equals($_SESSION['google_oauth_state'] ?? '', $state)) {
    redirect_with_error('invalid_state');
}

unset($_SESSION['google_oauth_state']);

// Ensure an authorization code was provided
if ($code === '') {
    redirect_with_error('missing_code');
}

// Exchange the authorization code for an access token
$tokenData = post_form('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code',
]);

if (!$tokenData || empty($tokenData['access_token'])) {
    redirect_with_error('token_failed');
}

// Fetch user profile information from Google using the access token
$userInfo = get_json('https://www.googleapis.com/oauth2/v2/userinfo', $tokenData['access_token']);

if (!$userInfo || empty($userInfo['id']) || empty($userInfo['email'])) {
    redirect_with_error('userinfo_failed');
}

// Extract user details
$googleId = (string) $userInfo['id'];
$email = trim((string) $userInfo['email']);
$name = trim((string) ($userInfo['name'] ?? 'Google User'));
$picture = trim((string) ($userInfo['picture'] ?? ''));

// Generate a clean username base from the user's name
$usernameBase = preg_replace('/[^A-Za-z0-9_]/', '', strtolower(str_replace(' ', '_', $name)));
$usernameBase = $usernameBase !== '' ? $usernameBase : 'milkuser';
$username = $usernameBase;

// Check if a user with this Google ID or Email already exists in the database
$sqlExisting = 'SELECT id, username, profile_image FROM users WHERE google_id = ? OR email = ?';
$stmtExisting = mysqli_prepare($conn, $sqlExisting);
mysqli_stmt_bind_param($stmtExisting, "ss", $googleId, $email);
mysqli_stmt_execute($stmtExisting);
$resultExisting = mysqli_stmt_get_result($stmtExisting);
$existingUser = ($resultExisting !== false) ? mysqli_fetch_assoc($resultExisting) : null;

if ($existingUser) {
    // If the user exists, update their profile with the Google ID and Provider status
    $finalUsername = $existingUser['username'];
    $profileImage = !empty($existingUser['profile_image']) ? $existingUser['profile_image'] : ($picture !== '' ? $picture : null);

    $sqlUpd = 'UPDATE users SET google_id = ?, auth_provider = ?, profile_image = COALESCE(profile_image, ?) WHERE id = ?';
    $stmtUpd = mysqli_prepare($conn, $sqlUpd);
    $provider = 'google';
    $picParam = ($picture !== '' ? $picture : null);
    mysqli_stmt_bind_param($stmtUpd, "sssi", $googleId, $provider, $picParam, $existingUser['id']);
    mysqli_stmt_execute($stmtUpd);

    // Establish session and redirect
    $_SESSION['user_id'] = $existingUser['id'];
    $_SESSION['username'] = $finalUsername;
    $_SESSION['profile_image'] = $profileImage;
    header('Location: home.php');
    exit;
}

// If it's a new user, make sure the generated username is unique
$counter = 1;
while (true) {
    $sqlCheck = 'SELECT id FROM users WHERE username = ?';
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmtCheck, "s", $username);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);
    $taken = ($resultCheck !== false && mysqli_fetch_assoc($resultCheck));

    if (!$taken) {
        break; // Username is free
    }

    // Append a counter to the username if it's already taken
    $username = $usernameBase . $counter;
    $counter++;
}

$randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$sqlIns = 'INSERT INTO users (username, email, password, google_id, auth_provider, profile_image) VALUES (?, ?, ?, ?, ?, ?)';
$stmtIns = mysqli_prepare($conn, $sqlIns);
$provider = 'google';
$picParam = ($picture !== '' ? $picture : null);
mysqli_stmt_bind_param($stmtIns, "ssssss", $username, $email, $randomPassword, $googleId, $provider, $picParam);
$insertSuccess = mysqli_stmt_execute($stmtIns);

if (!$insertSuccess) {
    redirect_with_error('account_create_failed');
}

$sqlUser = 'SELECT id, username, profile_image FROM users WHERE google_id = ?';
$stmtUser = mysqli_prepare($conn, $sqlUser);
mysqli_stmt_bind_param($stmtUser, "s", $googleId);
mysqli_stmt_execute($stmtUser);
$resultUser = mysqli_stmt_get_result($stmtUser);
$newUser = ($resultUser !== false) ? mysqli_fetch_assoc($resultUser) : null;

if (!$newUser) {
    redirect_with_error('login_failed');
}

$_SESSION['user_id'] = $newUser['id'];
$_SESSION['username'] = $newUser['username'];
$_SESSION['profile_image'] = $newUser['profile_image'] ?? null;

header('Location: home.php');
exit;
