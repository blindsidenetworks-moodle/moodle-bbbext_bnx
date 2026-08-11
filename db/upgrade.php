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
 * Upgrade steps for bbbext_bnx.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */

/**
 * Execute the upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_bbbext_bnx_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    require_once(__DIR__ . '/migration.php');

    if ($oldversion < 2026031101) {
        // Historical step. Cross-plugin enablement (mod_bigbluebuttonbn) and sidecar
        // on_enable discovery were removed under OL-3.1.3. Site administrators
        // explicitly manage parent and sibling plugin enablement.
        upgrade_plugin_savepoint(true, 2026031101, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026040100) {
        // Create bbbext_bnx_reminders table (stores individual reminder timespans).
        $table = new xmldb_table('bbbext_bnx_reminders');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('bigbluebuttonbnid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timespan', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('lastsent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_bigbluebuttonbnid', XMLDB_KEY_FOREIGN, ['bigbluebuttonbnid'], 'bigbluebuttonbn', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create bbbext_bnx_reminders_guests table.
        $table = new xmldb_table('bbbext_bnx_reminders_guests');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('bigbluebuttonbnid', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('email', XMLDB_TYPE_CHAR, '254');
        $table->add_field('userfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issent', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('isenabled', XMLDB_TYPE_INTEGER, '1', null, null, null, '1');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('bigbluebuttonbnid_fk', XMLDB_KEY_FOREIGN, ['bigbluebuttonbnid'], 'bigbluebuttonbn', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('userfrom', XMLDB_KEY_FOREIGN, ['userfrom'], 'user', ['id']);
        $table->add_key('bbbemail_ux', XMLDB_KEY_UNIQUE, ['email', 'bigbluebuttonbnid', 'userfrom']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026040100, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026040101) {
        // Backfill legacy bnreminders migration for existing BNX 1.1 sites.
        bbbext_bnx_migrate_bnreminders_data();

        upgrade_plugin_savepoint(true, 2026040101, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026050101) {
        // One-time migration from core lock settings into BNX lock settings.
        bbbext_bnx_migrate_core_locksettings_data();

        upgrade_plugin_savepoint(true, 2026050101, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026050102) {
        // 1.1.1: release-only version bump. This shipped as a stable release with
        // code fixes only, so there is no schema or data migration to execute.
        // The explicit savepoint must remain for sites already upgraded to 1.1.1.
        upgrade_plugin_savepoint(true, 2026050102, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026050103) {
        // 1.1.1.1: hotfix release-only version bump. This shipped with runtime
        // fixes only, so there is no schema or data migration to execute.
        // The explicit savepoint must remain for sites already upgraded to 1.1.1.1.
        upgrade_plugin_savepoint(true, 2026050103, 'bbbext', 'bnx');
    }

    if ($oldversion < 2026081000) {
        // 1.2 release line: 2026060200, 2026070200 and 2026072700 were internal dev
        // markers and were not released externally, so no standalone savepoints are kept.
        // Until the final 1.2 release is cut, keep a single savepoint here for
        // the pre-final line; 1.2-rc.2 is marker-only (no schema/data migration)
        // and keeps upgrades from earlier 1.1.x sites clean.
        upgrade_plugin_savepoint(true, 2026081000, 'bbbext', 'bnx');
    }

    return true;
}
