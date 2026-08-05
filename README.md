# PeerTube (OAuth2) repository for Moodle

[![Moodle Plugin CI](https://github.com/moodle-nds/moodle-repository_peertubeoauth/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/moodle-nds/moodle-repository_peertubeoauth/actions/workflows/moodle-ci.yml)

A Moodle repository plugin that lists videos from a PeerTube instance in
the file picker and automatically turns PeerTube links on any Moodle page
into responsive, embedded players.

Unlike the standard PeerTube repository, this plugin authenticates
against the instance and therefore also lists **unlisted** videos, not
only public ones.

## Features

- Lists public, unlisted and private videos of one shared moderator account
- Per user repository instances filtered down to a teacher's own channel
- Fallback renderer that converts PeerTube links into embedded players,
  both for links pasted as plain URLs and for links created through the
  file picker
- Configurable embed parameters, disabling P2P by default so that viewer
  IP addresses are not shared with other peers
- German and English interface

## Requirements

- Moodle 5.1 (build 2025100600) or later
- A PeerTube instance with one account holding the moderator role

## Installation

Install the plugin into `repository/peertubeoauth` of your Moodle
installation, either by uploading the ZIP file through *Site
administration > Plugins > Install plugins* or by unpacking it manually.
Then complete the database upgrade as usual.

## Configuration

Go to *Site administration > Plugins > Repositories > Manage
repositories*, enable *PeerTube (OAuth2)* and open its settings.

| Setting | Description |
| --- | --- |
| PeerTube instance URL | Full address of the PeerTube instance |
| Moderator account username | Username of the shared moderator account |
| Moderator account password | Password of the shared moderator account |
| School code | Prefix for generated channel names, letters, digits and underscores only |
| Embed URL parameters | Privacy and player parameters, default `peertubeLink=0&p2p=0&warningTitle=0` |

To let teachers create personal repository instances in their own
profile, the capability `repository/peertubeoauth:view` has to be granted
to the authenticated user role. See `docs/` for details.

## Privacy note

Videos embedded through this plugin are marked *unlisted* on PeerTube.
This hides them from search, but it is **not** an access control: the
video address is present in the page source and works for anyone who
receives it. Do not publish sensitive or personal videos this way. Use
the access controlled file area of Moodle for such content instead.

## Documentation

Further documentation is available in the `docs/` directory.

## License

GNU GPL v3 or later. See <https://www.gnu.org/licenses/>.

Maintained by Moodle in Niedersachsen e. V.
