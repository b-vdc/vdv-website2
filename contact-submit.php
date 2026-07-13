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

require __DIR__ . '/includes/form-helpers.php';

// ---- Method guard -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ---- Rate limiting --------------------------------------------------------

if (vdvRateLimited($ip, 'contact')) {
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

// ---- Verify reCAPTCHA -----------------------------------------------------

if (!vdvVerifyRecaptcha($config, $token, 'contact', $ip)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'We could not verify this submission. Please try again.']);
    exit;
}

// ---- Send emails ----------------------------------------------------------

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server is not configured yet.']);
    exit;
}
require $autoload;

use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
        . "<p>Talk to you soon,<br>Elisa</p>";
    $confirm->AltBody = "Hi {$name},\n\nThanks for your message. It has reached me and I will get back to you within 48 hours.\n\nWhat you sent:\n{$message}\n\nTalk to you soon,\nElisa";
    $confirm->send();
} catch (PHPMailerException $e) {
    error_log('VDV contact form confirmation email failed: ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
