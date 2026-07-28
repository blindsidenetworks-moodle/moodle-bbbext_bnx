<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Data migration helpers for bbbext_bnx.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */

/**
 * Return a legacy BN Reminders table name.
 *
 * @param string $suffix Optional table suffix.
 * @return string
 */
function bbbext_bnx_legacy_bnreminders_table(string $suffix = ''): string {
    return 'bbbext_' . 'bnreminders' . $suffix;
}

/**
 * Migrate legacy BN Reminders data and settings into BNX.
 *
 * Migration is idempotent and safe to call multiple times.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_data(): void {
    global $DB;

    if (!bbbext_bnx_has_legacy_bnreminders_data()) {
        return;
    }

    $now = time();

    bbbext_bnx_migrate_bnreminders_instance_settings($now);
    bbbext_bnx_migrate_bnreminders_reminders();
    bbbext_bnx_migrate_bnreminders_guest_reminders();
    bbbext_bnx_migrate_bnreminders_admin_settings();
    bbbext_bnx_migrate_bnreminders_user_preferences();

    // Disable bnreminders once migration has completed.
    $oldvalue = get_config('bbbext_bnreminders', 'disabled');
    if (empty($oldvalue)) {
        set_config('disabled', 1, 'bbbext_bnreminders');
        if (function_exists('add_to_config_log')) {
            add_to_config_log('disabled', $oldvalue, 1, 'bbbext_bnreminders');
        }
        \core_plugin_manager::reset_caches();
    }
}

/**
 * Migrate legacy BNX lock settings admin config into BNX and disable the sidecar.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnx_locksettings_data(): void {
    if (!bbbext_bnx_has_legacy_bnx_locksettings_data()) {
        return;
    }

    $component = 'bbbext_bnx_locksettings';
    $configkeys = [
        'cam_default',
        'cam_editable',
        'mic_default',
        'mic_editable',
        'publicchat_default',
        'publicchat_editable',
        'privatechat_default',
        'privatechat_editable',
        'notes_default',
        'notes_editable',
        'userlist_default',
        'userlist_editable',
        'hideviewerscursor_default',
        'hideviewerscursor_editable',
    ];

    foreach ($configkeys as $configname) {
        $oldvalue = get_config($component, $configname);
        if ($oldvalue === false) {
            continue;
        }

        set_config($configname, $oldvalue, 'bbbext_bnx');
    }

    $oldvalue = get_config($component, 'disabled');
    if (empty($oldvalue)) {
        set_config('disabled', 1, $component);
        if (function_exists('add_to_config_log')) {
            add_to_config_log('disabled', $oldvalue, 1, $component);
        }
        \core_plugin_manager::reset_caches();
    }
}

/**
 * Migrate core BigBlueButton lock settings into BNX lock settings.
 *
 * This migration runs once and maps core "disable/hide" semantics to BNX
 * "enable/show" semantics for both admin defaults and existing activities.
 *
 * @return void
 */
function bbbext_bnx_migrate_core_locksettings_data(): void {
    if ((int)get_config('bbbext_bnx', 'locksettings_core_migrated') === 1) {
        return;
    }

    bbbext_bnx_migrate_core_locksettings_admin_config();
    bbbext_bnx_migrate_core_locksettings_instance_settings();

    set_config('locksettings_core_migrated', 1, 'bbbext_bnx');
}

/**
 * Synchronize core lock settings into BNX lock settings.
 *
 * This is intended for BNX enable events so that any core lock setting
 * changes made while BNX was disabled are reflected in BNX immediately.
 *
 * @return void
 */
function bbbext_bnx_sync_core_locksettings_data(): void {
    bbbext_bnx_migrate_core_locksettings_admin_config();
    bbbext_bnx_migrate_core_locksettings_instance_settings(true);
}

/**
 * Migrate core lock admin defaults/editability into BNX config.
 *
 * @return void
 */
function bbbext_bnx_migrate_core_locksettings_admin_config(): void {
    $mapping = [
        'cam' => ['core' => 'disablecam', 'invertdefault' => true],
        'mic' => ['core' => 'disablemic', 'invertdefault' => true],
        'privatechat' => ['core' => 'disableprivatechat', 'invertdefault' => true],
        'publicchat' => ['core' => 'disablepublicchat', 'invertdefault' => true],
        'notes' => ['core' => 'disablenote', 'invertdefault' => true],
        'userlist' => ['core' => 'hideuserlist', 'invertdefault' => true],
    ];

    foreach ($mapping as $bnxfeature => $meta) {
        $defaultkey = $bnxfeature . '_default';
        $coredefaultvalue = get_config('mod_bigbluebuttonbn', $meta['core'] . '_default');
        if ($coredefaultvalue === false) {
            $coredefaultvalue = get_config('bigbluebuttonbn', $meta['core'] . '_default');
        }
        if ($coredefaultvalue === false) {
            $coredefaultvalue = \mod_bigbluebuttonbn\local\config::get($meta['core'] . '_default');
        }

        $coredefault = (int)!empty($coredefaultvalue);
        $bnxdefault = !empty($meta['invertdefault']) ? (int)!$coredefault : $coredefault;
        set_config($defaultkey, $bnxdefault, 'bbbext_bnx');

        $editablekey = $bnxfeature . '_editable';
        $coreeditablevalue = get_config('mod_bigbluebuttonbn', $meta['core'] . '_editable');
        if ($coreeditablevalue === false) {
            $coreeditablevalue = get_config('bigbluebuttonbn', $meta['core'] . '_editable');
        }
        if ($coreeditablevalue === false) {
            $coreeditablevalue = \mod_bigbluebuttonbn\local\config::get($meta['core'] . '_editable');
        }

        $coreeditable = (int)!empty($coreeditablevalue);
        set_config($editablekey, $coreeditable, 'bbbext_bnx');
    }
}

