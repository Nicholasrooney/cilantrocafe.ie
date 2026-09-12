<?php
/*
 * Minimal Google Calendar API client for a service account.
 *
 * Deliberately has no Composer dependency: Hostinger shared hosting has no
 * Composer, so the service-account JWT is signed here with openssl_sign and
 * the REST calls go out over curl. Needs the openssl and curl extensions,
 * both standard on Hostinger.
 *
 * This file knows about Google and nothing about bookings. The mapping from a
 * booking to a calendar event lives in calendar-sync.php.
 */

const GCAL_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GCAL_API_BASE  = 'https://www.googleapis.com/calendar/v3';
const GCAL_SCOPE     = 'https://www.googleapis.com/auth/calendar.events';

class GoogleCalendarError extends RuntimeException {}

/**
 * base64url, as required by JWT — standard base64 with +/ swapped and = stripped.
 */
function gcal_b64url(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Reads and validates the service-account JSON key file.
 */
function gcal_load_key(string $path): array
{
    if (!is_readable($path)) {
        throw new GoogleCalendarError("Service account key not readable at: $path");
    }

    $key = json_decode((string) file_get_contents($path), true);
    if (!is_array($key)) {
        throw new GoogleCalendarError('Service account key is not valid JSON.');
    }

    foreach (['client_email', 'private_key'] as $field) {
        if (empty($key[$field])) {
            throw new GoogleCalendarError("Service account key is missing '$field'.");
        }
    }

    return $key;
}

/**
 * Exchanges the service-account key for an access token.
 *
 * Tokens last an hour, so they are cached on disk for 55 minutes. Without the
 * cache every booking would cost an extra round trip to Google.
 */
function gcal_access_token(array $key, string $cacheFile): string
{
    if (is_readable($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)
            && ($cached['email'] ?? '') === $key['client_email']
            && ($cached['expires'] ?? 0) > time()
            && !empty($cached['token'])) {
            return $cached['token'];
        }
    }

    $now    = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $key['client_email'],
        'scope' => GCAL_SCOPE,
        'aud'   => GCAL_TOKEN_URL,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];

    $input = gcal_b64url((string) json_encode($header))
           . '.' . gcal_b64url((string) json_encode($claims));

    $signature = '';
    if (!openssl_sign($input, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new GoogleCalendarError('Could not sign the JWT. Check the private key in the JSON file.');
    }

    $assertion = $input . '.' . gcal_b64url($signature);

    [$status, $body] = gcal_http('POST', GCAL_TOKEN_URL, null, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $assertion,
    ]), 'application/x-www-form-urlencoded');

    $data = json_decode($body, true);
    if ($status !== 200 || empty($data['access_token'])) {
        $detail = $data['error_description'] ?? $data['error'] ?? substr($body, 0, 300);
        throw new GoogleCalendarError("Google refused the token request (HTTP $status): $detail");
    }

    $cacheDir = dirname($cacheFile);
    if (is_dir($cacheDir) || mkdir($cacheDir, 0750, true)) {
        @file_put_contents($cacheFile, json_encode([
            'email'   => $key['client_email'],
            'token'   => $data['access_token'],
            'expires' => $now + 3300,   // 55 minutes
        ]), LOCK_EX);
        @chmod($cacheFile, 0600);
    }

    return $data['access_token'];
}

/**
 * One HTTP call. Returns [status, body].
 *
 * Timeouts are short and deliberate: a slow Google must never hold up somebody
 * booking a table.
 */
function gcal_http(string $method, string $url, ?string $token, ?string $body = null, string $contentType = 'application/json'): array
{
    $ch = curl_init($url);

    $headers = ['Content-Type: ' . $contentType];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new GoogleCalendarError("Could not reach Google: $error");
    }

    return [$status, (string) $response];
}

/**
 * Creates an event. Returns the Google event id.
 */
function gcal_insert_event(array $config, array $event): string
{
    $data = gcal_call($config, 'POST', '/calendars/' . rawurlencode($config['calendar_id']) . '/events', $event);
    return (string) $data['id'];
}

/**
 * Updates an existing event in place.
 */
function gcal_update_event(array $config, string $eventId, array $event): void
{
    gcal_call($config, 'PUT',
        '/calendars/' . rawurlencode($config['calendar_id']) . '/events/' . rawurlencode($eventId),
        $event);
}

/**
 * Removes an event. An already-deleted event is treated as success.
 */
function gcal_delete_event(array $config, string $eventId): void
{
    gcal_call($config, 'DELETE',
        '/calendars/' . rawurlencode($config['calendar_id']) . '/events/' . rawurlencode($eventId),
        null, [404, 410]);
}

/**
 * Reads the calendar's own metadata. Used by the test page to prove that the
 * service account can actually see the calendar.
 */
function gcal_get_calendar(array $config): array
{
    return gcal_call($config, 'GET', '/calendars/' . rawurlencode($config['calendar_id']));
}

/**
 * Shared request path: get a token, call the API, decode, raise on failure.
 *
 * @param int[] $tolerate HTTP statuses to treat as success.
 */
function gcal_call(array $config, string $method, string $path, ?array $payload = null, array $tolerate = []): array
{
    if (empty($config['calendar_id'])) {
        throw new GoogleCalendarError('No calendar_id set in config.php.');
    }

    $key   = gcal_load_key($config['key_file']);
    $token = gcal_access_token($key, $config['token_cache']);

    [$status, $body] = gcal_http(
        $method,
        GCAL_API_BASE . $path,
        $token,
        $payload === null ? null : (string) json_encode($payload)
    );

    if ($status >= 200 && $status < 300) {
        return json_decode($body, true) ?: [];
    }

    if (in_array($status, $tolerate, true)) {
        return [];
    }

    $data   = json_decode($body, true);
    $detail = $data['error']['message'] ?? substr($body, 0, 300);

    if ($status === 404) {
        $detail .= ' — check the calendar ID, and that the calendar is shared with '
                 . $key['client_email'] . ' with "Make changes to events".';
    }
    if ($status === 403) {
        $detail .= ' — the service account can see the calendar but lacks write access. '
                 . 'Re-share it with "Make changes to events".';
    }

    throw new GoogleCalendarError("Google Calendar API error (HTTP $status): $detail");
}
