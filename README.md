# BigBlueButton BN Experience

`bbbext_bnx` is the foundation of the BNX extension family for
`mod_bigbluebuttonbn`. It ships end-user features of its own and provides the
shared runtime contract used by sibling `bbbext_bnx_*` sidecars.

## What BNX currently provides

- Moderator approval before join (`approvalbeforejoin`) with site defaults and
  optional per-activity override
- Enhanced recordings UX with search, sorting, pagination, in-place editing,
  and recording import
- BNX-specific guest entry flow and logout redirect handling
- BNX-managed lock-settings overlay for selected BigBlueButton join controls
- Reminder scheduling, guest reminder subscriptions, and reminder email
  customization
- Shared sidecar discovery and room-adjustment helpers
- A public BNX enable/disable event contract for sidecars

BNX is a `bbbext` subplugin. It does not replace `mod_bigbluebuttonbn`; it
extends the parent module through the extension hooks that the parent module
already exposes.

## Supported platform

- Moodle: 5.1 to 5.3 (`$plugin->supported = [501, 503]`)
- Minimum Moodle requirement: 5.1 (`$plugin->requires = 2025100600`)
- Plugin maturity: beta (`MATURITY_BETA`)
- BigBlueButton server: no BNX-specific server-version gate is enforced; BNX
  follows the BigBlueButton API and server compatibility of the installed
  `mod_bigbluebuttonbn` release

## Installation

1. Install the parent `mod_bigbluebuttonbn` plugin and configure its server
   credentials.
2. Copy BNX into `mod/bigbluebuttonbn/extension/bnx/`.
3. Visit Site administration > Notifications to complete installation and
   upgrade steps.
4. Review BNX settings at:
   Site administration > Plugins > Activity modules > BigBlueButton >
   BigBlueButton BN Experience

## Provisioning and prerequisites

BNX assumes that the parent `mod_bigbluebuttonbn` plugin is already installed
and enabled by the administrator.

Important:

- BNX does **not** auto-enable `mod_bigbluebuttonbn`
- BNX does **not** auto-enable sibling sidecars
- BNX sidecars are enabled and disabled independently by the administrator

Current operational expectation:

- if the parent module is disabled, BNX and its sidecars are effectively
  unusable even if they remain installed
- some sidecars have additional parent-owned prerequisites; for example,
  `bbbext_bnx_datahub` requires `meetingevents_enabled` on
  `mod_bigbluebuttonbn`

When enabled, the plugin adjusts BigBlueButton create and join parameters:

- `guestPolicy=ASK_MODERATOR` for `create`
- preserves `guest=true` only for real guest joins

BNX documents these prerequisites but does not currently provide the same
status-check coverage for them that `bbbext_bnx_datahub` provides for meeting
events.

## Feature configuration

### Site-level settings

BNX currently groups settings into these areas:

- Waiting room
- Lock settings
- Reminders
- Reminder email subject/template/footer

The parent BigBlueButton settings page also changes slightly when credentials
are preconfigured in `config.php`: BNX replaces the generic setup description
with a shorter message indicating that credentials are already managed there.

### Per-activity settings

BNX contributes settings through the parent module's extension hooks. Depending
on site configuration, teachers may see:

- moderator approval before join
- BNX-managed lock settings
- reminder enablement, guest reminders, and reminder timespans
- BNX guest join URL

For join URL handling, BNX preserves `['guest' => 'true']` only when the
incoming join is already a guest join.

If a feature is not editable at site level, BNX persists the site default and
hides the editable control from the activity form.

## Runtime model

### Parent hook integration

BNX uses the `bbbext` hook points exposed by `mod_bigbluebuttonbn`:

- `action_url_addons`
- `mod_form_addons`
- `mod_instance_helper`
- `view_page_addons`

These hook classes live in [`classes/bigbluebuttonbn/`](classes/bigbluebuttonbn).

### Room page flow

1. The parent module resolves BNX hook implementations.
2. BNX builds the room-page context through
   [`page_context_builder`](classes/local/bigbluebutton/view/page_context_builder.php).
3. Mustache templates render the BNX view.
4. AJAX/external services provide refreshed meeting and recordings data.

### Sidecar contract

BNX no longer auto-manages sibling plugin enablement. The supported contract is:

- sidecars discover BNX utilities through helper classes
- BNX emits `\bbbext_bnx\event\state_changed` when BNX itself is enabled or
  disabled
- sidecars may subscribe to that event if they want to react to BNX state
- sidecars remain responsible for their own configuration and enablement

BNX also supports convention-based sidecar discovery for selected behaviors,
such as presentation providers and room alerts, through
[`sidecar_helper`](classes/local/helpers/sidecar_helper.php).

### Reminder subscription changes

Reminder subscription state can only be changed through authenticated requests:

- The in-product subscription toggle posts to `managesubscriptions.php` and
  requires a valid session key (`sesskey`); it is no longer submitted through a
  GET request.