/**
 * Migrate existing activity lock values from core module columns into BNX settings.
 *
 * @param bool $overwriteexisting When true, update existing BNX setting values.
 * @return void
 */
function bbbext_bnx_migrate_core_locksettings_instance_settings(bool $overwriteexisting = false): void {
    global $DB;

    $mapping = [
        'enablecam' => ['corefield' => 'disablecam', 'invert' => true],
        'enablemic' => ['corefield' => 'disablemic', 'invert' => true],
        'enableprivatechat' => ['corefield' => 'disableprivatechat', 'invert' => true],
        'enablepublicchat' => ['corefield' => 'disablepublicchat', 'invert' => true],
        'enablenotes' => ['corefield' => 'disablenote', 'invert' => true],
        'enableuserlist' => ['corefield' => 'hideuserlist', 'invert' => true],
    ];

    $fields = 'id, disablecam, disablemic, disableprivatechat, disablepublicchat, disablenote, hideuserlist';
    $instances = $DB->get_records('bigbluebuttonbn', null, '', $fields);
    $now = time();

    foreach ($instances as $instance) {
        $bnx = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $instance->id], 'id');
        if (!$bnx) {
            $bnxid = (int)$DB->insert_record('bbbext_bnx', (object)[
                'bigbluebuttonbnid' => $instance->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        } else {
            $bnxid = (int)$bnx->id;
        }

        foreach ($mapping as $settingname => $meta) {
            $corevalue = (int)!empty($instance->{$meta['corefield']});
            $bnxvalue = !empty($meta['invert']) ? (int)!$corevalue : $corevalue;

            $existing = $DB->get_record('bbbext_bnx_settings', [
                'bnxid' => $bnxid,
                'name' => $settingname,
            ], 'id, value');

            if ($existing) {
                if (!$overwriteexisting) {
                    continue;
                }

                if ((string)$existing->value !== (string)$bnxvalue) {
                    $DB->update_record('bbbext_bnx_settings', (object)[
                        'id' => $existing->id,
                        'value' => (string)$bnxvalue,
                        'timemodified' => $now,
                    ]);
                }
                continue;
            }

            $DB->insert_record('bbbext_bnx_settings', (object)[
                'bnxid' => $bnxid,
                'name' => $settingname,
                'value' => (string)$bnxvalue,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }
}

/**
 * Determine if the legacy locksettings sidecar still has config to migrate.
 *
 * @return bool
 */
function bbbext_bnx_has_legacy_bnx_locksettings_data(): bool {
    $component = 'bbbext_bnx_locksettings';
    $pm = \core_plugin_manager::instance();
    $installed = $pm->get_installed_plugins('bbbext');
    if (isset($installed['bnx_locksettings'])) {
        return true;
    }

    if (get_config($component, 'version') !== false) {
        return true;
    }

    $configkeys = [
        'cam_default',
        'cam_editable',
        'mic_default',
        'mic_editable',
        'publicchat_default',
        'publicchat_editable',
        'privatechat_default',
        'privatechat_editable',
        'notes_default',
        'notes_editable',
        'userlist_default',
        'userlist_editable',
        'hideviewerscursor_default',
        'hideviewerscursor_editable',
    ];

    foreach ($configkeys as $configname) {
        if (get_config($component, $configname) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Determine if BN Reminders appears to be installed or has legacy data to migrate.
 *
 * @return bool
 */
function bbbext_bnx_has_legacy_bnreminders_data(): bool {
    global $DB;

    $pm = \core_plugin_manager::instance();
    $installed = $pm->get_installed_plugins('bbbext');
    if (isset($installed['bnreminders'])) {
        return true;
    }

    if (get_config('bbbext_bnreminders', 'version') !== false) {
        return true;
    }

    $dbman = $DB->get_manager();
    return $dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table()))
        || $dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table('_rem')))
        || $dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table('_guests')));
}

