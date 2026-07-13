<?php
/**
 * Booking endpoint for the appointment booker on the Contact page.
 *
 * Receives the booking form (name, email, phone, call type, message, the
 * chosen slot and a reCAPTCHA v3 token), verifies everything, re-checks the
 * slot against the Google Calendar, creates the calendar event (visitor
 * invited, Google Meet link, Google sends the official invite), then sends
 * a branded confirmation to the visitor and a notification to Van der Volpi.
 *
 * Requires:
 *  - config.php (gitignored) — copy from config.example.php and fill in,
 *    including the google_service_account key (see BOOKING-SETUP.md).
 *  - vendor/ (PHPMailer) — run `composer install` after deploying.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');

// ---- Config -----------------------------------------------------------

$configPath = __DIR__ . '/config.php';
$autoload   = __DIR__ . '/vendor/autoload.php';
if (!is_file($configPath) || !is_file($autoload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server is not configured yet.']);
    exit;
}

require $autoload;
require __DIR__ . '/includes/form-helpers.php';
require __DIR__ . '/includes/booking-lib.php';
require __DIR__ . '/includes/google-calendar.php';

$config = vdvBookingConfig(require $configPath);

// ---- Method guard -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// ---- Rate limiting --------------------------------------------------------

if (vdvRateLimited($ip, 'booking')) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many booking attempts from this connection. Please try again later.']);
    exit;
}

// ---- Validate fields ------------------------------------------------------

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$typeKey = trim((string) ($_POST['type'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$slot    = trim((string) ($_POST['slot'] ?? ''));
$token   = trim((string) ($_POST['recaptcha_token'] ?? ''));

$invalidFields = [];
if ($name === '' || mb_strlen($name) > 120) {
    $invalidFields[] = 'name';
}
if ($email === '' || mb_strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $invalidFields[] = 'email';
}
if ($phone === '' || mb_strlen($phone) > 40 || !preg_match('/^[0-9+()\/. -]{6,}$/', $phone)) {
    $invalidFields[] = 'phone';
}
if (mb_strlen($message) > 2000) {
    $invalidFields[] = 'message';
}
if (!isset($config['booking_types'][$typeKey])) {
    $invalidFields[] = 'type';
}
if (!$config['recaptcha_disable'] && $token === '') {
    $invalidFields[] = 'recaptcha_token';
}

// The slot must be one the availability endpoint could have offered:
// strict format, allowed weekday, on the hourly grid inside the window,
// past the lead time and within the horizon. Anything else is forged or
// stale and gets rejected without a Google call.
$start = null;
if (!in_array('type', $invalidFields, true) && $slot !== '') {
    $start = vdvBookingParseSlot($config, $typeKey, $slot);
}
if ($start === null) {
    $invalidFields[] = 'slot';
}

if ($invalidFields) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $invalidFields]);
    exit;
}

// ---- Verify reCAPTCHA -----------------------------------------------------

if (!vdvVerifyRecaptcha($config, $token, 'book', $ip)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'We could not verify this booking. Please try again.']);
    exit;
}

// ---- Re-check the slot and create the event (under a per-slot lock) --------
// The lock serializes two visitors racing for the same slot on this server;
// the fresh freeBusy query catches anything that landed on the calendar
// since the visitor loaded the page.

$type            = $config['booking_types'][$typeKey];
$durationMinutes = (int) $type['duration'];
$bufferSeconds   = ((int) $config['booking_buffer_minutes']) * 60;
$end             = $start->add(new DateInterval('PT' . $durationMinutes . 'M'));

$lockDir = __DIR__ . '/data/booking-locks';
if (!is_dir($lockDir)) {
    @mkdir($lockDir, 0700, true);
}
$lockHandle = @fopen($lockDir . '/' . sha1($slot) . '.lock', 'c');
if ($lockHandle) {
    @flock($lockHandle, LOCK_EX);
}

$createdEvent = null;
try {
    $busy = vdvCalendarBusy(
        $config,
        $start->modify('-' . $bufferSeconds . ' seconds'),
        $end->modify('+' . $bufferSeconds . ' seconds')
    );
    if ($busy === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Booking could not be completed right now. Email info@vandervolpi.com or call +32 474 055 052 and we\'ll set a time directly.']);
        exit;
    }
    if (vdvBookingFilterBusy($config, $typeKey, [$start], $busy) === []) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'That time was just taken. Pick another slot.', 'code' => 'slot_taken']);
        exit;
    }

    $description = "Booked via vandervolpi.com\n\nName: {$name}\nEmail: {$email}\nPhone: {$phone}";
    if ($message !== '') {
        $description .= "\n\nTopic:\n{$message}";
    }

    $createdEvent = vdvCalendarInsertEvent($config, [
        'summary'     => "{$type['label']} with {$name}",
        'description' => $description,
        'start'       => ['dateTime' => $start->format('Y-m-d\TH:i:s'), 'timeZone' => $config['booking_timezone']],
        'end'         => ['dateTime' => $end->format('Y-m-d\TH:i:s'), 'timeZone' => $config['booking_timezone']],
        'attendees'   => [['email' => $email, 'displayName' => $name]],
        'conferenceData' => [
            'createRequest' => [
                'requestId'             => bin2hex(random_bytes(12)),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ],
    ]);
} finally {
    if ($lockHandle) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

// ---- Emails -----------------------------------------------------------------

$dayLabel    = $start->format('l j F');   // Monday 20 July
$timeLabel   = $start->format('H:i');
$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone   = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$typeLabel   = $type['label'];

if ($createdEvent === null) {
    // reCAPTCHA passed and the slot was free, but Google would not take the
    // event. Tell the visitor how to reach out directly, and alert the owner
    // (best effort) so the lead is not lost.
    try {
        $alert = vdvBuildMailer($config);
        $alert->addAddress($config['to_email'], $config['to_name']);
        $alert->addReplyTo($email, $name);
        $alert->isHTML(true);
        $alert->Subject = "Booking attempt failed: {$typeLabel} with {$name}";
        $alert->Body    = "<p>A visitor tried to book but the calendar event could not be created. Reach out to them directly.</p>"
            . "<p><strong>Wanted:</strong> {$typeLabel} on {$dayLabel} at {$timeLabel}</p>"
            . "<p><strong>Name:</strong> {$safeName}<br><strong>Email:</strong> {$safeEmail}<br><strong>Phone:</strong> {$safePhone}</p>"
            . ($message !== '' ? "<p><strong>Topic:</strong></p><p>{$safeMessage}</p>" : '');
        $alert->AltBody = "A visitor tried to book but the calendar event could not be created.\n\nWanted: {$typeLabel} on {$dayLabel} at {$timeLabel}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\n\nTopic:\n{$message}";
        $alert->send();
    } catch (PHPMailerException $e) {
        error_log('VDV booking: failed-booking alert email failed: ' . $e->getMessage());
    }
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Booking could not be completed right now. Email info@vandervolpi.com or call +32 474 055 052 and we\'ll set a time directly.']);
    exit;
}

$meetLink     = vdvCalendarMeetLink($createdEvent);
$safeMeetLink = htmlspecialchars($meetLink, ENT_QUOTES, 'UTF-8');

// The calendar event is created and Google sends the official invite, so
// both emails below are a courtesy on top: failures are logged, never
// surfaced as a failed booking.

try {
    $notify = vdvBuildMailer($config);
    $notify->addAddress($config['to_email'], $config['to_name']);
    $notify->addReplyTo($email, $name);
    $notify->isHTML(true);
    $notify->Subject = "New booking: {$typeLabel} with {$name}";
    $notify->Body    = "<p><strong>{$typeLabel}</strong> on {$dayLabel} at {$timeLabel} ({$durationMinutes} min)</p>"
        . "<p><strong>Name:</strong> {$safeName}<br>"
        . "<strong>Email:</strong> {$safeEmail}<br>"
        . "<strong>Phone:</strong> {$safePhone}</p>"
        . ($message !== '' ? "<p><strong>Topic:</strong></p><p>{$safeMessage}</p>" : '')
        . ($meetLink !== '' ? "<p><strong>Meet link:</strong> <a href=\"{$safeMeetLink}\">{$safeMeetLink}</a></p>" : '');
    $notify->AltBody = "{$typeLabel} on {$dayLabel} at {$timeLabel} ({$durationMinutes} min)\n\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\n\nTopic:\n{$message}\n\nMeet link: {$meetLink}";
    $notify->send();
} catch (PHPMailerException $e) {
    error_log('VDV booking notification email failed: ' . $e->getMessage());
}

try {
    $rateNote     = '';
    $rateNoteText = '';
    if ($typeKey === 'legal') {
        $rateNote     = '<p>A quick reminder on the rate: &euro;170/hour, charged per started 15 minutes, with a 30-minute minimum.</p>';
        $rateNoteText = "A quick reminder on the rate: EUR 170/hour, charged per started 15 minutes, with a 30-minute minimum.\n\n";
    }
    $confirm = vdvBuildMailer($config);
    $confirm->addAddress($email, $name);
    $confirm->isHTML(true);
    $confirm->Subject = "Your {$typeLabel} with Van der Volpi is booked";
    $confirm->Body    = "<p>Hi {$safeName},</p>"
        . "<p>Your {$typeLabel} is booked for {$dayLabel} at {$timeLabel} (Brussels time).</p>"
        . ($meetLink !== '' ? "<p>Google Meet link: <a href=\"{$safeMeetLink}\">{$safeMeetLink}</a></p>" : '')
        . "<p>You'll also get a calendar invite from Google. Accept it and you're set.</p>"
        . $rateNote
        . "<p>Need to move it? Reply to this email or call <a href=\"tel:+32474055052\">+32 474 055 052</a>.</p>"
        . "<p>Talk soon,<br>Elisa</p>";
    $confirm->AltBody = "Hi {$name},\n\n"
        . "Your {$typeLabel} is booked for {$dayLabel} at {$timeLabel} (Brussels time).\n\n"
        . ($meetLink !== '' ? "Google Meet link: {$meetLink}\n\n" : '')
        . "You'll also get a calendar invite from Google. Accept it and you're set.\n\n"
        . $rateNoteText
        . "Need to move it? Reply to this email or call +32 474 055 052.\n\n"
        . "Talk soon,\nElisa";
    $confirm->send();
} catch (PHPMailerException $e) {
    error_log('VDV booking confirmation email failed: ' . $e->getMessage());
}

echo json_encode([
    'ok'              => true,
    'meetLink'        => $meetLink,
    'start'           => $start->format('Y-m-d H:i'),
    'typeLabel'       => $typeLabel,
    'durationMinutes' => $durationMinutes,
]);
