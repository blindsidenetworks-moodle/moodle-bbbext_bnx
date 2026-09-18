# BN Experience Developer Guide

## The BigBlueButton extension model

BNX is a Moodle `bbbext` subplugin. Moodle discovers it from the parent
`mod_bigbluebuttonbn` extension directory and the parent extension dispatcher
instantiates classes in the plugin namespace. BNX augments parent behavior; it
does not fork or replace the parent activity implementation.

An extension hook uses the parent class basename below the plugin namespace.
For example, BNX's lifecycle hook is:

```text
bbbext_bnx\bigbluebuttonbn\mod_instance_helper
```

The parent controls which hooks exist and when it calls them. Read the matching
base class in `mod_bigbluebuttonbn\local\extension` before adding a hook; method
signatures must remain compatible with the installed Moodle version.

## BNX framework role

BNX is both a feature plugin and the foundation for `bbbext_bnx_*` sidecars.
Sidecars should remain independently installable and must not write parent or
sibling configuration. Use BNX's public event and narrowly scoped helpers
instead of adding cross-plugin enablement logic.

### Parent hook implementations

| Hook class | Responsibility |
| --- | --- |
| `action_url_addons` | Adds BNX parameters to parent BigBlueButton API actions. |
| `mod_form_addons` | Adds activity-form fields and default/editability behavior. |
| `mod_instance_helper` | Persists, updates, and cleans up BNX activity data. |
| `view_page_addons` | Adds BNX room-page content and client resources. |

BNX's hooks live in `classes/bigbluebuttonbn/`. Put domain behavior in helpers
or services rather than growing a hook class into a second parent module.

### Sidecar lifecycle contract

BNX publishes `\bbbext_bnx\event\state_changed` when its enabled state changes.
A sidecar may observe this event and change only its own configuration. DataHub
is the reference consumer.

BNX also has `sidecar_helper` for convention-based alert and UI-string
providers. This is compatibility infrastructure, not a general extension API.
New sidecar behavior should prefer a parent hook or an explicit, typed contract
over naming-based discovery.

## Feature boundaries

- Early Access: `earlyaccess_helper` requires
  `bbbext/bnx:earlyjoinaccess`; do not grant early access by changing the
  meeting's running state for unauthorized viewers.
- Presentations: `presentation_helper` owns activity-file linkage;
  `presentation_token_helper` issues short-lived, use-limited service tokens;
  `external\get_file` serves only linked BNX presentation files.
- Guest flow: do not add BNX guest URLs, forms, join parameters, or logout
  redirects. The parent module owns this entire flow.
- Reminders: use the existing scheduled and ad hoc task classes and retain the
  session-key/HMAC protections on subscription mutations.

## Adding a feature

1. Identify the parent hook or Moodle API that owns the behavior.
2. Add BNX schema through `db/install.xml` and an idempotent `db/upgrade.php`
   step; bump `version.php` only as part of a release change.
3. Declare capabilities in `db/access.php`, external functions in
   `db/services.php`, events in `db/events.php`, and tasks in `db/tasks.php`.
4. Keep stored files in a component-specific file area and enforce module
   context, login, capability, and ownership checks before serving them.
5. Add focused PHPUnit coverage and a Behat scenario for browser-visible flows
   that do not need a real BigBlueButton server.

## Testing

Run Moodle PHPUnit with Moodle's configuration, not a bare PHPUnit invocation:

```bash
php vendor/bin/phpunit -c phpunit.xml \
  public/mod/bigbluebuttonbn/extension/bnx/tests/external/get_file_test.php
```

Useful focused areas are `tests/earlyaccess_helper_test.php`,
`tests/external/get_file_test.php`, `tests/privacy/provider_test.php`,
`tests/backup_restore_test.php`, and `tests/mod_instance_helper_test.php`.
BNX Behat features are in `tests/behat/`. Use PHPUnit/mock coverage for
BigBlueButton-server-dependent paths rather than inventing a browser scenario
that cannot represent the server state honestly.

## Compatibility rules

BNX 1.3 supports Moodle 5.3 only. Before relying on a new parent API, verify
that it exists on the required Moodle branch and add a regression test for the
parent-to-extension call contract. Do not add a compatibility shim for guest
access: core delegation is the supported architecture.
