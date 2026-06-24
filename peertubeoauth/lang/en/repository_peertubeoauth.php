<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PeerTube (OAuth2)';
$string['configplugin'] = 'PeerTube (OAuth2) configuration';

// Admin / type level.
$string['instanceurl'] = 'PeerTube instance URL';
$string['instanceurl_help'] = 'Address of your school\'s PeerTube instance, e.g. https://peertube.example-school.org.';
$string['fallbackheader'] = 'Shared moderator account. All teachers share this single PeerTube account for login. Each teacher instead gets their own channel within this account (see below).';
$string['fallbackusername'] = 'Moderator account username';
$string['fallbackusername_help'] = 'Username of the shared PeerTube moderator account that all teachers log in through.';
$string['fallbackpassword'] = 'Moderator account password';

$string['schoolcode'] = 'School code';
$string['schoolcode_help'] = 'Short identifier for this school, e.g. "gms_sample". Used automatically as a prefix in PeerTube channel names created by the upload plugin for teachers (e.g. "gms_sample_smith_jane"). Only letters, numbers and underscores allowed.';

// User / instance level.
$string['channelinfo'] = 'Enter the name of your personal PeerTube channel here (within the shared moderator account). You will then only see videos from that channel in the file picker. Leave empty to see all videos of the moderator account.';
$string['channelname'] = 'PeerTube channel name';
$string['channelname_help'] = 'The technical name (URL identifier) of your channel on PeerTube, e.g. "ms-smith". You can find it in your channel\'s PeerTube address, or ask your administrator.';

$string['enablecourseinstances'] = 'Allow course PeerTube instances';
$string['enableuserinstances'] = 'Allow user PeerTube instances';

$string['untitled'] = 'Untitled video';
$string['privacy_public'] = 'Public';
$string['privacy_unlisted'] = 'Unlisted';
$string['privacy_private'] = 'Private';
$string['privatewarning'] = '(cannot be played! Please switch to "Unlisted")';
