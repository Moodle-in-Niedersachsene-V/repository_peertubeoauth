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
 * Cohort to channel mapping for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

/**
 * Data access for the mapping between cohorts and PeerTube channels.
 *
 * A group channel is an ordinary channel inside the shared moderator
 * account. The mapping only decides who finds its videos in the file
 * picker. It grants no rights on the PeerTube side, because every
 * channel belongs to the same account there.
 *
 * Callers that write must enforce their own session key check. The
 * writing methods below are reached exclusively from the cohort channel
 * administration page, which calls require_sesskey() before invoking
 * them.
 */
class cohort_channel {
    /** @var string Name of the mapping table. */
    const TABLE = 'peertubeoauth_cohortchannel';

    /**
     * Return the channel handles of all cohorts a user belongs to.
     *
     * The query joins the mapping against cohort membership directly
     * rather than going through the cohort library, so that it stays a
     * single database round trip.
     *
     * @param int $userid The user to look up.
     * @return array Lower case channel handles, without duplicates.
     */
    public static function get_channels_for_user(int $userid): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }

        $sql = 'SELECT DISTINCT cc.channelname
                  FROM {' . self::TABLE . '} cc
                  JOIN {cohort_members} cm ON cm.cohortid = cc.cohortid
                 WHERE cm.userid = :userid';

        $names = $DB->get_fieldset_sql($sql, ['userid' => $userid]);
        if (!$names) {
            return [];
        }

        $lowered = array_map(function ($name) {
            return \core_text::strtolower(trim($name));
        }, $names);

        return array_values(array_unique(array_filter($lowered)));
    }

    /**
     * Return all mappings together with the name of their cohort.
     *
     * A left join is used on purpose. Should a cohort have been removed
     * without the observer running, the row is still listed and its
     * cohort name stays empty, which the administration page marks as
     * orphaned instead of hiding the row.
     *
     * @return array Mapping records indexed by mapping id.
     */
    public static function get_all(): array {
        global $DB;

        $sql = 'SELECT cc.id, cc.cohortid, cc.channelname, cc.displayname,
                       cc.timecreated, cc.timemodified, c.name AS cohortname
                  FROM {' . self::TABLE . '} cc
             LEFT JOIN {cohort} c ON c.id = cc.cohortid
              ORDER BY c.name, cc.channelname';

        return $DB->get_records_sql($sql);
    }

    /**
     * Return one mapping by its id.
     *
     * @param int $id The mapping id.
     * @return \stdClass|null The record, or null when it does not exist.
     */
    public static function get(int $id): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Return the mapping of one cohort.
     *
     * @param int $cohortid The cohort id.
     * @return \stdClass|null The record, or null when none exists.
     */
    public static function get_by_cohort(int $cohortid): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['cohortid' => $cohortid]);
        return $record ?: null;
    }

    /**
     * Create or update one mapping.
     *
     * The caller must have verified the session key beforehand.
     *
     * @param \stdClass $data Form data with cohortid, channelname and displayname.
     * @return int The id of the stored mapping.
     */
    public static function save(\stdClass $data): int {
        global $DB;

        $now = time();

        $record = new \stdClass();
        $record->cohortid = (int)$data->cohortid;
        $record->channelname = \core_text::strtolower(trim($data->channelname));
        $record->displayname = trim($data->displayname ?? '');
        $record->timemodified = $now;

        if (!empty($data->id)) {
            $record->id = (int)$data->id;
            $DB->update_record(self::TABLE, $record);
            return $record->id;
        }

        $record->timecreated = $now;
        return (int)$DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete one mapping.
     *
     * The caller must have verified the session key beforehand.
     *
     * @param int $id The mapping id.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Delete every mapping of one cohort.
     *
     * This is called from the cohort_deleted event observer, where no
     * session key exists because the deletion happens elsewhere.
     *
     * @param int $cohortid The cohort id.
     * @return void
     */
    public static function delete_by_cohort(int $cohortid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cohortid' => $cohortid]);
    }

    /**
     * Return the cohorts that have no mapping yet.
     *
     * @param int $excludecohortid Cohort to keep in the list when editing.
     * @return array Cohort names indexed by cohort id.
     */
    public static function get_selectable_cohorts(int $excludecohortid = 0): array {
        global $DB;

        $sql = 'SELECT c.id, c.name, c.idnumber
                  FROM {cohort} c
              ORDER BY c.name';
        $cohorts = $DB->get_records_sql($sql);

        $mapped = $DB->get_fieldset_select(self::TABLE, 'cohortid', '');
        $mapped = array_flip(array_map('intval', $mapped ?: []));

        $options = [];
        foreach ($cohorts as $cohort) {
            $id = (int)$cohort->id;
            if (isset($mapped[$id]) && $id !== $excludecohortid) {
                continue;
            }
            $label = format_string($cohort->name);
            if (!empty($cohort->idnumber)) {
                $label .= ' (' . format_string($cohort->idnumber) . ')';
            }
            $options[$id] = $label;
        }

        return $options;
    }
}
