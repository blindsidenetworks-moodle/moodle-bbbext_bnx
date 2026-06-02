# BigBlueButton BN Experience

BigBlueButton BN Experience is a foundational UX enhancement extension for BigBlueButton in Moodle — featuring moderator approval workflows and shared capabilities that power the entire BNX extension family. Developed and supported by Blindside Networks, the creators of BigBlueButton.

## Description

This plugin extends the BigBlueButton activity module with foundational UX improvements and shared behaviours that can be reused by other `bbbext_bnx_*` sidecar plugins.

**Note:** This plugin is the parent for other `bbbext_bnx_*` extensions and must be enabled for those plugins to function.

## Features

- **Waiting Room (moderator approval before join)**: Replaces the built-in "Wait for Moderator" screen with a Waiting Room lobby. Participants wait until a moderator approves their entry — configurable site-wide with per-activity override for teachers.
- **Enhanced recording experience**: Replaces the core recordings table with a fully functional implementation including search, sorting, pagination, editable recording name and description, and recording import. Driven by dedicated AJAX web services.
- **Navigation label override**: Replaces the default BigBlueButton activity navigation label with "BigBlueButton +" to surface the enhanced experience to users.
- **Sidecar integration contract**: BNX publishes the `\bbbext_bnx\event\state_changed` event on every BNX enable/disable transition. Sidecar `bbbext_bnx_*` plugins subscribe to that event in their own `db/events.php` and decide for themselves how to react. BNX no longer auto-enables or auto-disables sibling sub-plugins; administrator intent on sibling enablement is always preserved (see [Sidecar contract](#sidecar-contract) below).
- **Backup and restore**: Full backup and restore support for per-activity settings.
- **GDPR Compliant**: No personal user data is stored (null privacy provider).

## Requirements

- Moodle 5.1 or later
- BigBlueButton plugin (`mod_bigbluebuttonbn`)

## Installation

1. Copy the plugin to `mod/bigbluebuttonbn/extension/bnx/`
2. Visit Site Administration > Notifications to complete installation
3. Configure settings at Site Administration > Plugins > Activity modules > BigBlueButton > BigBlueButton BN Experience

## Configuration

### Admin Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Waiting Room enabled by default | Default on/off state for the Waiting Room across all activities | Enabled |
| Allow teachers to change the Waiting Room setting per activity | Allow teachers to override the Waiting Room on or off for individual activities | Enabled |

### Per-Activity Settings

Teachers can configure this on the BigBlueButton activity form under **Room Settings +**:

- **Moderator approval required to join session**

When enabled, the plugin sets:
- `guestPolicy=ASK_MODERATOR` for `create`
- preserves `guest=true` only for real guest joins

## Architecture

### Key Classes

| Class | Purpose |
|-------|---------|
| `action_url_addons` | Injects guest policy parameters into BBB API calls |
| `mod_form_addons` | Adds the Waiting Room checkbox and per-activity fields to the activity form |
| `mod_instance_helper` | Handles per-activity settings storage |
| `action_url_parameters` | Computes create/join parameters based on settings |
| `view_page_addons` | Overrides the BBB view page to embed the enhanced recordings experience |
| `page_context_builder` | Builds the full template context for the view page |
| `hook_callbacks` | Injects the navigation label override on BigBlueButton module pages |
| `recording_helper` | Helpers for recording data retrieval and formatting |
| `joinurl_helper` | Builds custom join URLs |
| `guestlink_lookup` | Isolated shim for parent-table guest-link UID lookups (pending upstream `mod_bigbluebuttonbn\instance::get_from_guestlinkuid()`) |
| `sidecar_helper` | Utilities shared across BNX sidecar plugins |
| `state_changed` | Event triggered on BNX enable/disable transitions; the public sidecar integration contract |

### Web Services

| Service | Description |
|---------|-------------|
| `bbbext_bnx_get_meeting_info` | Returns meeting info with BNX extensions |
| `bbbext_bnx_get_recordings` | Returns recordings list for the enhanced recordings table |
| `bbbext_bnx_get_recordings_to_import` | Returns recordings available to import |

### API Integration

The plugin uses the `action_url_addons` hook to append parameters:

```php
// create
['guestPolicy' => 'ASK_MODERATOR']

// join
['guest' => 'true'] // only when the incoming join is already a guest join
```

### Settings Resolution

1. If the Waiting Room setting is editable, the per-activity value is used.
2. Otherwise, the site-wide admin default is used.

## Sidecar contract

Starting with BNX **1.2**, the integration boundary between `bbbext_bnx` and
its sibling `bbbext_bnx_*` sub-plugins ("sidecars") is a public, event-driven
contract. BNX no longer reaches into sibling plugins' enablement, configuration,
or storage; sidecars opt in to BNX-driven behaviour through documented hooks.

This change brings BNX in line with the Moodle
[Component Communication policy](https://moodledev.io/general/development/policies/component-communication).

### What BNX guarantees

- **No auto-enablement.** BNX never calls `\core\plugininfo\mod::enable_plugin()`
  for `mod_bigbluebuttonbn` or for any sibling `bbbext_bnx_*` plugin. The
  administrator enables the parent module and each sidecar explicitly.
- **No cross-plugin writes.** BNX install/upgrade code mutates only BNX-owned
  tables and the `bbbext_bnx` config namespace.
- **No direct sibling-table reads.** Parent-table lookups are confined to
  `\bbbext_bnx\local\helpers\guestlink_lookup` and are tracked for removal
  once upstream `mod_bigbluebuttonbn` exposes a matching API
  (see Moodle tracker request for `instance::get_from_guestlinkuid()`).
- **A public event for state transitions.** Every BNX enable/disable transition
  emits `\bbbext_bnx\event\state_changed` with payload
  `other => ['enabled' => bool]`. Sidecars subscribe to this event if they want
  to mirror BNX state; sidecars that do not subscribe are unaffected.

### Event payload

```php
\bbbext_bnx\event\state_changed::create([
    'context' => \context_system::instance(),
    'other'   => ['enabled' => true],  // false on disable
])->trigger();
```

- `crud`: `'u'`
- `edulevel`: `LEVEL_OTHER`
- `other['enabled']`: `bool` (required) — `true` when BNX has just been enabled,
  `false` when BNX has just been disabled.

### Subscribing from a sidecar

A sidecar (e.g. `bbbext_bnx_mysidecar`) declares an observer in its own
`db/events.php`:

```php
// mod/bigbluebuttonbn/extension/mysidecar/db/events.php
$observers = [
    [
        'eventname' => \bbbext_bnx\event\state_changed::class,
        'callback'  => \bbbext_bnx_mysidecar\observer::class . '::bnx_state_changed',
        'internal'  => false,
    ],
];
```

and implements the observer:

```php
// mod/bigbluebuttonbn/extension/mysidecar/classes/observer.php
namespace bbbext_bnx_mysidecar;

class observer {
    public static function bnx_state_changed(\bbbext_bnx\event\state_changed $event): void {
        $enabled = (bool) $event->other['enabled'];
        // Mirror BNX state inside *this* sidecar only. Never touch other plugins.
        if ($enabled) {
            // ... enable sidecar-owned features ...
        } else {
            // ... disable sidecar-owned features ...
        }
    }
}
```

Sidecars that should remain enabled regardless of BNX state simply do not
subscribe. Each sidecar is responsible for its own enablement; BNX is not.

### Sidecar callback discovery

For non-state behaviour (e.g. presentation providers), BNX discovers sidecar
implementations by the `{pluginname}`-templated class-name convention enforced
by `\bbbext_bnx\local\helpers\sidecar_helper`. A sidecar that wants to provide
presentations exposes:

```
\bbbext_{pluginname}\local\helpers\presentation_helper::get_presentations_for_ws(int $instanceid): array
```

BNX iterates enabled sidecars, calls the method when present, and degrades to
an empty result when no sidecar provides it. The contract is conventional, not
typed; a future Moodle hook may replace it (tracker request filed).

## Privacy

This plugin **does not store any personal data**. It only stores configuration values and per-activity feature toggles.

## Version History

- **1.2-beta.1** (June 2, 2026) — Open LMS code-review remediation. Hardens
  external API authorization and validation, sanitizes recording and meeting
  output, enforces timing-safe guest password comparison, adds null guards
  after `instance::get_from_instanceid()`, batches recording status writes,
  defers settings string resolution, removes jQuery from `overridenav`,
  replaces cross-module `window.*` pagination state with a `CustomEvent`,
  moves inline guest JS into an AMD module, and gates production `debugging()`
  behind `DEBUG_DEVELOPER`. Introduces the public
  `\bbbext_bnx\event\state_changed` event and removes BNX-side auto-enable/
  auto-disable of sibling sub-plugins; administrators now manage each plugin's
  enablement explicitly. See [Sidecar contract](#sidecar-contract) and
  [docs/release-notes/1.2-beta.1.md](docs/release-notes/1.2-beta.1.md) for the
  per-commit remediation log.
- **1.1.1** (June 1, 2026) — Avoids forcing `guest=true` for moderator joins so
  moderator access is not misclassified as guest access (MD-109). Fixes
  missing recording row creation when BigBlueButton create calls fail in
  specific error paths (MDL-88775).
- **1.1** (May 1, 2026) — Migrates BN Reminders functionality and reminder data
  into BNX, consolidating reminder settings and email customization (MD-90,
  MD-104). Migrates lock settings controls from `bnx_locksettings` into BNX and
  absorbs the legacy sidecar configuration into BNX defaults (MD-107).
  Refactors mod-form header label overrides into a reusable helper (MD-100).
  Adds a shared developer debug helper for BNX sidecars.
- **1.0.1** (April 1, 2026) — Adds a BNX-specific guest access endpoint
  (`guest.php`) with its own join form, URL builder, and logout redirect flow,
  replacing the core guest page in BNX contexts (GIT-1). Hides BBB server setup
  instructions in the activity settings when credentials are pre-configured
  (MD-98). Improves recordings table UX (MD-94). Supports auto-set default
  settings decoupled from sidecars (MD-95, MD-97).
- **1.0** (March 11, 2026) — First stable open-source release. Introduces the
  BNX Framework with moderator approval workflows and shared capabilities that
  power the BNX extension family. Adds PreSession lobby access (replaces Wait
  for Moderator) and delivers an enhanced recording experience with end-to-end
  recording features.
- **0.1.0-beta.1** (March 11, 2026) — Initial beta of the BNX Framework. Adds
  PreSession lobby access, enhanced recordings table, improved sidecar
  integration (ordered plugin behavior, presentation title resolution), and
  multiple stability and quality fixes (guest lobby/group assignment,
  `approvalbeforejoin`, code quality).

See [RELEASENOTES](RELEASENOTES) for the full per-commit history.

## Credits

**Author**: Jesus Federico, Shamiso Jaravaza  
**Copyright**: 2026 onwards, Blindside Networks Inc  
**License**: GNU GPL v3 or later

## Related Plugins

- **bbbext_bnx_insights**: Sends student insights from Moodle directly into the BigBlueButton live session, per student.
- **bbbext_bnx_datahub**: Integrates with Moodle report builder to provide advanced reporting and analytics.
- **bbbext_bnx_preuploads**: Allows multiple preuploaded presentations for meeting content.
- **bbbext_bnx_earlyaccess**: Allows teachers to join the room for testing before the activity starts.
- **bbbext_bnx_locksettings**: Extends join settings to align with the lock controls offered by the BigBlueButton API.
- **mod_bigbluebuttonbn**: Core BigBlueButton activity module
