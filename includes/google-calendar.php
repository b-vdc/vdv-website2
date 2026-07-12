<?php
/**
 * Minimal Google Calendar client for the appointment booker.
 *
 * Talks to the Calendar REST API directly instead of pulling in
 * google/apiclient. Only three calls are needed: token refresh,
 * freeBusy.query and events.insert.
 *
 * Auth is a plain OAuth refresh token for the calendar owner (the org
 * policy on the Workspace blocks service-account keys). config.php holds
 * 'google_oauth' => [client_id, client_secret, refresh_token]; the one-time
 * consent flow that produces the refresh token is tools/google-oauth-consent.php
 * (walkthrough in BOOKING-SETUP.md).
 *
 * Set 'booking_disable_google' to true for local testing: availability then
 * acts as a fully free calendar and event creation is logged instead of
 * executed.
 *
 * Requires includes/form-helpers.php for vdvPostForm / vdvHttpJson.
 */

declare(strict_types=1);

// Requested at consent time; the refresh token can never reach beyond these.
const VDV_GOOGLE_SCOPES = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy';

function vdvGoogleCredentials(array $config): ?array
{
    $oauth = $config['google_oauth'] ?? null;
    if (!is_array($oauth)
        || empty($oauth['client_id'])
        || empty($oauth['client_secret'])
        || empty($oauth['refresh_token'])
    ) {
        return null;
    }
    return $oauth;
}

/**
 * Get an access token for the calendar owner, cached in
 * data/google-token.json until shortly before expiry.
 */
function vdvGoogleAccessToken(array $config): ?string
{
    $credentials = vdvGoogleCredentials($config);
    if ($credentials === null) {
        error_log('VDV booking: google_oauth is missing or incomplete in config.php');
        return null;
    }

    // Cache is only valid for the credentials that produced it.
    $fingerprint = hash('sha256', $credentials['client_id'] . ':' . $credentials['refresh_token']);

    $cacheFile = dirname(__DIR__) . '/data/google-token.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)
            && !empty($cached['token'])
            && ($cached['expires'] ?? 0) > time() + 60
            && ($cached['fingerprint'] ?? '') === $fingerprint
        ) {
            return $cached['token'];
        }
    }

    $now      = time();
    $response = vdvPostForm('https://oauth2.googleapis.com/token', [
        'grant_type'    => 'refresh_token',
        'client_id'     => $credentials['client_id'],
        'client_secret' => $credentials['client_secret'],
        'refresh_token' => $credentials['refresh_token'],
    ]);

    $token = $response['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        error_log('VDV booking: Google token refresh failed: ' . json_encode($response));
        return null;
    }

    $dataDir = dirname(__DIR__) . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0700, true);
    }
    @file_put_contents($cacheFile, json_encode([
        'token'       => $token,
        'expires'     => $now + (int) ($response['expires_in'] ?? 3600),
        'fingerprint' => $fingerprint,
    ]));

    return $token;
}

/**
 * Busy intervals on the booking calendar between $min and $max, as
 * [['start' => ts, 'end' => ts], ...]. Returns null on any failure so
 * callers can tell "Google is down" apart from "fully free" and never
 * offer slots they cannot vouch for.
 */
function vdvCalendarBusy(array $config, DateTimeImmutable $min, DateTimeImmutable $max): ?array
{
    if (!empty($config['booking_disable_google'])) {
        return [];
    }

    $token = vdvGoogleAccessToken($config);
    if ($token === null) {
        return null;
    }

    $utc      = new DateTimeZone('UTC');
    $response = vdvHttpJson('POST', 'https://www.googleapis.com/calendar/v3/freeBusy', [
        'timeMin' => $min->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        'timeMax' => $max->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
        'items'   => [['id' => $config['booking_calendar_id']]],
    ], ['Authorization: Bearer ' . $token]);

    if ($response === null || $response['status'] !== 200) {
        error_log('VDV booking: freeBusy query failed: ' . json_encode($response));
        return null;
    }

    $calendar = $response['body']['calendars'][$config['booking_calendar_id']] ?? [];
    if (!empty($calendar['errors'])) {
        error_log('VDV booking: freeBusy returned calendar errors: ' . json_encode($calendar['errors']));
        return null;
    }

    $busy = [];
    foreach ($calendar['busy'] ?? [] as $interval) {
        $start = strtotime($interval['start'] ?? '');
        $end   = strtotime($interval['end'] ?? '');
        if ($start !== false && $end !== false) {
            $busy[] = ['start' => $start, 'end' => $end];
        }
    }
    return $busy;
}

/**
 * Create the booking event (visitor as attendee, Google Meet link, invite
 * emails sent by Google). Returns the created event array, or null on
 * failure. In mock mode the event is logged and faked.
 */
function vdvCalendarInsertEvent(array $config, array $event): ?array
{
    if (!empty($config['booking_disable_google'])) {
        error_log('VDV booking (mock): would create event: ' . json_encode($event));
        return $event + ['id' => 'mock-event', 'hangoutLink' => 'https://meet.google.com/mock-test-link'];
    }

    $token = vdvGoogleAccessToken($config);
    if ($token === null) {
        return null;
    }

    $url = 'https://www.googleapis.com/calendar/v3/calendars/'
        . rawurlencode($config['booking_calendar_id'])
        . '/events?conferenceDataVersion=1&sendUpdates=all';

    $response = vdvHttpJson('POST', $url, $event, ['Authorization: Bearer ' . $token]);

    if ($response === null || $response['status'] < 200 || $response['status'] >= 300) {
        error_log('VDV booking: events.insert failed: ' . json_encode($response));
        return null;
    }
    return $response['body'];
}

/**
 * Pull the Meet link out of a created event.
 */
function vdvCalendarMeetLink(array $event): string
{
    if (!empty($event['hangoutLink'])) {
        return (string) $event['hangoutLink'];
    }
    foreach ($event['conferenceData']['entryPoints'] ?? [] as $entryPoint) {
        if (($entryPoint['entryPointType'] ?? '') === 'video' && !empty($entryPoint['uri'])) {
            return (string) $entryPoint['uri'];
        }
    }
    return '';
}
