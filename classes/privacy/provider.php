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
 * Privacy provider for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth\privacy;

/**
 * Privacy provider implementation for repository_peertubeoauth.
 *
 * This plugin stores no personal data of its own. All credentials are
 * site-wide administrator settings, and the OAuth2 access token is kept
 * only in the volatile Moodle session, never in the database.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Return the language string identifier explaining why this plugin
     * stores no personal data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