/**
 * Migrate legacy per-instance reminder settings to bbbext_bnx_settings.
 *
 * @param int $now Current timestamp used for inserted rows.
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_instance_settings(int $now): void {
    global $DB;

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table()))) {
        return;
    }

    $records = $DB->get_records(bbbext_bnx_legacy_bnreminders_table());
    foreach ($records as $record) {
        $bnxrecord = $DB->get_record('bbbext_bnx', [
            'bigbluebuttonbnid' => $record->bigbluebuttonbnid,
        ]);
        if (!$bnxrecord) {
            continue;
        }

        $settingmap = [
            'reminderenabled' => isset($record->reminderenabled) ? (string) $record->reminderenabled : '0',
            'remindertoguestsenabled' => isset($record->remindertoguestsenabled)
                ? (string) $record->remindertoguestsenabled
                : '0',
        ];

        foreach ($settingmap as $settingname => $settingvalue) {
            $existing = $DB->get_record('bbbext_bnx_settings', [
                'bnxid' => $bnxrecord->id,
                'name' => $settingname,
            ], 'id, value');

            if ($existing) {
                if ((string) $existing->value !== $settingvalue) {
                    $DB->update_record('bbbext_bnx_settings', (object) [
                        'id' => $existing->id,
                        'value' => $settingvalue,
                        'timemodified' => $now,
                    ]);
                }
                continue;
            }

            $DB->insert_record('bbbext_bnx_settings', (object) [
                'bnxid' => $bnxrecord->id,
                'name' => $settingname,
                'value' => $settingvalue,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }
}

/**
 * Migrate reminder timespans into bbbext_bnx_reminders.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_reminders(): void {
    global $DB;

    $dbman = $DB->get_manager();
    if (
        !$dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table('_rem')))
        || !$dbman->table_exists(new xmldb_table('bbbext_bnx_reminders'))
    ) {
        return;
    }

    $records = $DB->get_records(bbbext_bnx_legacy_bnreminders_table('_rem'));
    foreach ($records as $record) {
        if (
            $DB->record_exists('bbbext_bnx_reminders', [
                'bigbluebuttonbnid' => $record->bigbluebuttonbnid,
                'timespan' => $record->timespan,
            ])
        ) {
            continue;
        }

        $DB->insert_record('bbbext_bnx_reminders', (object) [
            'bigbluebuttonbnid' => $record->bigbluebuttonbnid,
            'timespan' => $record->timespan,
            'lastsent' => (int) ($record->lastsent ?? 0),
        ]);
    }
}

/**
 * Migrate guest reminder recipients into bbbext_bnx_reminders_guests.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_guest_reminders(): void {
    global $DB;

    $dbman = $DB->get_manager();
    if (
        !$dbman->table_exists(new xmldb_table(bbbext_bnx_legacy_bnreminders_table('_guests')))
        || !$dbman->table_exists(new xmldb_table('bbbext_bnx_reminders_guests'))
    ) {
        return;
    }

    $records = $DB->get_records(bbbext_bnx_legacy_bnreminders_table('_guests'));
    foreach ($records as $record) {
        if (
            $DB->record_exists('bbbext_bnx_reminders_guests', [
                'email' => $record->email,
                'bigbluebuttonbnid' => $record->bigbluebuttonbnid,
                'userfrom' => $record->userfrom,
            ])
        ) {
            continue;
        }

        $DB->insert_record('bbbext_bnx_reminders_guests', (object) [
            'bigbluebuttonbnid' => $record->bigbluebuttonbnid,
            'email' => $record->email,
            'userfrom' => (int) ($record->userfrom ?? 0),
            'issent' => (int) ($record->issent ?? 0),
            'isenabled' => (int) ($record->isenabled ?? 1),
            'usermodified' => (int) ($record->usermodified ?? 0),
            'timecreated' => (int) ($record->timecreated ?? 0),
            'timemodified' => (int) ($record->timemodified ?? 0),
        ]);
    }
}

/**
 * Migrate bnreminders admin settings into bnx.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_admin_settings(): void {
    $oldconfigs = ['emailsubject', 'emailtemplate', 'emailfooter', 'emailcontent'];

    foreach ($oldconfigs as $configname) {
        $oldvalue = get_config('bbbext_bnreminders', $configname);
        if ($oldvalue === false) {
            continue;
        }
        set_config($configname, $oldvalue, 'bbbext_bnx');
    }
}

/**
 * Migrate user preferences to the bnx reminder namespace.
 *
 * @return void
 */
function bbbext_bnx_migrate_bnreminders_user_preferences(): void {
    global $DB;

    $oldprefs = $DB->get_recordset_sql(
        "SELECT userid, name, value
           FROM {user_preferences}
          WHERE name LIKE ?",
        ['bbbext_bnreminders_%']
    );

    foreach ($oldprefs as $pref) {
        $newname = str_replace('bbbext_bnreminders_', 'bbbext_bnx_reminder_', $pref->name);

        if (get_user_preferences($newname, null, (int) $pref->userid) !== null) {
            continue;
        }

        set_user_preference($newname, (string) $pref->value, (int) $pref->userid);
    }

    $oldprefs->close();
}
