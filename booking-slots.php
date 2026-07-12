<?php
/**
 * Availability endpoint for the appointment booker on the Contact page.
 *
 * GET booking-slots.php?type=intake|training|legal
 *
 * Returns the bookable days and times for one call type: the configured
 * schedule (weekdays, daily window, lead time, horizon) minus everything
 * that is busy in the Google Calendar, with a buffer around existing
 * events. Slot strings are wall-clock times in the booking timezone; the
 * client shows and echoes them verbatim.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ---- Config -----------------------------------------------------------

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server is not configured yet.']);
    exit;
}

require __DIR__ . '/includes/form-helpers.php';
require __DIR__ . '/includes/booking-lib.php';
require __DIR__ . '/includes/google-calendar.php';

$config = vdvBookingConfig(require $configPath);

// ---- Method guard -------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ---- Validate the call type ----------------------------------------------

$typeKey = (string) ($_GET['type'] ?? '');
if (!isset($config['booking_types'][$typeKey])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown call type.']);
    exit;
}

// ---- Compute availability -------------------------------------------------

$candidates = vdvBookingCandidateStarts($config, $typeKey);

$available = [];
if ($candidates) {
    $bufferSeconds = ((int) $config['booking_buffer_minutes']) * 60;
    $duration      = (int) $config['booking_types'][$typeKey]['duration'];
    $rangeMin      = $candidates[0]->modify('-' . $bufferSeconds . ' seconds');
    $rangeMax      = end($candidates)->modify('+' . ($duration * 60 + $bufferSeconds) . ' seconds');

    $busy = vdvCalendarBusy($config, $rangeMin, $rangeMax);
    if ($busy === null) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'Could not load availability right now. Please try again in a moment.']);
        exit;
    }
    $available = vdvBookingFilterBusy($config, $typeKey, $candidates, $busy);
}

echo json_encode([
    'ok'              => true,
    'timezone'        => $config['booking_timezone'],
    'type'            => $typeKey,
    'durationMinutes' => (int) $config['booking_types'][$typeKey]['duration'],
    'days'            => vdvBookingGroupByDay($config, $available),
]);
