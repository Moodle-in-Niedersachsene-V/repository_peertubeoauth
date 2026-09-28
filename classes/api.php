<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * PeerTube API access for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

/**
 * Thin wrapper around the PeerTube REST API.
 *
 * All methods are static because every value they need comes from the
 * site wide type configuration of the repository, never from a single
 * repository instance. That allows both the repository class and the
 * cohort channel administration page to share one implementation.
 */
class api {
    /** @var string Session key under which the access token is cached. */
    const TOKEN_CACHE_KEY = 'repository_peertubeoauth_token_shared';

    /** @var int Seconds of leeway before a cached token counts as expired. */
    const TOKEN_LEEWAY = 60;

    /** @var int Assumed token lifetime when the API reports none. */
    const TOKEN_DEFAULT_LIFETIME = 3600;

    /** @var int Request timeout in seconds. */
    const TIMEOUT = 15;

    /**
     * Return the configured PeerTube base URL without a trailing slash.
     *
     * @return string|null The base URL, or null when it is unset.
     */
    public static function get_instance_url(): ?string {
        $url = get_config('peertubeoauth', 'instanceurl');
        return $url ? rtrim($url, '/') : null;
    }

    /**
     * Read a value of the shared moderator account from the type config.
     *
     * @param string $name Setting name, either fallbackusername or fallbackpassword.
     * @return string|null The configured value, or null when it is unset.
     */
    public static function get_account_value(string $name): ?string {
        $value = get_config('peertubeoauth', $name);
        return $value !== false && $value !== '' ? $value : null;
    }

    /**
     * Obtain a valid OAuth2 access token for the shared moderator account.
     *
     * The session acts as a cache so that the plugin does not
     * reauthenticate on every single request.
     *
     * @return string|null The access token, or null when unavailable.
     */
    public static function get_token(): ?string {
        $instanceurl = self::get_instance_url();
        $username = self::get_account_value('fallbackusername');
        $password = self::get_account_value('fallbackpassword');

        if (!$instanceurl || !$username || !$password) {
            return null;
        }

        $cached = self::get_cached_token();
        if ($cached !== null) {
            return $cached;
        }

        $clientdata = self::fetch_oauth_client($instanceurl);
        if ($clientdata === null) {
            return null;
        }

        return self::request_token($instanceurl, $clientdata, $username, $password);
    }

    /**
     * Return a still valid access token from the session cache.
     *
     * The token depends only on the shared moderator account, so one
     * cache entry per session is enough for all repository instances.
     *
     * @return string|null The cached token, or null when it is absent or stale.
     */
    private static function get_cached_token(): ?string {
        global $SESSION;

        $cachekey = self::TOKEN_CACHE_KEY;
        if (empty($SESSION->$cachekey)) {
            return null;
        }

        $cached = $SESSION->$cachekey;
        if (empty($cached->expiry) || $cached->expiry <= time() + self::TOKEN_LEEWAY) {
            return null;
        }

        return $cached->access_token;
    }

    /**
     * Fetch the OAuth2 client credentials of the PeerTube instance.
     *
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @return object|null The client credentials, or null on failure.
     */
    private static function fetch_oauth_client(string $instanceurl): ?object {
        $clientdata = self::call($instanceurl . '/api/v1/oauth-clients/local', 'GET');

        if (!$clientdata || empty($clientdata->client_id) || empty($clientdata->client_secret)) {
            debugging(
                'PeerTube OAuth2: failed to fetch client credentials.',
                DEBUG_DEVELOPER
            );
            return null;
        }

        return $clientdata;
    }

    /**
     * Request an access token using the password grant and cache it.
     *
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @param object $clientdata OAuth2 client credentials of the instance.
     * @param string $username Username of the shared moderator account.
     * @param string $password Password of the shared moderator account.
     * @return string|null The access token, or null on failure.
     */
    private static function request_token(
        string $instanceurl,
        object $clientdata,
        string $username,
        string $password
    ): ?string {
        global $SESSION;

        $postfields = [
            'client_id' => $clientdata->client_id,
            'client_secret' => $clientdata->client_secret,
            'grant_type' => 'password',
            'response_type' => 'code',
            'username' => $username,
            'password' => $password,
        ];

        $tokendata = self::call($instanceurl . '/api/v1/users/token', 'POST', $postfields);
        if (!$tokendata || empty($tokendata->access_token)) {
            debugging('PeerTube OAuth2: token request failed.', DEBUG_DEVELOPER);
            return null;
        }

        $cachekey = self::TOKEN_CACHE_KEY;
        $SESSION->$cachekey = (object)[
            'access_token' => $tokendata->access_token,
            'expiry' => time() + (int)($tokendata->expires_in ?? self::TOKEN_DEFAULT_LIFETIME),
        ];

        return $tokendata->access_token;
    }

