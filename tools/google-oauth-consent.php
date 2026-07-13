<?php
/**
 * One-time helper: obtain the Google OAuth refresh token for the
 * appointment booker (see BOOKING-SETUP.md).
 *
 * Fill google_oauth client_id and client_secret in config.php first, then
 * run from the repo root:
 *
 *   php -S localhost:8765 tools/google-oauth-consent.php
 *
 * and open http://localhost:8765 in a browser. Sign in as the calendar
 * owner and approve; the page shows the refresh token to paste into
 * config.php. Stop the server afterwards (Ctrl+C).
 *
 * Loopback-only by design: it refuses any non-local request, so it is
 * harmless if it ever ends up on the deployed host.
 */

declare(strict_types=1);

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This helper only works on localhost. See BOOKING-SETUP.md.');
}

$root       = dirname(__DIR__);
$configPath = $root . '/config.php';
if (!is_file($configPath)) {
    exit('config.php not found. Copy config.example.php to config.php first.');
}
$config = require $configPath;

require $root . '/includes/form-helpers.php';
require $root . '/includes/google-calendar.php';

$oauth        = $config['google_oauth'] ?? [];
$clientId     = (string) ($oauth['client_id'] ?? '');
$clientSecret = (string) ($oauth['client_secret'] ?? '');
if ($clientId === '' || $clientSecret === '' || strpos($clientId, 'REPLACE') === 0) {
    exit('Fill in google_oauth client_id and client_secret in config.php first (BOOKING-SETUP.md step 2).');
}

$redirectUri = 'http://localhost:8765/callback';
$path        = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path !== '/callback') {
    // Step 1: send the browser to the Google consent screen.
    // prompt=consent + access_type=offline guarantee a refresh token comes back.
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => VDV_GOOGLE_SCOPES,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'login_hint'    => $config['booking_calendar_id'] ?? '',
    ]);
    header('Location: ' . $url);
    exit;
}

// Step 2: back from Google — exchange the code for tokens.
header('Content-Type: text/plain; charset=utf-8');

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    exit("Google returned no authorization code.\nError: " . (string) ($_GET['error'] ?? 'unknown') . "\nClose this tab and try again from http://localhost:8765.");
}

$response = vdvPostForm('https://oauth2.googleapis.com/token', [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
]);

$refreshToken = $response['refresh_token'] ?? null;
if (!is_string($refreshToken) || $refreshToken === '') {
    exit("Token exchange failed.\nResponse: " . json_encode($response) . "\nClose this tab and try again from http://localhost:8765.");
}

echo "Success. Paste this into config.php under google_oauth:\n\n";
echo "  'refresh_token' => '" . $refreshToken . "',\n\n";
echo "Then stop this helper server (Ctrl+C). Done.\n";
