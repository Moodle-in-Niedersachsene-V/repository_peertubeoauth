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
 * English strings for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmapping'] = 'Add group channel';
$string['channelcreated'] = 'The channel "{$a}" was created on PeerTube.';
$string['channelexists'] = 'The channel "{$a}" already exists on PeerTube and was reused.';
$string['channelinfo'] = 'Enter the name of your personal PeerTube channel here (within the shared moderator account). You will then see videos from that channel in the file picker, together with the videos of any group channel your cohorts are assigned to. Leave empty to see all videos of the moderator account.';
$string['channelname'] = 'PeerTube channel name';
$string['channelname_help'] = 'The technical name (URL identifier) of your channel on PeerTube, for example "ms_smith". You can find it in the PeerTube address of your channel, or ask your administrator.';
$string['cohort'] = 'Cohort';
$string['cohort_help'] = 'Members of this cohort will see the videos of the assigned channel in the file picker. Only cohorts without an assignment are listed.';
$string['cohortchannelintro'] = 'A group channel is an ordinary channel inside the shared moderator account. Members of the assigned cohort find its videos in the file picker, in addition to their own channel. Because all channels belong to the same PeerTube account, this assignment decides who finds a video, not who may play it.';
$string['configplugin'] = 'PeerTube (OAuth2) configuration';
$string['confirmdeletemapping'] = 'Remove this group channel assignment? The channel and its videos stay on PeerTube, and links already embedded in courses keep working.';
$string['createchannel'] = 'Create the channel on PeerTube';
$string['createchannel_help'] = 'If the channel does not exist yet, it is created under the shared moderator account. Leave this ticked unless you created the channel on PeerTube by hand.';
$string['embedparams'] = 'Embed URL parameters';
$string['embedparams_help'] = 'Query parameters appended to every embedded video by the fallback renderer. Separate multiple parameters with an ampersand. The default is peertubeLink=0&p2p=0&warningTitle=0. It disables P2P so that viewer IP addresses are not shared with other peers, which is recommended for pupils, and it removes the PeerTube link and the IP warning banner from the player. Add title=0 to hide the title overlay as well. Parameters already present on a link are kept.';
$string['enablecourseinstances'] = 'Allow course PeerTube instances';
$string['enableuserinstances'] = 'Allow user PeerTube instances';
$string['errorchannelcreate'] = 'The assignment was saved, but the channel "{$a}" could not be created on PeerTube. Please create it there by hand, or check the moderator account settings.';
$string['errorchannelname'] = 'A channel name may only contain lower case letters, digits, underscores and dots. Leave the field empty to derive it from the cohort name.';
$string['errorcohortmissing'] = 'The selected cohort no longer exists.';
$string['errornotconfigured'] = 'The PeerTube instance URL is not configured yet. Group channels cannot be created on PeerTube until it is set.';
$string['fallbackheader'] = 'Shared moderator account. All teachers share this single PeerTube account for login. Each teacher instead gets an own channel within this account, see below.';
$string['fallbackpassword'] = 'Moderator account password';
$string['fallbackusername'] = 'Moderator account username';
$string['fallbackusername_help'] = 'Username of the shared PeerTube moderator account that all teachers log in through.';
$string['groupchanneldisplayname'] = 'Display name on PeerTube';
$string['groupchanneldisplayname_help'] = 'The readable name shown for this channel on PeerTube. Leave empty to use the cohort name.';
$string['groupchannelname'] = 'Channel name';
$string['groupchannelname_help'] = 'The technical channel handle on PeerTube. Only lower case letters, digits, underscores and dots are allowed; hyphens are not. Leave empty to derive the handle from the school code and the cohort name.';
$string['groupchannels'] = 'Group channels';
$string['instanceurl'] = 'PeerTube instance URL';
$string['instanceurl_help'] = 'Address of the PeerTube instance of your school, for example https://peertube.example-school.org.';
$string['managecohortchannels'] = 'Manage group channels';
$string['mappingdeleted'] = 'The group channel assignment was removed.';
$string['mappingsaved'] = 'The group channel assignment was saved.';
$string['nocohortsavailable'] = 'There is no cohort left without an assignment. Create a cohort first, or edit an existing assignment.';
$string['nomappings'] = 'No group channels have been assigned yet.';
$string['orphanedmapping'] = 'Cohort deleted';
$string['peertubeoauth:view'] = 'Use PeerTube (OAuth2) repository';
$string['pluginname'] = 'PeerTube (OAuth2)';
$string['privacy:metadata'] = 'The PeerTube (OAuth2) repository plugin does not store any personal data. Credentials are site wide administrator settings, the access token is kept in the session only, and the group channel table stores a cohort reference together with a channel name rather than data about individual users.';
$string['privacy_private'] = 'Private';
$string['privacy_public'] = 'Public';
$string['privacy_unlisted'] = 'Unlisted';
$string['privatewarning'] = '(cannot be played, please switch to unlisted)';
$string['schoolcode'] = 'School code';
$string['schoolcode_help'] = 'Short identifier for this school, for example "gms_sample". It is used automatically as a prefix in PeerTube channel names created by the upload plugin for teachers, for example "gms_sample_smith_jane". Only letters, numbers and underscores are allowed.';
$string['untitled'] = 'Untitled video';