    /**
     * Perform an HTTP call against the PeerTube API.
     *
     * The bearer token is an explicit parameter rather than being
     * fetched internally. This avoids a recursive token request when
     * the method is called from get_token() itself, because those two
     * calls deliberately pass no token.
     *
     * @param string $url Full request URL.
     * @param string $method Request method, either GET or POST.
     * @param array $postfields Form fields sent with POST requests.
     * @param string|null $bearertoken Access token to send, if any.
     * @return object|null Decoded JSON response, or null on failure.
     */
    public static function call(
        string $url,
        string $method = 'GET',
        array $postfields = [],
        ?string $bearertoken = null
    ): ?object {
        $curl = new \curl();
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        if ($bearertoken) {
            $curl->setHeader(['Authorization: Bearer ' . $bearertoken]);
        }

        if ($method === 'POST') {
            $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
            // The POST body must be an explicitly encoded string, not an array.
            // The cURL extension switches to multipart encoding for arrays.
            // PeerTube rejects multipart bodies with an invalid_client error.
            // PHP_QUERY_RFC1738 encodes special characters exactly once.
            $encoded = http_build_query($postfields, '', '&', PHP_QUERY_RFC1738);
            $response = $curl->post($url, $encoded, $options);
        } else {
            $response = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            debugging(
                'PeerTube OAuth2: cURL error ' . $curl->get_errno() . '.',
                DEBUG_DEVELOPER
            );
            return null;
        }

        $decoded = json_decode($response);
        return $decoded ?: null;
    }

    /**
     * Send a JSON request body to the PeerTube API.
     *
     * PeerTube expects a real JSON document on the channel endpoints.
     * The body is therefore handed to Moodle's cURL wrapper as a raw
     * string together with CURLOPT_CUSTOMREQUEST. Passing an array here
     * would make the cURL extension re-encode the body as
     * multipart/form-data, which PeerTube rejects. This detail is load
     * bearing and must survive any later refactoring.
     *
     * @param string $url Full request URL.
     * @param array $payload Data to send as a JSON document.
     * @param string $token Bearer token of the moderator account.
     * @param string $method HTTP method to use.
     * @return object|null Decoded JSON response, or null on failure.
     */
    public static function send_json(
        string $url,
        array $payload,
        string $token,
        string $method = 'POST'
    ): ?object {
        $body = json_encode($payload);

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($body),
        ]);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => self::TIMEOUT,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_CUSTOMREQUEST' => $method,
        ];

        $response = $curl->post($url, $body, $options);

        if ($curl->get_errno()) {
            debugging(
                'PeerTube OAuth2: cURL error ' . $curl->get_errno() . ' on ' . $method . '.',
                DEBUG_DEVELOPER
            );
            return null;
        }

        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        if ($httpcode < 200 || $httpcode >= 300) {
            debugging('PeerTube OAuth2: HTTP ' . $httpcode . ' on ' . $method . '.', DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode($response);
        return is_object($decoded) ? $decoded : (object)[];
    }

    /**
     * Check whether a channel with the given handle already exists.
     *
     * @param string $handle Channel handle to look up.
     * @return bool True when the channel exists on the instance.
     */
    public static function channel_exists(string $handle): bool {
        $instanceurl = self::get_instance_url();
        if (!$instanceurl) {
            return false;
        }

        $token = self::get_token();
        if (!$token) {
            return false;
        }

        $url = $instanceurl . '/api/v1/video-channels/' . rawurlencode($handle);
        $data = self::call($url, 'GET', [], $token);

        return $data !== null && !empty($data->id);
    }

    /**
     * Create a video channel under the shared moderator account.
     *
     * @param string $handle URL safe channel handle.
     * @param string $displayname Human readable channel name.
     * @return bool True when the channel exists afterwards.
     */
    public static function create_channel(string $handle, string $displayname): bool {
        $instanceurl = self::get_instance_url();
        if (!$instanceurl) {
            return false;
        }

        $token = self::get_token();
        if (!$token) {
            return false;
        }

        $payload = [
            'name' => $handle,
            'displayName' => $displayname,
        ];

        $result = self::send_json(
            $instanceurl . '/api/v1/video-channels',
            $payload,
            $token
        );

        return $result !== null;
    }

    /**
     * Build a PeerTube channel handle from arbitrary text.
     *
     * PeerTube accepts lower case letters, digits, underscores and dots.
     * Hyphens are rejected even though they look URL safe, so they are
     * mapped to underscores like spaces are.
     *
     * @param string $text Source text, for example a cohort name.
     * @param string $prefix Optional prefix such as the school code.
     * @return string|null The handle, or null when nothing usable remains.
     */
    public static function build_handle(string $text, string $prefix = ''): ?string {
        $handle = $prefix !== '' ? $prefix . '_' . $text : $text;
        $handle = mb_strtolower($handle, 'UTF-8');
        $handle = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' ', '-'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue', '_', '_'],
            $handle
        );

        $handle = preg_replace('/[^a-z0-9_\.]/', '', $handle);
        $handle = preg_replace('/_+/', '_', $handle);
        $handle = trim($handle, '_');

        return $handle ?: null;
    }
}
