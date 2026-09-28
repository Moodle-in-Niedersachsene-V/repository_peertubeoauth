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
 * Form for editing one cohort to channel mapping.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth\form;

/**
 * Edit form for a single cohort to channel mapping.
 *
 * The form library is loaded by the calling page, so that this class
 * file itself stays free of side effects.
 */
class cohort_channel_form extends \moodleform {
    /**
     * Assemble the form elements.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $cohorts = $this->_customdata['cohorts'] ?? [];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'edit');
        $mform->setType('action', PARAM_ALPHA);

        if (empty($cohorts)) {
            $mform->addElement(
                'static',
                'nocohorts',
                '',
                get_string('nocohortsavailable', 'repository_peertubeoauth')
            );
            $this->add_action_buttons(true, get_string('savechanges'));
            return;
        }

        $mform->addElement(
            'select',
            'cohortid',
            get_string('cohort', 'repository_peertubeoauth'),
            $cohorts
        );
        $mform->setType('cohortid', PARAM_INT);
        $mform->addRule('cohortid', null, 'required', null, 'client');
        $mform->addHelpButton('cohortid', 'cohort', 'repository_peertubeoauth');

        $mform->addElement(
            'text',
            'channelname',
            get_string('groupchannelname', 'repository_peertubeoauth'),
            ['size' => 40]
        );
        $mform->setType('channelname', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('channelname', 'groupchannelname', 'repository_peertubeoauth');

        $mform->addElement(
            'text',
            'displayname',
            get_string('groupchanneldisplayname', 'repository_peertubeoauth'),
            ['size' => 40]
        );
        $mform->setType('displayname', PARAM_TEXT);
        $mform->addHelpButton('displayname', 'groupchanneldisplayname', 'repository_peertubeoauth');

        $mform->addElement(
            'advcheckbox',
            'createchannel',
            get_string('createchannel', 'repository_peertubeoauth')
        );
        $mform->setType('createchannel', PARAM_BOOL);
        $mform->setDefault('createchannel', 1);
        $mform->addHelpButton('createchannel', 'createchannel', 'repository_peertubeoauth');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate the submitted values.
     *
     * An empty channel name is allowed and is derived from the cohort
     * afterwards. A name that was typed must follow the character set
     * PeerTube accepts for channel handles.
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Validation errors indexed by element name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $channelname = trim($data['channelname'] ?? '');
        if ($channelname !== '' && !preg_match('/^[a-z0-9_\.]+$/', $channelname)) {
            $errors['channelname'] = get_string('errorchannelname', 'repository_peertubeoauth');
        }

        return $errors;
    }
}