- One-click unsubscribe links in reminder emails carry a per-row HMAC token.
  `subscription.php` verifies the token before changing any subscription state,
  so links cannot be forged or replayed for a different recipient. Links are
  regenerated whenever a reminder email is sent, so previously delivered links
  remain valid only while the signing secret is unchanged.

## Migrations and upgrade behavior

BNX performs real upgrade-time migrations. Today that includes:

- migrating legacy BN Reminders data and settings into BNX
- disabling the legacy `bbbext_bnreminders` plugin after successful migration
- migrating core BigBlueButton lock settings into BNX-managed lock settings
- syncing those migrated lock settings again when BNX is enabled

This means BNX is not a pure "read only" sidecar with respect to other plugin
state. The current implementation intentionally preserves customer migration
behavior, even though some certification reviews may prefer looser coupling.

## Known limitations and design constraints

- Guest-link lookup still relies on a documented BNX shim because the parent
  module does not yet expose a public `get_from_guestlinkuid()` API.
- BNX still contains some cross-component migration behavior because legacy
  functionality was consolidated into BNX.
- The enhanced recordings front end largely moved to event-based module
  communication, but one compatibility global remains in
  `amd/src/recordings_pagination.js`.
- BNX does not currently expose administrator status checks for all parent
  prerequisites.
- Some BBB-server-dependent behaviors are better covered by PHPUnit than by
  isolated Behat scenarios.

## Testing and CLI

BNX does not ship custom CLI commands of its own. Maintenance and validation use
standard Moodle tooling.

Typical commands:

```bash
# Initialise PHPUnit if needed.
php public/admin/tool/phpunit/cli/init.php

# Run targeted BNX PHPUnit tests.
php vendor/bin/phpunit -c phpunit.xml public/mod/bigbluebuttonbn/extension/bnx/tests/form/guest_login_test.php
php vendor/bin/phpunit -c phpunit.xml public/mod/bigbluebuttonbn/extension/bnx/tests/module_enablement_test.php

# List BNX Behat coverage files.
find public/mod/bigbluebuttonbn/extension/bnx/tests/behat -maxdepth 1 -type f | sort
```

Current automated coverage includes:

- PHPUnit coverage for meeting info, import-recording service paths, guest
  password validation, module enablement boundaries, state-change events,
  reminders, form helpers, and migrations
- Behat coverage for basic BNX flows, guest login validation, guest meeting
  joins, and recordings listing/editing

## Troubleshooting

### BNX is installed but features are not visible

Check:

- `mod_bigbluebuttonbn` is installed and enabled
- BNX itself is enabled
- the current activity is a BigBlueButton activity
- feature defaults/editability were configured in BNX settings

### Sidecar behavior is missing

Check:

- the sidecar plugin is enabled independently
- the sidecar is ordered correctly if behavior is order-sensitive
- any sidecar-specific parent prerequisites are satisfied

Example:

- `bbbext_bnx_datahub` depends on `meetingevents_enabled` in the parent module
  and surfaces that dependency through its own status check

### Guest links stop working after parent changes

BNX guest links currently depend on the parent module's `guestlinkuid` field
through the documented `guestlink_lookup` shim. If upstream parent APIs change,
the guest-link path may need to be updated accordingly.

### Reminder data did not migrate as expected

BNX migration logic is idempotent but depends on the presence of the legacy
plugin configuration/tables at install or upgrade time. Review:

- [`db/migration.php`](db/migration.php)
- [`tests/install_migration_test.php`](tests/install_migration_test.php)

## Developer notes

- Do not assume BNX can freely mutate parent or sibling plugin settings.
- Do not reintroduce hard-coded sibling class dependencies.
- Prefer the existing sidecar helper and event contract over ad hoc sidecar
  wiring.
- Treat the guest-link shim as temporary until the parent plugin exposes a
  proper API.

## Change history

See:

- [CHANGES](CHANGES) for developer-facing change history
- [RELEASENOTES](RELEASENOTES) for administrator/customer-facing release notes
- [docs/release-notes/1.2-beta.1.md](docs/release-notes/1.2-beta.1.md) for the
  detailed Open LMS remediation log for 1.2-beta.1
- [docs/release-notes/1.2-beta.2.md](docs/release-notes/1.2-beta.2.md) for the
  detailed Open LMS code review #2 remediation log for 1.2-beta.2

## Related plugins

- `mod_bigbluebuttonbn` — parent module
- `bbbext_bnx_datahub` — analytics/reporting sidecar
- `bbbext_bnx_insights` — in-session student-insight sidecar
- `bbbext_bnx_preuploads` — presentation-provider sidecar
- `bbbext_bnx_earlyaccess` — early teacher-access sidecar

Legacy plugins whose functionality has been migrated into BNX:

- `bbbext_bnreminders`
- `bbbext_bnx_locksettings`

## Credits

**Author:** Jesus Federico, Shamiso Jaravaza  
**Copyright:** 2026 onwards, Blindside Networks Inc  
**License:** GNU GPL v3 or later
