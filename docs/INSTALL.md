# Installation and configuration

## 1. Requirements

- Moodle 5.1 (build 2025100600) or later
- A PeerTube instance reachable over HTTPS
- One PeerTube account with the moderator role, shared by the whole school

## 2. Install the plugin

Upload the ZIP file under *Site administration > Plugins > Install
plugins*, or unpack it into `repository/peertubeoauth` manually. Complete
the database upgrade afterwards.

## 3. Enable and configure

Open *Site administration > Plugins > Repositories > Manage repositories*,
set *PeerTube (OAuth2)* to enabled and open its settings.

| Setting | Value |
| --- | --- |
| `instanceurl` | Full URL of the PeerTube instance |
| `fallbackusername` | Username of the shared moderator account |
| `fallbackpassword` | Password of the shared moderator account |
| `schoolcode` | Short school identifier, letters, digits and underscores only |
| `embedparams` | Default `peertubeLink=0&p2p=0&warningTitle=0` |
| `enableuserinstances` | Must be enabled for personal teacher instances |

## 4. Grant the capability to authenticated users

Personal repository instances live in the user context, which falls back
to the authenticated user role. Moodle does not assign the capability
there automatically, so it has to be granted once:

```sql
INSERT INTO mdl_role_capabilities
  (contextid, roleid, capability, permission, timemodified, modifierid)
SELECT
  (SELECT id FROM mdl_context WHERE contextlevel=10 LIMIT 1),
  (SELECT id FROM mdl_role WHERE shortname='user'),
  'repository/peertubeoauth:view', 1, UNIX_TIMESTAMP(), 2;
```

Run this statement only once. A duplicate key error means it is already
in place and can be ignored.

## 5. Verify

1. Log in as a teacher and open the file picker in any text editor.
2. Choose *PeerTube (OAuth2)*; the video list should appear.
3. Insert a video and save; the link should render as an embedded player.
