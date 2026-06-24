<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * PeerTube OAuth2 repository.
 *
 * Authenticates against a PeerTube instance using the OAuth2 "Resource
 * Owner Password Credentials" grant and lists videos (public, unlisted,
 * private) belonging to a single shared "moderator" account.
 *
 * Configuration is split across two levels:
 * - TYPE level (admin-wide, Site administration > Repositories): the
 *   PeerTube instance URL plus the credentials of ONE shared moderator
 *   account for the whole school. All authentication happens through
 *   this single account.
 * - INSTANCE level (per-user or per-course, configured from within the
 *   file picker itself via "Configure this repository instance"): a
 *   PeerTube CHANNEL NAME. Each teacher creates their own video channel
 *   under the shared moderator account on PeerTube (e.g. "frau-mueller"),
 *   uploads their videos there, and enters that channel name here. They
 *   will then only see videos from their own channel in the picker. If
 *   no channel name is configured, all videos of the moderator account
 *   are shown (across all channels).
 */
class repository_peertubeoauth extends repository {

    /** Privacy level constants, matching the PeerTube API. */
    const PRIVACY_PUBLIC   = 1;
    const PRIVACY_UNLISTED = 2;
    const PRIVACY_PRIVATE  = 3;

    /**
     * Read the shared moderator account credentials (type-level config).
     *
     * @param string $name  'fallbackusername' or 'fallbackpassword'
     * @return string|null
     */
    private function get_account_value(string $name): ?string {
        $value = get_config('peertubeoauth', $name);
        return $value !== false && $value !== '' ? $value : null;
    }

    /**
     * Return the PeerTube channel name configured for THIS repository
     * instance (e.g. a teacher's personal instance), if any. Used to
     * filter the video listing down to just that channel.
     *
     * @return string|null
     */
    private function get_channel_filter(): ?string {
        $channel = $this->options['channelname'] ?? '';
        $channel = trim($channel);
        return $channel !== '' ? $channel : null;
    }

    /**
     * Return the configured PeerTube base URL, without trailing slash.
     * Always read from the admin (type-level) config - the instance URL
     * is centrally fixed per school, not per teacher.
     *
     * @return string|null
     */
    private function get_instance_url(): ?string {
        $url = get_config('peertubeoauth', 'instanceurl');
        return $url ? rtrim($url, '/') : null;
    }

    /**
     * Obtain a valid OAuth2 access token for the shared moderator
     * account, using the session as a cache so we don't re-authenticate
     * on every single picker request.
     *
     * @return string|null  Access token, or null if unavailable.
     */
    private function get_access_token(): ?string {
        global $SESSION;

        $instanceurl = $this->get_instance_url();
        $username = $this->get_account_value('fallbackusername');
        $password = $this->get_account_value('fallbackpassword');

        if (!$instanceurl || !$username || !$password) {
            return null;
        }

        // The token only depends on the shared moderator account, not on
        // which Moodle user or repository instance is asking - safe to
        // cache once per session regardless of channel filter.
        $cachekey = 'repository_peertubeoauth_token_shared';

        if (!empty($SESSION->$cachekey)) {
            $cached = $SESSION->$cachekey;
            if (!empty($cached->expiry) && $cached->expiry > time() + 60) {
                return $cached->access_token;
            }
        }

        // Step 1: fetch OAuth2 client_id / client_secret for this instance.
        $clientdata = $this->api_call($instanceurl . '/api/v1/oauth-clients/local', 'GET');
        if (!$clientdata || empty($clientdata->client_id) || empty($clientdata->client_secret)) {
            debugging('PeerTube OAuth2: failed to fetch client credentials from ' . $instanceurl,
                DEBUG_DEVELOPER);
            return null;
        }

        // Step 2: request an access token using the password grant.
        $postfields = [
            'client_id'     => $clientdata->client_id,
            'client_secret' => $clientdata->client_secret,
            'grant_type'    => 'password',
            'response_type' => 'code',
            'username'      => $username,
            'password'      => $password,
        ];

        $tokendata = $this->api_call($instanceurl . '/api/v1/users/token', 'POST', $postfields);
        if (!$tokendata || empty($tokendata->access_token)) {
            debugging('PeerTube OAuth2: token request failed for user ' . $username, DEBUG_DEVELOPER);
            return null;
        }

        $SESSION->$cachekey = (object)[
            'access_token' => $tokendata->access_token,
            'expiry'       => time() + (int)($tokendata->expires_in ?? 3600),
        ];

        return $tokendata->access_token;
    }

