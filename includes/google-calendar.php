<?php
/**
 * Minimal Google Calendar client for the appointment booker.
 *
 * Talks to the Calendar REST API directly with a service-account JWT
 * (domain-wide delegation, impersonating the Workspace user in
 * booking_impersonate) instead of pulling in google/apiclient. Only three
 * calls are needed: token exchange, freeBusy.query and events.insert.
 *
 * The service-account key lives in config.php under 'google_service_account'
 * (the decoded contents of the JSON key file). Set 'booking_disable_google'
 * to true for local testing: availability then acts as a fully free
 * calendar and event creation is logged instead of executed.
 *
 * Requires includes/form-helpers.php for vdvPostForm / vdvHttpJson.
 */

declare(strict_types=1);

const VDV_GOOGLE_SCOPES = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.freebusy';

function vdvGoogleCredentials(array $config): ?array
{
    $credentials = $config['google_service_account'] ?? null;
    if (!is_array($credentials)
        || empty($credentials['client_email'])
        || empty($credentials['private_key'])
    ) {
        return null;
    }
    return $credentials;
}

function vdvGoogleBase64Url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Get an access token for the impersonated user, cached in
 * data/google-token.json until shortly before expiry.
 */
function vdvGoogleAccessToken(array $config): ?string
{
    $credentials = vdvGoogleCredentials($config);
    if ($credentials === null) {
        error_log('VDV booking: google_service_account is missing or incomplete in config.php');
        return null;
    }

    $cacheFile = dirname(__DIR__) . '/data/google-token.json';
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)
            && !empty($cached['token'])
            && ($cached['expires'] ?? 0) > time() + 60
            && ($cached['sub'] ?? '') === $config['booking_impersonate']
        ) {
            return $cached['token'];
        }
    }

    $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
    $now      = time();

    $claims = [
        'iss'   => $credentials['client_email'],
        'sub'   => $config['booking_impersonate'],
        'scope' => VDV_GOOGLE_SCOPES,
        'aud'   => $tokenUri,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];

    $signingInput = vdvGoogleBase64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
        . '.' . vdvGoogleBase64Url((string) json_encode($claims));

    $signature = '';
    if (!openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
        error_log('VDV booking: could not sign the Google JWT (check private_key in config.php)');
        return null;
    }

    $response = vdvPostForm($tokenUri, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $signingInput . '.' . vdvGoogleBase64Url($signature),
    ]);

    $token = $response['access_token'] ?? null;
    if (!is_string($token) || $token === '') {
        error_log('VDV booking: Google token exchange failed: ' . json_encode($response));
        return null;
    }

    $dataDir = dirname(__DIR__) . '/data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0700, true);
    }
    @file_put_contents($cacheFile, json_encode([
        'token'   => $token,
        'expires' => $now + (int) ($response['expires_in'] ?? 3600),
        'sub'     => $config['booking_impersonate'],
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
