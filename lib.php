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
 *
 * On top of the personal channel, an instance also shows the group
 * channels of every cohort the current user belongs to. That mapping
 * is maintained by an administrator and lives in the table
 * repository_peertubeoauth_cohortchannel.
 *
 * All channels belong to the same PeerTube account, so this separation
 * decides who finds a video in the file picker, not who may play it.
 * Videos stay unlisted rather than private, because PeerTube refuses
 * to serve private videos to viewers who are not logged in there.
 */
class repository_peertubeoauth extends repository {
    /** @var int Privacy level of public videos, matching the PeerTube API. */
    const PRIVACY_PUBLIC = 1;

    /** @var int Privacy level of unlisted videos, matching the PeerTube API. */
    const PRIVACY_UNLISTED = 2;

    /** @var int Privacy level of private videos, matching the PeerTube API. */
    const PRIVACY_PRIVATE = 3;

    /** @var int Number of videos shown on one file picker page. */
    const PAGE_SIZE = 30;

    /** @var int Number of videos fetched per API request when filtering. */
    const PAGE_SIZE_FILTERED = 100;

    /** @var int Safety limit on API requests made for one filtered listing. */
    const MAX_FETCH_PAGES = 20;

    /** @var string Session key under which the access token is cached. */
    const TOKEN_CACHE_KEY = \repository_peertubeoauth\api::TOKEN_CACHE_KEY;

    /** @var int Seconds of leeway before a cached token counts as expired. */
    const TOKEN_LEEWAY = \repository_peertubeoauth\api::TOKEN_LEEWAY;

    /** @var int Assumed token lifetime when the API reports none. */
    const TOKEN_DEFAULT_LIFETIME = \repository_peertubeoauth\api::TOKEN_DEFAULT_LIFETIME;

    /**
     * Return the PeerTube channels this instance should show.
     *
     * The personal channel of the instance is the starting point. Every
     * group channel of a cohort the current user belongs to is added to
     * it, so that shared videos appear next to the own ones.
     *
     * An instance without a personal channel is left unfiltered on
     * purpose. Such an instance already shows every video of the shared
     * moderator account, so applying cohort channels there would hide
     * videos that are visible today rather than reveal additional ones.
     *
     * @return array Lower case channel handles. An empty array means no filter.
     */
    private function get_channel_filters(): array {
        global $USER;

        $personal = trim($this->options['channelname'] ?? '');
        if ($personal === '') {
            return [];
        }

        $channels = [\core_text::strtolower($personal)];

        $cohortchannels = \repository_peertubeoauth\cohort_channel::get_channels_for_user(
            (int)$USER->id
        );

        foreach ($cohortchannels as $channel) {
            $channels[] = $channel;
        }

        return array_values(array_unique($channels));
    }
    /**
     * Return the video listing shown in the file picker.
     *
     * The listing uses the authenticated endpoint of the shared
     * moderator account, which returns all of its videos across all
     * channels regardless of privacy level. Filtering through the
     * PeerTube API proved unreliable, because some versions return
     * public videos only when a channel is queried directly, so the
     * channel restriction is applied in PHP. Without any configured
     * account the plugin falls back to the public search endpoint.
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

        $instanceurl = \repository_peertubeoauth\api::get_instance_url();
        if (!$instanceurl) {
            return $list;
        }

        $page = max(1, (int)$page);
        $channelfilters = $this->get_channel_filters();

        if (!$channelfilters) {
            return $this->get_unfiltered_listing($list, $instanceurl, $page);
        }

        return $this->get_filtered_listing($list, $instanceurl, $page, $channelfilters);
    }

    /**
     * Build a listing that shows every video of the shared account.
     *
     * One API page maps directly onto one file picker page here, so the
     * total reported by PeerTube can be used as it is.
     *
     * @param array $list The prepared listing skeleton.
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @param int $page Requested page number, starting at one.
     * @return array The completed listing structure.
     */
    private function get_unfiltered_listing(array $list, string $instanceurl, int $page): array {
        $start = ($page - 1) * self::PAGE_SIZE;

        $data = $this->fetch_videos($instanceurl, $start, self::PAGE_SIZE);
        if (!$data || empty($data->data)) {
            return $list;
        }

        foreach ($data->data as $video) {
            $list['list'][] = $this->video_to_listitem($video, $instanceurl);
        }

        $total = (int)($data->total ?? 0);
        $list['pages'] = $total > 0 ? (int)ceil($total / self::PAGE_SIZE) : 1;

        return $list;
    }

