<?php
/**
 * Google Login Page
 * Initiates the Google OAuth 2.0 authorization flow. 
 * Redirects the user to Google's authentication server with the required parameters.
 */
session_start();

$config = require __DIR__ . '/../config/google_oauth.php';

// Check if OAuth is configured before proceeding
if (empty($config['client_id']) || $config['client_id'] === 'YOUR_GOOGLE_CLIENT_ID') {
    header('Location: login.php?google_error=not_configured');
    exit;
}

// Generate a random state token to prevent CSRF attacks
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'response_type' => 'code',
    'scope' => $config['scope'],
    'access_type' => 'online',
    'include_granted_scopes' => 'true',
    'prompt' => 'select_account',
    'state' => $state,
];

$googleUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
header('Location: ' . $googleUrl);
exit;
