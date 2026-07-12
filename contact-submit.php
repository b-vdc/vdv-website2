<?php
/**
 * Contact form endpoint for vandervolpi.com.
 *
 * Receives the Contact page form (name, email, message, plus a
 * reCAPTCHA v3 token), verifies it, then sends two emails
 * through the info@vandervolpi.com Google Workspace mailbox: a
 * notification to Van der Volpi and a confirmation to the person who
 * wrote in.
 *
 * Requires:
 *  - config.php (gitignored) — copy from config.example.php and fill in.
 *  - vendor/ (PHPMailer) — run `composer install` after deploying.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ---- Config -----------------------------------------------------------

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server is not configured yet.']);
    exit;
}
$config = require $configPath;

// ---- Method guard -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ---- Rate limiting (lightweight, file-based) -----------------------------
// Blunts abuse of the confirmation email (it goes to whatever address the
// visitor types in) without needing a database.

function vdvRateLimited(string $ip): bool
{
    if ($ip === 'unknown') {
        return false;
    }

    $dir = __DIR__ . '/data/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . hash('sha256', $ip) . '.json';

    $windowSeconds = 3600;
    $maxRequests   = 5;
    $now           = time();

    $hits = [];
    if (is_file($file)) {
        $raw  = file_get_contents($file);
        $hits = json_decode($raw !== false ? $raw : '[]', true);
        if (!is_array($hits)) {
            $hits = [];
        }
    }
    $hits = array_values(array_filter($hits, static function ($t) use ($now, $windowSeconds) {
        return is_int($t) && $t > $now - $windowSeconds;
    }));

    if (count($hits) >= $maxRequests) {
        return true;
    }

    $hits[] = $now;
    @file_put_contents($file, json_encode($hits));
    return false;
}

if (vdvRateLimited($ip)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many messages sent from this connection. Please try again later.']);
    exit;
}

// ---- Validate fields ------------------------------------------------------

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$token   = trim((string) ($_POST['recaptcha_token'] ?? ''));

$invalidFields = [];
if ($name === '' || mb_strlen($name) > 120) {
    $invalidFields[] = 'name';
}
if ($email === '' || mb_strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $invalidFields[] = 'email';
}
if ($message === '' || mb_strlen($message) > 5000) {
    $invalidFields[] = 'message';
}
if (!$config['recaptcha_disable'] && $token === '') {
    $invalidFields[] = 'recaptcha_token';
}

if ($invalidFields) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $invalidFields]);
    exit;
}

// ---- HTTP POST helper (used for the reCAPTCHA verification call) --------

function vdvPostForm(string $url, array $fields): ?array
{
    $body = http_build_query($fields);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 8,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === null) {
        return null;
    }
    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

// ---- Verify reCAPTCHA -----------------------------------------------------

if (!$config['recaptcha_disable']) {
    $verification = vdvPostForm('https://www.google.com/recaptcha/api/siteverify', [
        'secret'   => $config['recaptcha_secret_key'],
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $success = $verification['success'] ?? false;
    $action  = $verification['action'] ?? '';
    $score   = isset($verification['score']) ? (float) $verification['score'] : 0.0;

    if (!$success || $action !== 'contact' || $score < (float) $config['recaptcha_min_score']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'We could not verify this submission. Please try again.']);
        exit;
    }
}

// ---- Send emails ----------------------------------------------------------

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server is not configured yet.']);
    exit;
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function vdvBuildMailer(array $config): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->Port       = $config['smtp_port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($config['from_email'], $config['from_name']);
    return $mail;
}

$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$timestamp   = date('Y-m-d H:i');

// The notification to Van der Volpi is the part that matters most: if it
// fails, nothing got through, so report failure. The confirmation to the
// customer is a courtesy on top of that — if it fails (e.g. their address
// rejects mail), the message still reached Van der Volpi, so that failure
// is logged but does not turn a successful submission into an error.

try {
    $notify = vdvBuildMailer($config);
    $notify->addAddress($config['to_email'], $config['to_name']);
    $notify->addReplyTo($email, $name);
    $notify->isHTML(true);
    $notify->Subject = "New contact message from {$name}";
    $notify->Body    = "<p><strong>Name:</strong> {$safeName}</p>"
        . "<p><strong>Email:</strong> {$safeEmail}</p>"
        . "<p><strong>Sent:</strong> {$timestamp}</p>"
        . "<p><strong>Message:</strong></p><p>{$safeMessage}</p>";
    $notify->AltBody = "Name: {$name}\nEmail: {$email}\nSent: {$timestamp}\n\nMessage:\n{$message}";
    $notify->send();
} catch (PHPMailerException $e) {
    error_log('VDV contact form notification email failed: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Your message could not be sent right now. Please email info@vandervolpi.com directly.']);
    exit;
}

try {
    $confirm = vdvBuildMailer($config);
    $confirm->addAddress($email, $name);
    $confirm->isHTML(true);
    $confirm->Subject = 'Thanks for reaching out to Van der Volpi';
    $confirm->Body    = "<p>Hi {$safeName},</p>"
        . "<p>Thanks for your message. It has reached me and I will get back to you within 48 hours.</p>"
        . "<p><strong>What you sent:</strong></p><p>{$safeMessage}</p>"
        . "<p>Talk soon,<br>Elisa</p>";
    $confirm->AltBody = "Hi {$name},\n\nThanks for your message. It has reached me and I will get back to you within 48 hours.\n\nWhat you sent:\n{$message}\n\nTalk soon,\nElisa";
    $confirm->send();
} catch (PHPMailerException $e) {
    error_log('VDV contact form confirmation email failed: ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
