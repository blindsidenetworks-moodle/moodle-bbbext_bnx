# BN Experience Administrator Guide

## Purpose

`bbbext_bnx` adds BN Experience features to Moodle's BigBlueButton activity. It
is a `bbbext` subplugin, so it is installed below the parent module and depends
on the parent module's server configuration and extension dispatcher.

This guide applies to BNX `1.3-alpha.1`, which supports Moodle 5.3 only.

## Install and upgrade

1. Install and configure `mod_bigbluebuttonbn`, including its BigBlueButton
   server URL and shared secret.
2. Deploy BNX to `mod/bigbluebuttonbn/extension/bnx`.
3. Complete the Moodle upgrade from Site administration > Notifications, or run
   the Moodle CLI upgrade as the web-server user.
4. Enable BNX in Plugins overview if it is not enabled after installation.
5. Configure BNX at Site administration > Plugins > Activity modules >
   BigBlueButton > BigBlueButton BN Experience.

BNX does not enable the parent module or any sibling plugin. Administrators own
those enablement decisions.

## Configure features

BNX settings define defaults and, where provided, whether teachers can override
them in an individual BigBlueButton activity. The main configuration areas are:

- waiting room approval before join;
- lock settings for camera, microphone, chat, notes, user list, and viewer
  cursor;
- Early Access before an activity opening time;
- multi-presentation uploads and the maximum number of files; and
- reminders, guest reminder subscriptions, and email content.

Teachers configure eligible per-activity options in the standard
BigBlueButton activity form. A setting that is not editable site-wide is stored
from its site default and is not shown as an activity-level choice.

## Operational prerequisites

The BigBlueButton parent module must be installed, enabled, and configured.
BNX does not duplicate its server configuration or substitute for it.

Guest access is owned by the parent module. BNX does not provide a guest URL
endpoint, guest password form, custom guest join URL, or logout redirect. Use
the parent module's guest-access configuration and support documentation.

## Presentations

Teachers can attach multiple presentation files to an activity when the
presentation feature is enabled. Files are stored in the activity module
context under the BNX `presentation` file area. During meeting creation, BNX
generates a short-lived service URL for each presentation so BigBlueButton can
fetch it without a teacher's Moodle browser session.

Do not reuse those URLs outside the meeting create flow. They expire after ten
minutes, are bound to the activity, and have a limited number of requests.

## Reminders and legacy migration

BNX includes reminder scheduling and guest reminder subscriptions. Moodle cron
must run normally; the scheduled reminder check defaults to every minute and
can be adjusted in Site administration > Server > Scheduled tasks for very
large sites.

The CLI migration can move data from legacy BN Reminders:

```bash
php public/mod/bigbluebuttonbn/extension/bnx/cli/migrate_bnreminders.php
```

After a successful migration, BNX disables `bbbext_bnreminders` to prevent two
reminder engines from sending the same message. Review the migration result and
do not re-enable the legacy plugin alongside BNX.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| BNX controls are absent | Confirm both the parent module and BNX are enabled, then review BNX setting editability. |
| Teachers cannot join early | Confirm the activity has an opening time, Early Access is enabled, and the user has `bbbext/bnx:earlyjoinaccess`. |
| Presentations do not preload | Confirm a file is attached to the correct activity and inspect the parent module's BigBlueButton create request/logs. |
| Reminders do not arrive | Confirm Moodle cron, reminder settings/timespans, recipient subscriptions, and mail configuration. |
| A legacy-reminders warning appears | Run the migration, verify its result, and leave the legacy sidecar disabled. |

## Support information

When diagnosing BNX, capture the Moodle version, BNX version, parent
`mod_bigbluebuttonbn` version, enabled sidecars, relevant activity ID, and a
sanitized error or debugging trace. Never include the BigBlueButton shared
secret or temporary presentation token in a support ticket.
