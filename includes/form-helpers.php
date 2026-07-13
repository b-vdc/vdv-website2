<?php
/**
 * Shared helpers for the form endpoints (contact-submit.php, booking-*.php):
 * file-based rate limiting, outbound HTTP, reCAPTCHA verification and the
 * PHPMailer builder. Extracted from contact-submit.php so the contact form
 * and the appointment booker stay in sync.
 *
 * PHPMailer must be autoloaded (vendor/autoload.php) before vdvBuildMailer
 * is called.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

// ---- Rate limiting (lightweight, file-based) -----------------------------
// Blunts abuse of the outgoing emails (they go to whatever address the
// visitor types in) without needing a database. Each endpoint uses its own
// bucket so a burst on one form does not lock the other.

function vdvRateLimited(string $ip, string $bucket, int $windowSeconds = 3600, int $maxRequests = 5): bool
{
    if ($ip === 'unknown') {
        return false;
    }

    $dir = dirname(__DIR__) . '/data/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/' . $bucket . '-' . hash('sha256', $ip) . '.json';

    $now = time();

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

// ---- Outbound HTTP --------------------------------------------------------

/**
 * POST a form-encoded body and decode the JSON response.
 * Used for the reCAPTCHA verification call and the Google token exchange.
 */
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

/**
 * Send a JSON request (Google Calendar API) and decode the JSON response.
 * Returns ['status' => int, 'body' => array] or null when the request never
 * completed (network failure, timeout).
 */
function vdvHttpJson(string $method, string $url, ?array $jsonBody, array $headers = []): ?array
{
    $payload = $jsonBody === null ? null : json_encode($jsonBody);
    $headers[] = 'Content-Type: application/json; charset=utf-8';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $payload ?? '',
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status   = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if ($response === false || $response === null) {
        return null;
    }
    $decoded = json_decode($response, true);
    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : []];
}

// ---- reCAPTCHA ------------------------------------------------------------

/**
 * Verify a reCAPTCHA v3 token. $expectedAction ties the token to the form
 * that generated it ('contact', 'book'). Returns true when the submission
 * may proceed. Skipped entirely when recaptcha_disable is set (local dev).
 */
function vdvVerifyRecaptcha(array $config, string $token, string $expectedAction, string $ip): bool
{
    if (!empty($config['recaptcha_disable'])) {
        return true;
    }

    $verification = vdvPostForm('https://www.google.com/recaptcha/api/siteverify', [
        'secret'   => $config['recaptcha_secret_key'],
        'response' => $token,
        'remoteip' => $ip,
    ]);

    $success = $verification['success'] ?? false;
    $action  = $verification['action'] ?? '';
    $score   = isset($verification['score']) ? (float) $verification['score'] : 0.0;

    return $success && $action === $expectedAction && $score >= (float) $config['recaptcha_min_score'];
}

// ---- Mail -----------------------------------------------------------------

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
