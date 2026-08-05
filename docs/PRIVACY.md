# Privacy considerations

## Stored data

This plugin stores no personal data. The moderator credentials are site
wide administrator settings held in `mdl_config_plugins`, and the OAuth2
access token exists only in the Moodle session, never in the database.

Note that Moodle stores plugin settings in plain text. This is standard
Moodle behaviour and applies to every repository plugin. Use a long
random password for the moderator account, restrict database access to
localhost, and enable two factor authentication for PeerTube
administrator accounts.

## Embed parameters

The default embed parameters are `peertubeLink=0&p2p=0&warningTitle=0`.

| Parameter | Effect |
| --- | --- |
| `p2p=0` | Disables WebTorrent, so viewer IP addresses are not shared with other peers. This is the only parameter with a real technical privacy effect. |
| `peertubeLink=0` | Hides the PeerTube link in the player. Cosmetic only. |
| `warningTitle=0` | Hides the IP warning banner. Cosmetic only. |
| `title=0` | Optional, hides the title overlay. Cosmetic only. |

## Visibility model

Videos are published as *unlisted* on PeerTube. This is a deliberate
decision: private PeerTube videos cannot be played by anonymous viewers,
and pupils do not have PeerTube accounts, so unlisted is the strongest
level that still allows anonymous playback.

Unlisted means the video is not discoverable through search. It is **not**
an access control. The video UUID is present in the iframe source in the
page source and can be copied and forwarded; a forwarded link works
without Moodle and without a login.

Consequently, do not publish sensitive or personal videos through
PeerTube, in particular recordings showing identifiable pupils,
discussions of assessments, or confidential material. Use the access
controlled file area of Moodle for such content, where the Moodle
permission system applies.
