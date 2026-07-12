<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Hook callback implementations for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

/**
 * Hook callbacks for the PeerTube OAuth2 repository plugin.
 *
 * The before_http_headers hook fires on every Moodle page before output
 * starts. We use it to inject an AMD module that rewrites PeerTube
 * watch/embed links into responsive iframes client-side.
 *
 * The instance URL is passed as a JS configuration value so that no
 * hardcoded PeerTube domain appears anywhere in the plugin code.
 */
class hook_callbacks {

    /**
     * Called before HTTP headers are sent on every Moodle page.
     *
     * Reads the configured PeerTube instance URL and, if set, passes it
     * to the AMD module repository_peertubeoauth/embed_links together
     * with the derived embed URL base. The AMD module then scans the
     * rendered DOM and converts matching anchor tags into iframes.
     *
     * @param \core\hook\output\before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(
            \core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        // Only act when a full page is being rendered (not AJAX, CLI, etc.).
        if (!$PAGE->has_set_url()) {
            return;
        }

        $instanceurl = get_config('peertubeoauth', 'instanceurl');
        if (empty($instanceurl)) {
            // Plugin not configured – nothing to do.
            return;
        }

        $instanceurl = rtrim($instanceurl, '/');

        // Derive the embed base URL from the instance URL.
        // PeerTube embed URLs follow the pattern: {instanceurl}/videos/embed/{uuid}
        $embedbase = $instanceurl . '/videos/embed/';

        // Pass configuration to the AMD module and require it.
        $PAGE->requires->js_call_amd(
            'repository_peertubeoauth/embed_links',
            'init',
            [[
                'instanceUrl' => $instanceurl,
                'embedBase'   => $embedbase,
            ]]
        );
    }
}
