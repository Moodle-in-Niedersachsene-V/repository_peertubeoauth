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
 * PeerTube OAuth2 repository plugin.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Repository class listing PeerTube videos in the Moodle file picker.
 *
 * Authentication uses the OAuth2 resource owner password credentials
 * grant against one shared moderator account. Configuration is split
 * across two levels.
 *
 * At type level, set site wide by an administrator, the plugin stores
 * the PeerTube instance URL and the credentials of the single shared
 * moderator account used for the whole school.
 *
 * At instance level, configured per user or per course, the plugin
 * stores a PeerTube channel name. Each teacher owns a channel inside
 * the shared moderator account and sees only videos from that channel.
 * When no channel name is set, all videos of the account are listed.
 */
class repository_peertubeoauth extends repository {
    /** @var int Privacy level of public videos, matching the PeerTube API. */
    const PRIVACY_PUBLIC = 1;

    /** @var int Privacy level of unlisted videos, matching the PeerTube API. */
    const PRIVACY_UNLISTED = 2;

    /** @var int Privacy level of private videos, matching the PeerTube API. */
    const PRIVACY_PRIVATE = 3;

    /** @var int Number of videos fetched per request without a channel filter. */
    const PAGE_SIZE = 30;

    /** @var int Number of videos fetched per request when filtering by channel. */
    const PAGE_SIZE_FILTERED = 100;

    /** @var string Session key under which the access token is cached. */
    const TOKEN_CACHE_KEY = 'repository_peertubeoauth_token_shared';

    /** @var int Seconds of leeway before a cached token counts as expired. */
    const TOKEN_LEEWAY = 60;

    /** @var int Assumed token lifetime when the API reports none. */
    const TOKEN_DEFAULT_LIFETIME = 3600;

    /**
     * Read a value of the shared moderator account from the type config.
     *
     * @param string $name Setting name, either fallbackusername or fallbackpassword.
     * @return string|null The configured value, or null when it is unset.
     */
    private function get_account_value(string $name): ?string {
        $value = get_config('peertubeoauth', $name);
        return $value !== false && $value !== '' ? $value : null;
    }

    /**
     * Return the PeerTube channel name configured for this instance.
     *
     * The value is used to filter the video listing down to one channel.
     *
     * @return string|null The channel name, or null when none is set.
     */
    private function get_channel_filter(): ?string {
        $channel = $this->options['channelname'] ?? '';
        $channel = trim($channel);
        return $channel !== '' ? $channel : null;
    }

    /**
     * Return the configured PeerTube base URL without a trailing slash.
     *
     * The URL is always read from the type level config, because it is
     * fixed centrally per school rather than per teacher.
     *
     * @return string|null The base URL, or null when it is unset.
     */
    private function get_instance_url(): ?string {
        $url = get_config('peertubeoauth', 'instanceurl');
        return $url ? rtrim($url, '/') : null;
    }

    /**
     * Obtain a valid OAuth2 access token for the shared moderator account.
     *
     * The session acts as a cache so that the plugin does not
     * reauthenticate on every single file picker request.
     *
     * @return string|null The access token, or null when unavailable.
     */
    private function get_access_token(): ?string {
        $instanceurl = $this->get_instance_url();
        $username = $this->get_account_value('fallbackusername');
        $password = $this->get_account_value('fallbackpassword');

        if (!$instanceurl || !$username || !$password) {
            return null;
        }

        $cached = $this->get_cached_token();
        if ($cached !== null) {
            return $cached;
        }

        $clientdata = $this->fetch_oauth_client($instanceurl);
        if ($clientdata === null) {
            return null;
        }

        return $this->request_token($instanceurl, $clientdata, $username, $password);
    }