    /**
     * Perform an HTTP call against the PeerTube API.
     *
     * Takes the bearer token as an explicit parameter rather than
     * fetching it itself - this avoids a recursive/redundant token
     * request when called from within get_access_token() for the
     * client-credentials and token-request steps (those calls pass
     * no token).
     *
     * @param string $url     Full request URL
     * @param string $method  'GET' or 'POST'
     * @param array  $postfields  Form fields for POST requests
     * @param string|null $bearertoken  Token to send, if any
     * @return object|null  Decoded JSON response, or null on failure
     */
    private function api_call(string $url, string $method = 'GET', array $postfields = [],
            ?string $bearertoken = null): ?object {

        $curl = new curl();
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT'        => 15,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        if ($bearertoken) {
            $curl->setHeader(['Authorization: Bearer ' . $bearertoken]);
        }

        if ($method === 'POST') {
            $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
            // IMPORTANT: pass an explicitly URL-encoded STRING, not an
            // array. PHP's cURL extension silently switches CURLOPT_POSTFIELDS
            // to multipart/form-data encoding whenever it is given an array,
            // regardless of the Content-Type header set above. PeerTube's
            // OAuth2 endpoint only accepts true x-www-form-urlencoded
            // bodies and responds with "invalid_client" otherwise. Using
            // PHP_QUERY_RFC1738 ensures special characters (e.g. '#' in
            // a password) are percent-encoded correctly exactly once.
            $encoded = http_build_query($postfields, '', '&', PHP_QUERY_RFC1738);
            $response = $curl->post($url, $encoded, $options);
        } else {
            $response = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            debugging('PeerTube OAuth2: cURL error ' . $curl->get_errno() . ' for ' . $url,
                DEBUG_DEVELOPER);
            return null;
        }

