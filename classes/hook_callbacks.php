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
 * Hook callback implementations for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

use core\hook\output\before_http_headers;

/**
 * Hook callbacks for the PeerTube OAuth2 repository plugin.
 *
 * The before_http_headers hook fires on every Moodle page before output
 * starts. It is used to inject an AMD module that rewrites PeerTube
 * watch and embed links into responsive iframes on the client side.
 *
 * The instance URL is passed as a JavaScript configuration value, so
 * that no PeerTube domain is hardcoded anywhere in the plugin.
 */
class hook_callbacks {
    /** @var string Default embed parameters used when none are configured. */
    const DEFAULT_EMBED_PARAMS = 'peertubeLink=0&p2p=0&warningTitle=0';

    /**
     * Inject the link rewriting AMD module before HTTP headers are sent.
     *
     * Reads the configured PeerTube instance URL and the embed URL
     * parameters, then hands both to the AMD module, which scans the
     * rendered page and converts matching anchor tags into iframes.
     *
     * @param before_http_headers $hook The hook instance provided by Moodle.
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;

        // Act only when a real page is rendered, not on AJAX or CLI requests.
        if (!$PAGE->has_set_url()) {
            return;
        }

        $instanceurl = get_config('peertubeoauth', 'instanceurl');
        if (empty($instanceurl)) {
            // The plugin is not configured yet, so there is nothing to do.
            return;
        }

        $instanceurl = rtrim($instanceurl, '/');

        // PeerTube embed URLs always follow the pattern below.
        $embedbase = $instanceurl . '/videos/embed/';

        // Embed parameters control viewer privacy and player chrome.
        // The default disables P2P so viewer IP addresses stay private.
        $embedparams = get_config('peertubeoauth', 'embedparams');
        if ($embedparams === false || $embedparams === null || $embedparams === '') {
            $embedparams = self::DEFAULT_EMBED_PARAMS;
        }

        // Strip a leading question mark or ampersand an admin may have typed.
        $embedparams = ltrim($embedparams, '?&');

        $config = [
            'instanceUrl' => $instanceurl,
            'embedBase' => $embedbase,
            'embedParams' => $embedparams,
        ];

        $PAGE->requires->js_call_amd(
            'repository_peertubeoauth/embed_links',
            'init',
            [$config]
        );
    }
}
