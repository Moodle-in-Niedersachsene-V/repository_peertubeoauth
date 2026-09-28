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
 * Event observers for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

/**
 * Observers keeping the cohort channel mapping consistent.
 */
class observer {
    /**
     * Remove the mapping of a cohort that has just been deleted.
     *
     * Only the Moodle side mapping is removed. The channel itself stays
     * on PeerTube together with its videos, because deleting it would
     * break every link that has already been embedded in a course.
     *
     * @param \core\event\cohort_deleted $event The event carrying the cohort id.
     * @return void
     */
    public static function cohort_deleted(\core\event\cohort_deleted $event): void {
        cohort_channel::delete_by_cohort((int)$event->objectid);
    }
}