        $decoded = json_decode($response);
        return $decoded ?: null;
    }

    /**
     * Return the video listing shown in the file picker.
     *
     * Uses /api/v1/users/me/videos (authenticated as the shared moderator
     * account), which returns ALL of that account's videos across ALL of
     * its channels, regardless of privacy level. If this repository
     * instance has a channel name configured, the results are filtered
     * down to that channel only (done client-side in PHP, since
     * filtering the authenticated "my videos" endpoint by channel via
     * PeerTube's API was unreliable in testing - some PeerTube versions
     * only return public videos when querying a channel directly).
     * Falls back to the public search endpoint if no account is
     * configured at all.
     *
     * @param string $path
     * @param string $page
     * @return array
     */
    public function get_listing($path = '', $page = '') {
        $list = [
            'list'      => [],
            'path'      => [['name' => get_string('pluginname', 'repository_peertubeoauth'), 'path' => '']],
            'dynload'   => true,
            'nologin'   => true,
            'norefresh' => false,
            'nosearch'  => false,
        ];

        $instanceurl = $this->get_instance_url();
        if (!$instanceurl) {
            return $list;
        }

        $channelfilter = $this->get_channel_filter();

        // When filtering by channel, fetch a larger page from PeerTube
        // before filtering client-side, since we don't know in advance
        // how many of the moderator account's videos belong to this
        // particular channel.
        $perpage = $channelfilter ? 100 : 30;
        $start = $channelfilter ? 0 : max(0, ((int)$page - 1) * $perpage);

        $token = $this->get_access_token();

        if ($token) {
            // Authenticated as the shared moderator account: all videos
            // across all of its channels, including private/unlisted.
            $url = $instanceurl . '/api/v1/users/me/videos?' . http_build_query([
                'start' => $start,
                'count' => $perpage,
                'sort'  => '-publishedAt',
            ]);
            $data = $this->api_call($url, 'GET', [], $token);
        } else {
            // No account configured at all: public videos only.
            $url = $instanceurl . '/api/v1/search/videos?' . http_build_query([
                'start'        => $start,
                'count'        => $perpage,
                'sort'         => '-publishedAt',
                'privacyOneOf' => self::PRIVACY_PUBLIC,
            ]);
            $data = $this->api_call($url, 'GET');
        }

        if (!$data || empty($data->data)) {
            return $list;
        }

        $videos = $data->data;
        if ($channelfilter) {
            $videos = array_values(array_filter($videos, function ($video) use ($channelfilter) {
                $channelname = $video->channel->name ?? '';
                return strcasecmp($channelname, $channelfilter) === 0;
            }));
        }

        foreach ($videos as $video) {
            $list['list'][] = $this->video_to_listitem($video, $instanceurl);
        }

        if ($channelfilter) {
            // Already filtered down to a single page worth of results.
            $list['pages'] = 1;
        } else if (!empty($data->total)) {
            $list['pages'] = (int)ceil($data->total / $perpage);
        }

        return $list;
    }

    /**
     * Convert one PeerTube API video object into a Moodle file-picker item.
     *
     * @param object $video
     * @param string $instanceurl
     * @return array
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
            self::PRIVACY_PUBLIC   => get_string('privacy_public', 'repository_peertubeoauth'),
            self::PRIVACY_UNLISTED => get_string('privacy_unlisted', 'repository_peertubeoauth'),
            self::PRIVACY_PRIVATE  => get_string('privacy_private', 'repository_peertubeoauth'),
        ];
        $privacylabel = $privacylabels[$privacylevel] ?? '';

        // Private PeerTube videos cannot be embedded/played by anonymous
        // viewers (e.g. students browsing the Moodle course) - PeerTube
        // itself rejects unauthenticated embed requests for private
        // content with an "authentication required" error. We still list
        // them (so teachers know they exist and can be re-shared), but
        // mark them clearly so nobody embeds a video that will not play.
        // The warning is added to title, shorttitle AND thumbnail_title,
        // because different file picker views (list vs. grid) display
        // different fields - the grid view shown by default uses
        // shorttitle, not title.
        $displaytitle = $title . ' [' . $privacylabel . ']';
        $shorttitle = $title;
        if ($privacylevel === self::PRIVACY_PRIVATE) {
            $warning = get_string('privatewarning', 'repository_peertubeoauth');
            $displaytitle = '⚠ ' . $displaytitle . ' ' . $warning;
            $shorttitle = '⚠ ' . $title;
        }

        return [
            'title'           => $displaytitle,
            'shorttitle'      => $shorttitle,
            'date'            => !empty($video->publishedAt) ? strtotime($video->publishedAt) : time(),
            'size'            => 0,
            'thumbnail'       => $thumbnail,
            'thumbnail_title' => $displaytitle,
            'source'          => $embedurl,
            'url'             => $embedurl,
            'icon'            => $thumbnail,
        ];
    }

    public function check_login() {
        return true;
    }

    public function global_search() {
        return false;
    }

    /**
     * This repository only ever returns links to the PeerTube embed page,
     * never downloaded copies of the video. FILE_INTERNAL is deliberately
     * NOT supported - PeerTube videos can be large and/or private, and
     * copying them into Moodle's file storage would be both wasteful and
     * would bypass PeerTube's own access control for private content.
     */
    public function supported_returntypes() {
        return FILE_EXTERNAL;
    }

    /**
     * Explicitly support all file types - some file picker contexts
     * filter repositories out if this is restricted to an unrecognised
     * mimetype group.
     */
    public function supported_filetypes() {
        return '*';
    }

    /**
     * Type-level (admin-wide) options: the PeerTube instance URL plus the
     * credentials of the single shared moderator account used for ALL
     * authentication.
     *
     * IMPORTANT: field names here must be DISTINCT from the names used
     * in get_instance_option_names() below, to avoid Moodle colliding
     * the two configuration scopes when saving (this previously
     * triggered a fatal error). The account fields therefore keep their
     * historical 'fallback'-prefixed names even though they are no
     * longer an optional fallback but the primary (only) account.
     */
    public static function get_type_option_names() {
        return [
            'instanceurl',
            'fallbackusername',
            'fallbackpassword',
            'schoolcode',
            'pluginname',
            'enablecourseinstances',
            'enableuserinstances',
        ];
    }

    /**
     * Instance-level (per-user or per-course) option: the PeerTube
     * channel name to filter this instance's video listing down to.
     * Authentication itself always uses the single shared moderator
     * account (type-level config) - this is purely a display filter.
     */
    public static function get_instance_option_names() {
        return ['channelname'];
    }

    /**
     * Admin settings form shown under Site administration >
     * Plugins > Repositories > Manage repositories > Settings.
     *
     * Configures the PeerTube instance URL and the credentials of the
     * single shared "moderator" account used for ALL authentication.
     * Individual teachers do not log in separately - they instead
     * filter the shared account's videos down to their own channel via
     * instance_config_form() (see below).
     */
    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform, $classname);

        $mform->addElement('text', 'instanceurl',
            get_string('instanceurl', 'repository_peertubeoauth'), ['size' => 50]);
        $mform->setType('instanceurl', PARAM_URL);
        $mform->addHelpButton('instanceurl', 'instanceurl', 'repository_peertubeoauth');

        $mform->addElement('static', 'fallbackheader', '',
            get_string('fallbackheader', 'repository_peertubeoauth'));

        $mform->addElement('text', 'fallbackusername',
            get_string('fallbackusername', 'repository_peertubeoauth'));
        $mform->setType('fallbackusername', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('fallbackusername', 'fallbackusername', 'repository_peertubeoauth');

        $mform->addElement('passwordunmask', 'fallbackpassword',
            get_string('fallbackpassword', 'repository_peertubeoauth'));
        $mform->setType('fallbackpassword', PARAM_RAW);

        $mform->addElement('text', 'schoolcode',
            get_string('schoolcode', 'repository_peertubeoauth'), ['size' => 20]);
        $mform->setType('schoolcode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('schoolcode', 'schoolcode', 'repository_peertubeoauth');

        // NOTE: 'enablecourseinstances' and 'enableuserinstances' checkboxes
        // are NOT added manually here. Moodle auto-renders them from
        // get_type_option_names() - adding them again here duplicated the
        // checkboxes on the settings page.
    }

    /**
     * Per-instance settings form. Teachers (or course managers) reach
     * this when creating their own repository instance via the file
     * picker or their Moodle profile/course settings (enabled via
     * 'enableuserinstances'/'enablecourseinstances' above).
     *
     * Rather than separate login credentials, each instance is
     * configured with the name of a PeerTube CHANNEL that belongs to
     * the shared moderator account. Teachers upload their videos to
     * their own channel on PeerTube; this instance then only shows
     * videos from that channel.
     *
     * IMPORTANT: must be declared STATIC. The parent class
     * (repository::instance_config_form) is a static method; overriding
     * it as a non-static method causes a PHP fatal error ("Cannot make
     * non static method ... static") at class-load time, which is silent
     * when display_errors is off and looks like an unrelated HTTP 500.
     */
    public static function instance_config_form($mform) {
        global $USER;

        $mform->addElement('static', 'channelinfo', '',
            get_string('channelinfo', 'repository_peertubeoauth'));

        $mform->addElement('text', 'channelname',
            get_string('channelname', 'repository_peertubeoauth'));
        $mform->setType('channelname', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('channelname', 'channelname', 'repository_peertubeoauth');

        // Pre-fill with the auto-generated channel handle (schoolcode +
        // lastname + firstname) if a school code is configured, so
        // teachers see the correct value without having to type it.
        $schoolcode = get_config('peertubeoauth', 'schoolcode');
        if ($schoolcode && !empty($USER->lastname)) {
            $suggested = self::build_channel_handle_for_user($USER, $schoolcode);
            if ($suggested) {
                // setDefault() only fills in values when no value is already
                // set; updateAttributes sets the HTML 'value' attribute
                // directly, which works reliably for new (empty) instances.
                $mform->setDefault('channelname', $suggested);
                $element = $mform->getElement('channelname');
                if ($element && empty($element->getValue())) {
                    $element->updateAttributes(['value' => $suggested]);
                }
            }
        }
    }

    /**
     * Build a canonical PeerTube channel handle for a given user.
     * Extracted as a static helper so it can be called from both
     * instance_config_form() (static context) and from the upload
     * plugin's API class.
     *
     * @param stdClass $user
     * @param string   $schoolcode
     * @return string|null
     */
    public static function build_channel_handle_for_user(\stdClass $user, string $schoolcode): ?string {
        $handle = $schoolcode . '_' . $user->lastname . '_' . $user->firstname;
        $handle = mb_strtolower($handle, 'UTF-8');
        $handle = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü', ' ', '-'],
            ['ae', 'oe', 'ue', 'ss', 'ae', 'oe', 'ue', '_', '_'],
            $handle
        );
        // PeerTube channel names allow only letters, digits, underscores
        // and dots - hyphens are NOT allowed despite looking URL-safe.
        $handle = preg_replace('/[^a-z0-9_\.]/', '', $handle);
        $handle = preg_replace('/_+/', '_', $handle); // collapse multiple underscores
        $handle = trim($handle, '_');
        return $handle ?: null;
    }
}
