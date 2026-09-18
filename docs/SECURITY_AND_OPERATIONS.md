# BN Experience Security and Operations

## Access control

BNX follows Moodle context and capability checks. Standard presentation access
uses the parent `mod/bigbluebuttonbn:view` capability. Early Access additionally
requires `bbbext/bnx:earlyjoinaccess` in the activity module context. The
default grant is limited to teacher and editing-teacher archetypes.

The BNX pluginfile callback requires the activity context, Moodle login, the
view capability, a valid file area/item ID, and a record in
`bbbext_bnx_presentations` that links the requested file to the current
BigBlueButton activity. A file linked to another activity is not served.

## Presentation service tokens

BigBlueButton fetches uploaded presentation files through the
`bbbext_bnx_get_file` external service. The generated URL contains a temporary
service token, not a user's browser session. The token is:

- restricted to the BNX presentation service;
- bound to one BNX activity;
- valid for ten minutes;
- limited to the configured remaining request count; and
- revoked from Moodle after its last permitted use.

The service validates token ownership before resolving the stored file. It does
not permit arbitrary Moodle file IDs. Do not log complete presentation URLs or
share them outside the BBB request path.

## Privacy and lifecycle

Uploaded presentations are activity content, not user-owned personal data. BNX
backs them up and restores the file-to-presentation link. Deleting an activity
removes its presentation files, links, and temporary-token records.

BNX does process personal data for guest reminder subscriptions and reminder
preferences. Its privacy provider exposes context discovery, export, deletion,
and user-list operations for those records. Keep metadata strings and provider
behavior in sync whenever a new user-associated table or preference is added.

## Guest and reminder safety

Guest access is deliberately delegated to the parent BigBlueButton module.
BNX must not replicate guest credential validation, guest-link resolution, or
logout handling.

Authenticated reminder subscription changes require a session key. Email
unsubscribe links use a per-row HMAC token. Preserve those checks when changing
the reminder UI or mail tasks.

## Operational controls

- Run Moodle cron so scheduled and ad hoc reminder tasks execute.
- Treat the service-account user and its web-service token as plugin-managed;
  do not repurpose it for unrelated APIs.
- Run the legacy reminders migration once and leave the old sidecar disabled.
- Use Moodle role assignment to control Early Access instead of modifying
  capabilities in code.
- Before release, run targeted authorization, token, privacy, backup/restore,
  and deletion tests alongside the repository CI checks.
