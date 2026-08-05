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

$string['channelinfo'] = 'Enter the name of your personal PeerTube channel here (within the shared moderator account). You will then only see videos from that channel in the file picker. Leave empty to see all videos of the moderator account.';
$string['channelname'] = 'PeerTube channel name';
$string['channelname_help'] = 'The technical name (URL identifier) of your channel on PeerTube, for example "ms_smith". You can find it in the PeerTube address of your channel, or ask your administrator.';
$string['configplugin'] = 'PeerTube (OAuth2) configuration';
$string['embedparams'] = 'Embed URL parameters';
$string['embedparams_help'] = 'Query parameters appended to every embedded video by the fallback renderer. Separate multiple parameters with an ampersand. The default is peertubeLink=0&p2p=0&warningTitle=0. It disables P2P so that viewer IP addresses are not shared with other peers, which is recommended for pupils, and it removes the PeerTube link and the IP warning banner from the player. Add title=0 to hide the title overlay as well. Parameters already present on a link are kept.';
$string['enablecourseinstances'] = 'Allow course PeerTube instances';
$string['enableuserinstances'] = 'Allow user PeerTube instances';
$string['fallbackheader'] = 'Shared moderator account. All teachers share this single PeerTube account for login. Each teacher instead gets an own channel within this account, see below.';
$string['fallbackpassword'] = 'Moderator account password';
$string['fallbackusername'] = 'Moderator account username';
$string['fallbackusername_help'] = 'Username of the shared PeerTube moderator account that all teachers log in through.';
$string['instanceurl'] = 'PeerTube instance URL';
$string['instanceurl_help'] = 'Address of the PeerTube instance of your school, for example https://peertube.example-school.org.';
$string['peertubeoauth:view'] = 'Use PeerTube (OAuth2) repository';
$string['pluginname'] = 'PeerTube (OAuth2)';
$string['privacy:metadata'] = 'The PeerTube (OAuth2) repository plugin does not store any personal data. Credentials are site wide administrator settings, and the access token is kept in the session only.';
$string['privacy_private'] = 'Private';
$string['privacy_public'] = 'Public';
$string['privacy_unlisted'] = 'Unlisted';
$string['privatewarning'] = '(cannot be played, please switch to unlisted)';
$string['schoolcode'] = 'School code';
$string['schoolcode_help'] = 'Short identifier for this school, for example "gms_sample". It is used automatically as a prefix in PeerTube channel names created by the upload plugin for teachers, for example "gms_sample_smith_jane". Only letters, numbers and underscores are allowed.';
$string['untitled'] = 'Untitled video';
