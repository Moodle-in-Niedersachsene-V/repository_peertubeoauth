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
 * Administration page for cohort based PeerTube group channels.
 *
 * Every data changing branch on this page calls require_sesskey()
 * before it reaches the storage class. The storage class itself
 * performs no session check, because it is also used by the event
 * observer, where no session key exists.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use repository_peertubeoauth\api;
use repository_peertubeoauth\cohort_channel;
use repository_peertubeoauth\form\cohort_channel_form;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);

$baseurl = new moodle_url('/repository/peertubeoauth/managecohortchannels.php');

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('managecohortchannels', 'repository_peertubeoauth'));
$PAGE->set_heading(get_string('managecohortchannels', 'repository_peertubeoauth'));

// Deleting a mapping. The channel on PeerTube is deliberately kept.
if ($action === 'delete' && $id > 0) {
    require_sesskey();

    $existing = cohort_channel::get($id);
    if ($existing) {
        cohort_channel::delete($id);
    }

    redirect(
        $baseurl,
        get_string('mappingdeleted', 'repository_peertubeoauth'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Creating or editing a mapping.
if ($action === 'edit') {
    $record = $id > 0 ? cohort_channel::get($id) : null;
    $excludecohortid = $record ? (int)$record->cohortid : 0;

    $form = new cohort_channel_form(
        $baseurl,
        ['cohorts' => cohort_channel::get_selectable_cohorts($excludecohortid)]
    );

    if ($record) {
        $form->set_data($record);
    }

    if ($form->is_cancelled()) {
        redirect($baseurl);
    }

    $data = $form->get_data();
    if ($data) {
        require_sesskey();

        $cohort = $DB->get_record('cohort', ['id' => (int)$data->cohortid], '*', IGNORE_MISSING);
        if (!$cohort) {
            redirect(
                $baseurl,
                get_string('errorcohortmissing', 'repository_peertubeoauth'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        // An empty channel name is derived from the cohort name.
        // The administrator then need not know the character rules.
        $channelname = trim($data->channelname ?? '');
        if ($channelname === '') {
            $schoolcode = (string)get_config('peertubeoauth', 'schoolcode');
            $channelname = (string)api::build_handle($cohort->name, $schoolcode);
        }

        if ($channelname === '') {
            redirect(
                $baseurl,
                get_string('errorchannelname', 'repository_peertubeoauth'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $displayname = trim($data->displayname ?? '');
        if ($displayname === '') {
            $displayname = $cohort->name;
        }

        $data->channelname = $channelname;
        $data->displayname = $displayname;
        cohort_channel::save($data);

        $message = get_string('mappingsaved', 'repository_peertubeoauth');
        $messagetype = \core\output\notification::NOTIFY_SUCCESS;

        // Creating the channel is a best effort step.
        // A failure here must not discard the stored mapping.
        // The channel may exist already or be created by hand later.
        if (!empty($data->createchannel)) {
            if (api::channel_exists($channelname)) {
                $message = get_string('channelexists', 'repository_peertubeoauth', $channelname);
            } else if (api::create_channel($channelname, $displayname)) {
                $message = get_string('channelcreated', 'repository_peertubeoauth', $channelname);
            } else {
                $message = get_string('errorchannelcreate', 'repository_peertubeoauth', $channelname);
                $messagetype = \core\output\notification::NOTIFY_WARNING;
            }
        }

        redirect($baseurl, $message, null, $messagetype);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managecohortchannels', 'repository_peertubeoauth'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Listing all mappings.
$mappings = cohort_channel::get_all();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecohortchannels', 'repository_peertubeoauth'));
echo $OUTPUT->notification(
    get_string('cohortchannelintro', 'repository_peertubeoauth'),
    \core\output\notification::NOTIFY_INFO
);

if (!api::get_instance_url()) {
    echo $OUTPUT->notification(
        get_string('errornotconfigured', 'repository_peertubeoauth'),
        \core\output\notification::NOTIFY_ERROR
    );
}

if ($mappings) {
    $table = new html_table();
    $table->head = [
        get_string('cohort', 'repository_peertubeoauth'),
        get_string('groupchannelname', 'repository_peertubeoauth'),
        get_string('groupchanneldisplayname', 'repository_peertubeoauth'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($mappings as $mapping) {
        $cohortname = $mapping->cohortname !== null
            ? format_string($mapping->cohortname)
            : html_writer::span(
                get_string('orphanedmapping', 'repository_peertubeoauth'),
                'text-danger'
            );

        $editurl = new moodle_url($baseurl, ['action' => 'edit', 'id' => $mapping->id]);
        $deleteurl = new moodle_url($baseurl, [
            'action' => 'delete',
            'id' => $mapping->id,
            'sesskey' => sesskey(),
        ]);

        $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit')));
        $actions .= $OUTPUT->action_icon(
            $deleteurl,
            new pix_icon('t/delete', get_string('delete')),
            new confirm_action(get_string('confirmdeletemapping', 'repository_peertubeoauth'))
        );

        $table->data[] = [
            $cohortname,
            s($mapping->channelname),
            format_string($mapping->displayname ?? ''),
            $actions,
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(
        get_string('nomappings', 'repository_peertubeoauth'),
        \core\output\notification::NOTIFY_INFO
    );
}

echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'edit']),
    get_string('addmapping', 'repository_peertubeoauth'),
    'get'
);

echo $OUTPUT->footer();