    /**
     * Build a listing restricted to a set of channels.
     *
     * Because the restriction happens in PHP, the matching videos have
     * to be collected across as many API pages as needed before the
     * requested page can be cut out of them.
     *
     * @param array $list The prepared listing skeleton.
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @param int $page Requested page number, starting at one.
     * @param array $channelfilters Lower case channel handles to keep.
     * @return array The completed listing structure.
     */
    private function get_filtered_listing(
        array $list,
        string $instanceurl,
        int $page,
        array $channelfilters
    ): array {
        $matches = $this->collect_matching_videos($instanceurl, $channelfilters);
        if (!$matches) {
            return $list;
        }

        $offset = ($page - 1) * self::PAGE_SIZE;
        foreach (array_slice($matches, $offset, self::PAGE_SIZE) as $video) {
            $list['list'][] = $this->video_to_listitem($video, $instanceurl);
        }

        $list['pages'] = max(1, (int)ceil(count($matches) / self::PAGE_SIZE));

        return $list;
    }

    /**
     * Collect every video of the account that belongs to the channels.
     *
     * The account is walked page by page until PeerTube reports no more
     * videos. Without this loop the listing would silently drop older
     * videos as soon as the account holds more of them than a single
     * request returns. MAX_FETCH_PAGES caps the effort on very large
     * accounts; reaching it is reported through debugging().
     *
     * The number of configured channels does not influence the number
     * of API requests, because all channels live in the same account
     * and are therefore covered by the same walk.
     *
     * @param string $instanceurl PeerTube base URL without trailing slash.
     * @param array $channelfilters Lower case channel handles to keep.
     * @return array The matching video objects in API order.
     */
    private function collect_matching_videos(string $instanceurl, array $channelfilters): array {
        $matches = [];
        $start = 0;
        $seen = 0;
        $total = 0;

        for ($request = 0; $request < self::MAX_FETCH_PAGES; $request++) {
            $data = $this->fetch_videos($instanceurl, $start, self::PAGE_SIZE_FILTERED);
            if (!$data || empty($data->data)) {
                return $matches;
            }

            if ($request === 0) {
                $total = (int)($data->total ?? 0);
            }

            $matches = array_merge(
                $matches,
                $this->filter_by_channel($data->data, $channelfilters)
            );

            $received = count($data->data);
            $seen += $received;
            $start += self::PAGE_SIZE_FILTERED;

            if ($received < self::PAGE_SIZE_FILTERED) {
                return $matches;
            }

            if ($total > 0 && $seen >= $total) {
                return $matches;
            }
        }

        debugging(
            'PeerTube OAuth2: stopped after ' . self::MAX_FETCH_PAGES . ' requests, listing may be incomplete.',
            DEBUG_DEVELOPER
        );

        return $matches;
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
        $token = \repository_peertubeoauth\api::get_token();

        if ($token) {
            $url = $instanceurl . '/api/v1/users/me/videos?' . http_build_query([
                'start' => $start,
                'count' => $perpage,
                'sort' => '-publishedAt',
            ]);
            return \repository_peertubeoauth\api::call($url, 'GET', [], $token);
        }

        $url = $instanceurl . '/api/v1/search/videos?' . http_build_query([
            'start' => $start,
            'count' => $perpage,
            'sort' => '-publishedAt',
            'privacyOneOf' => self::PRIVACY_PUBLIC,
        ]);
        return \repository_peertubeoauth\api::call($url, 'GET');
    }

    /**
     * Reduce a list of videos to those belonging to the given channels.
     *
     * The stored handles are already lower case, so the comparison is
     * done on a lower case copy of the channel name rather than through
     * a case insensitive string comparison per entry.
     *
     * @param array $videos Videos as returned by the PeerTube API.
     * @param array $channelfilters Lower case channel handles to keep.
     * @return array The filtered list of videos.
     */
    private function filter_by_channel(array $videos, array $channelfilters): array {
        if (!$channelfilters) {
            return $videos;
        }

        $matching = array_filter($videos, function ($video) use ($channelfilters) {
            $channelname = \core_text::strtolower($video->channel->name ?? '');
            return in_array($channelname, $channelfilters, true);
        });

        return array_values($matching);
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

        // The group channel administration lives on its own page.
        // It manages database rows rather than plugin settings.
        // The link is placed here on purpose.
        // This form is where an administrator already looks.
        $manageurl = new \moodle_url('/repository/peertubeoauth/managecohortchannels.php');
        $mform->addElement(
            'static',
            'managecohortchannels',
            get_string('groupchannels', 'repository_peertubeoauth'),
            \html_writer::link(
                $manageurl,
                get_string('managecohortchannels', 'repository_peertubeoauth')
            )
        );

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