    /**
     * Return a still valid access token from the session cache.
     *
     * The token depends only on the shared moderator account, so one
     * cache entry per session is enough for all repository instances.
     *
     * @return string|null The cached token, or null when it is absent or stale.
     */
    private function get_cached_token(): ?string {
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
    private function fetch_oauth_client(string $instanceurl): ?object {
        $clientdata = $this->api_call($instanceurl . '/api/v1/oauth-clients/local', 'GET');

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
    private function request_token(
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

        $tokendata = $this->api_call($instanceurl . '/api/v1/users/token', 'POST', $postfields);
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
     * the method is called from get_access_token() itself, because
     * those two calls deliberately pass no token.
     *
     * @param string $url Full request URL.
     * @param string $method Request method, either GET or POST.
     * @param array $postfields Form fields sent with POST requests.
     * @param string|null $bearertoken Access token to send, if any.
     * @return object|null Decoded JSON response, or null on failure.
     */
    private function api_call(
        string $url,
        string $method = 'GET',
        array $postfields = [],
        ?string $bearertoken = null
    ): ?object {
        $curl = new curl();
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 15,
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
     * Return the video listing shown in the file picker.
     *
     * The listing uses the authenticated endpoint of the shared
     * moderator account, which returns all of its videos across all
     * channels regardless of privacy level. When this instance has a
     * channel name configured, the results are filtered down to that
     * channel in PHP. Filtering through the PeerTube API proved
     * unreliable, because some versions return public videos only when
     * a channel is queried directly. Without any configured account the
     * plugin falls back to the public search endpoint.
     *
     * @param string $path Folder path, unused by this repository.
     * @param string $page Requested page number as a string.
     * @return array The file picker listing structure.
     */
    public function get_listing($path = '', $page = '') {
        // The path parameter is part of the parent signature only.
        // This repository presents a flat list without folders.
        unset($path);

        $list = $this->empty_listing();

        $instanceurl = $this->get_instance_url();
        if (!$instanceurl) {
            return $list;
        }

        $channelfilter = $this->get_channel_filter();

        // A larger page is fetched before the filtering step.
        // The number of videos per channel is not known in advance.
        $perpage = $channelfilter ? self::PAGE_SIZE_FILTERED : self::PAGE_SIZE;
        $start = $channelfilter ? 0 : max(0, ((int)$page - 1) * $perpage);

        $data = $this->fetch_videos($instanceurl, $start, $perpage);
        if (!$data || empty($data->data)) {
            return $list;
        }

        $videos = $this->filter_by_channel($data->data, $channelfilter);
        foreach ($videos as $video) {
            $list['list'][] = $this->video_to_listitem($video, $instanceurl);
        }

        $list['pages'] = $this->count_pages($data, $perpage, $channelfilter);

        return $list;
    }

    /**
     * Build the empty skeleton of a file picker listing.
     *
     * @return array The listing structure without any entries.
     */
    private function empty_listing(): array {
        return [
            'list' => [],
            'path' => [
                [
                    'name' => get_string('pluginname', 'repository_peertubeoauth'),
                    'path' => '',
                ],
            ],
            'dynload' => true,
            'nologin' => true,
            'norefresh' => false,
            'nosearch' => false,
        ];
    }

    /**
     * Fetch one page of videos from the PeerTube API.
     *
     * With a valid token the authenticated endpoint is used, which also
     * returns unlisted and private videos. Without a token the plugin
     * falls back to the public search endpoint.
     *
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @param int $start Index of the first video to return.
     * @param int $perpage Number of videos to request.
     * @return object|null Decoded API response, or null on failure.
     */
    private function fetch_videos(string $instanceurl, int $start, int $perpage): ?object {
        $token = $this->get_access_token();

        if ($token) {
            $url = $instanceurl . '/api/v1/users/me/videos?' . http_build_query([
                'start' => $start,
                'count' => $perpage,
                'sort' => '-publishedAt',
            ]);
            return $this->api_call($url, 'GET', [], $token);
        }

        $url = $instanceurl . '/api/v1/search/videos?' . http_build_query([
            'start' => $start,
            'count' => $perpage,
            'sort' => '-publishedAt',
            'privacyOneOf' => self::PRIVACY_PUBLIC,
        ]);
        return $this->api_call($url, 'GET');
    }

    /**
     * Reduce a list of videos to those belonging to one channel.
     *
     * @param array $videos Videos as returned by the PeerTube API.
     * @param string|null $channelfilter Channel name, or null for no filter.
     * @return array The filtered list of videos.
     */
    private function filter_by_channel(array $videos, ?string $channelfilter): array {
        if (!$channelfilter) {
            return $videos;
        }

        $matching = array_filter($videos, function ($video) use ($channelfilter) {
            $channelname = $video->channel->name ?? '';
            return strcasecmp($channelname, $channelfilter) === 0;
        });

        return array_values($matching);
    }

    /**
     * Determine the number of pages reported to the file picker.
     *
     * @param object $data Decoded API response.
     * @param int $perpage Number of videos per page.
     * @param string|null $channelfilter Channel name, or null for no filter.
     * @return int The page count.
     */
    private function count_pages(object $data, int $perpage, ?string $channelfilter): int {
        if ($channelfilter) {
            // The filtered result already fits on a single page.
            return 1;
        }

        if (empty($data->total)) {
            return 1;
        }

        return (int)ceil($data->total / $perpage);
    }

    /**
     * Convert one PeerTube API video object into a file picker item.
     *
     * @param object $video Video object as returned by the PeerTube API.
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @return array The file picker item structure.
     */
    private function video_to_listitem(object $video, string $instanceurl): array {
        $uuid = $video->uuid ?? $video->shortUUID ?? '';
        $title = $video->name ?? get_string('untitled', 'repository_peertubeoauth');
        $embedurl = $instanceurl . '/videos/embed/' . $uuid;

        $thumbnail = '';
        if (!empty($video->thumbnailPath)) {
            $thumbnail = $instanceurl . $video->thumbnailPath;
        }

        $privacylevel = (int)($video->privacy->id ?? self::PRIVACY_PUBLIC);
        $privacylabels = [
            self::PRIVACY_PUBLIC => get_string('privacy_public', 'repository_peertubeoauth'),
            self::PRIVACY_UNLISTED => get_string('privacy_unlisted', 'repository_peertubeoauth'),
            self::PRIVACY_PRIVATE => get_string('privacy_private', 'repository_peertubeoauth'),
        ];
        $privacylabel = $privacylabels[$privacylevel] ?? '';

        // Private videos cannot be played by anonymous viewers.
        // PeerTube rejects unauthenticated embed requests for them.
        // They are still listed so that teachers know they exist.
        // The marker is written into three separate fields.
        // The grid view of the file picker shows shorttitle only.
        $displaytitle = $title . ' [' . $privacylabel . ']';
        $shorttitle = $title;
        if ($privacylevel === self::PRIVACY_PRIVATE) {
            $warning = get_string('privatewarning', 'repository_peertubeoauth');
            $displaytitle = '⚠ ' . $displaytitle . ' ' . $warning;
            $shorttitle = '⚠ ' . $title;
        }

        return [
            'title' => $displaytitle,
            'shorttitle' => $shorttitle,
            'date' => !empty($video->publishedAt) ? strtotime($video->publishedAt) : time(),
            'size' => 0,
            'thumbnail' => $thumbnail,
            'thumbnail_title' => $displaytitle,
            'source' => $embedurl,
            'url' => $embedurl,
            'icon' => $thumbnail,
        ];
    }

    /**
     * Report that no interactive login is required for this repository.
     *
     * @return bool Always true, because a shared account is used.
     */
    public function check_login() {
        return true;
    }

    /**
     * Report that this repository is excluded from global search.
     *
     * @return bool Always false.
     */
    public function global_search() {
        return false;
    }

    /**
     * Return the supported return types of this repository.
     *
     * Only external links are supported. Internal copies are not
     * offered on purpose, because PeerTube videos can be large and
     * copying them into Moodle would bypass the access control of
     * PeerTube for private content.
     *
     * @return int The FILE_EXTERNAL constant.
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * Return the supported file types of this repository.
     *
     * All types are accepted, because some file picker contexts hide
     * repositories that restrict themselves to an unknown mimetype group.
     *
     * @return string The wildcard for all file types.
     */
    public function supported_filetypes() {
        return '*';
    }

    /**
     * Return the names of the site wide type level settings.
     *
     * The names must differ from the instance level names returned by
     * get_instance_option_names(), because Moodle otherwise mixes the
     * two configuration scopes when saving. The account fields keep
     * their historical fallback prefix for that reason.
     *
     * @return array List of setting names.
     */
    public static function get_type_option_names() {
        return [
            'instanceurl',
            'fallbackusername',
            'fallbackpassword',
            'schoolcode',
            'embedparams',
            'pluginname',
            'enablecourseinstances',
            'enableuserinstances',
        ];
    }

    /**
     * Return the names of the per user or per course settings.
     *
     * Authentication always uses the shared moderator account, so the
     * channel name is purely a display filter.
     *
     * @return array List of setting names.
     */
    public static function get_instance_option_names() {
        return ['channelname'];
    }

    /**
     * Build the site wide settings form of this repository type.
     *
     * @param MoodleQuickForm $mform The form to add the elements to.
     * @param string $classname Name of the repository class.
     * @return void
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform, $classname);

        $mform->addElement(
            'text',
            'instanceurl',
            get_string('instanceurl', 'repository_peertubeoauth'),
            ['size' => 50]
        );
        $mform->setType('instanceurl', PARAM_URL);
        $mform->addHelpButton('instanceurl', 'instanceurl', 'repository_peertubeoauth');

        $mform->addElement(
            'static',
            'fallbackheader',
            '',
            get_string('fallbackheader', 'repository_peertubeoauth')
        );

        $mform->addElement(
            'text',
            'fallbackusername',
            get_string('fallbackusername', 'repository_peertubeoauth')
        );
        $mform->setType('fallbackusername', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('fallbackusername', 'fallbackusername', 'repository_peertubeoauth');

        $mform->addElement(
            'passwordunmask',
            'fallbackpassword',
            get_string('fallbackpassword', 'repository_peertubeoauth')
        );
        $mform->setType('fallbackpassword', PARAM_RAW);

        $mform->addElement(
            'text',
            'schoolcode',
            get_string('schoolcode', 'repository_peertubeoauth'),
            ['size' => 20]
        );
        $mform->setType('schoolcode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('schoolcode', 'schoolcode', 'repository_peertubeoauth');

        // These parameters are appended to every embedded video.
        // They are applied by the renderer in amd/src/embed_links.js.
        // The default disables P2P and hides the player chrome.
        $mform->addElement(
            'text',
            'embedparams',
            get_string('embedparams', 'repository_peertubeoauth'),
            ['size' => 50]
        );
        $mform->setType('embedparams', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('embedparams', 'embedparams', 'repository_peertubeoauth');
        $mform->setDefault('embedparams', \repository_peertubeoauth\hook_callbacks::DEFAULT_EMBED_PARAMS);

        // The instance checkboxes are not added here on purpose.
        // Moodle renders them automatically from get_type_option_names().
        // Adding them again would duplicate them on the settings page.
    }

    /**
     * Build the per user or per course settings form of one instance.
     *
     * Instead of separate credentials, each instance stores the name of
     * a PeerTube channel belonging to the shared moderator account.
     *
     * This method must be declared static. The parent method is static,
     * and a non static override raises a fatal error at class load time
     * that is silent when display_errors is off.
     *
     * @param MoodleQuickForm $mform The form to add the elements to.
     * @return void
     */
    public static function instance_config_form($mform) {
        global $USER;

        $mform->addElement(
            'static',
            'channelinfo',
            '',
            get_string('channelinfo', 'repository_peertubeoauth')
        );

        $mform->addElement(
            'text',
            'channelname',
            get_string('channelname', 'repository_peertubeoauth')
        );
        $mform->setType('channelname', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('channelname', 'channelname', 'repository_peertubeoauth');

        // The field is prefilled with the generated channel handle.
        // Teachers then see the correct value without typing it.
        $schoolcode = get_config('peertubeoauth', 'schoolcode');
        if ($schoolcode && !empty($USER->lastname)) {
            $suggested = self::build_channel_handle_for_user($USER, $schoolcode);
            if ($suggested) {
                // The setDefault call only fills in empty values.
                // The updateAttributes call sets the value attribute.
                $mform->setDefault('channelname', $suggested);
                $element = $mform->getElement('channelname');
                if ($element && empty($element->getValue())) {
                    $element->updateAttributes(['value' => $suggested]);
                }
            }
        }
    }

    /**
     * Build the canonical PeerTube channel handle of a given user.
     *
     * The helper is static so that it can be called both from the
     * static instance_config_form() and from the upload plugin.
     *
     * @param stdClass $user The Moodle user record.
     * @param string $schoolcode Short identifier of the school.
     * @return string|null The channel handle, or null when it is empty.
     */
    public static function build_channel_handle_for_user(\stdClass $user, string $schoolcode): ?string {
        $handle = $schoolcode . '_' . $user->lastname . '_' . $user->firstname;
        $handle = mb_strtolower($handle, 'UTF-8');
        $handle = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' ', '-'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue', '_', '_'],
            $handle
        );

        // PeerTube channel names allow letters, digits, underscores and dots.
        // Hyphens are not allowed even though they look URL safe.
        $handle = preg_replace('/[^a-z0-9_\.]/', '', $handle);

        // Collapse repeated underscores into a single one.
        $handle = preg_replace('/_+/', '_', $handle);
        $handle = trim($handle, '_');
        return $handle ?: null;
    }
}
