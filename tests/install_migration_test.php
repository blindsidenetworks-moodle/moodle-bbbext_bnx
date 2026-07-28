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

namespace bbbext_bnx;

/**
 * Tests for install/upgrade migration from BN Reminders.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class install_migration_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        require_once(__DIR__ . '/../db/migration.php');
    }

    /**
     * BN reminders settings/data should be migrated and plugin disabled.
     */
    public function test_migrate_bnreminders_data_and_disable_plugin(): void {
        global $DB;

        $this->create_legacy_bnreminders_tables();

        $course = $this->getDataGenerator()->create_course();
        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $DB->delete_records(bbbext_bnx_legacy_bnreminders_table(), ['bigbluebuttonbnid' => $bbb->id]);
        $DB->delete_records(bbbext_bnx_legacy_bnreminders_table('_rem'), ['bigbluebuttonbnid' => $bbb->id]);
        $DB->delete_records(bbbext_bnx_legacy_bnreminders_table('_guests'), ['bigbluebuttonbnid' => $bbb->id]);

        $bnxid = $this->ensure_bnx_instance($bbb->id);

        $DB->insert_record(bbbext_bnx_legacy_bnreminders_table(), (object) [
            'bigbluebuttonbnid' => $bbb->id,
            'reminderenabled' => 1,
            'remindertoguestsenabled' => 1,
        ]);

        $DB->insert_record(bbbext_bnx_legacy_bnreminders_table('_rem'), (object) [
            'bigbluebuttonbnid' => $bbb->id,
            'timespan' => 'PT1H',
            'lastsent' => 123,
        ]);

        $DB->insert_record(bbbext_bnx_legacy_bnreminders_table('_guests'), (object) [
            'bigbluebuttonbnid' => $bbb->id,
            'email' => 'legacy@example.com',
            'userfrom' => $userid,
            'issent' => 0,
            'isenabled' => 1,
            'usermodified' => $userid,
            'timecreated' => 111,
            'timemodified' => 222,
        ]);

        set_config('emailsubject', 'Legacy Subject', 'bbbext_bnreminders');
        set_config('emailtemplate', '<p>Legacy Template</p>', 'bbbext_bnreminders');
        set_config('emailfooter', '<p>Legacy Footer</p>', 'bbbext_bnreminders');
        set_config('emailcontent', 'Legacy Content', 'bbbext_bnreminders');
        set_config('version', '2025100700', 'bbbext_bnreminders');

        $DB->insert_record('user_preferences', (object) [
            'userid' => $userid,
            'name' => 'bbbext_bnreminders_' . $bbb->id,
            'value' => '1',
        ]);

        \core_plugin_manager::reset_caches();

        bbbext_bnx_migrate_bnreminders_data();

        $reminderenabled = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'reminderenabled',
        ], '*', MUST_EXIST);
        $this->assertSame('1', (string) $reminderenabled->value);

        $remindertoguestsenabled = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'remindertoguestsenabled',
        ], '*', MUST_EXIST);
        $this->assertSame('1', (string) $remindertoguestsenabled->value);

        $this->assertTrue($DB->record_exists('bbbext_bnx_reminders', [
            'bigbluebuttonbnid' => $bbb->id,
            'timespan' => 'PT1H',
        ]));

        $this->assertTrue($DB->record_exists('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $bbb->id,
            'email' => 'legacy@example.com',
            'userfrom' => $userid,
        ]));

        $this->assertSame('Legacy Subject', get_config('bbbext_bnx', 'emailsubject'));
        $this->assertSame('<p>Legacy Template</p>', get_config('bbbext_bnx', 'emailtemplate'));
        $this->assertSame('<p>Legacy Footer</p>', get_config('bbbext_bnx', 'emailfooter'));
        $this->assertSame('Legacy Content', get_config('bbbext_bnx', 'emailcontent'));

        $newpreference = $DB->get_record('user_preferences', [
            'userid' => $userid,
            'name' => 'bbbext_bnx_reminder_' . $bbb->id,
        ], '*', MUST_EXIST);
        $this->assertSame('1', (string) $newpreference->value);

        $this->assertSame('1', get_config('bbbext_bnreminders', 'disabled'));
    }

    /**
     * Running migration multiple times should not duplicate records.
     */
    public function test_migration_is_idempotent(): void {
        global $DB;

        $this->create_legacy_bnreminders_tables();

        $course = $this->getDataGenerator()->create_course();
        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);

        $DB->delete_records(bbbext_bnx_legacy_bnreminders_table(), ['bigbluebuttonbnid' => $bbb->id]);
        $DB->delete_records(bbbext_bnx_legacy_bnreminders_table('_rem'), ['bigbluebuttonbnid' => $bbb->id]);

        $bnxid = $this->ensure_bnx_instance($bbb->id);

        $DB->insert_record(bbbext_bnx_legacy_bnreminders_table(), (object) [
            'bigbluebuttonbnid' => $bbb->id,
            'reminderenabled' => 1,
            'remindertoguestsenabled' => 0,
        ]);

        $DB->insert_record(bbbext_bnx_legacy_bnreminders_table('_rem'), (object) [
            'bigbluebuttonbnid' => $bbb->id,
            'timespan' => 'P1D',
            'lastsent' => 0,
        ]);

        set_config('version', '2025100700', 'bbbext_bnreminders');
        \core_plugin_manager::reset_caches();

        bbbext_bnx_migrate_bnreminders_data();
        bbbext_bnx_migrate_bnreminders_data();

        $this->assertSame(1, $DB->count_records('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'reminderenabled',
        ]));

        $this->assertSame(1, $DB->count_records('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'remindertoguestsenabled',
        ]));

        $this->assertSame(1, $DB->count_records('bbbext_bnx_reminders', [
            'bigbluebuttonbnid' => $bbb->id,
            'timespan' => 'P1D',
        ]));
    }

    /**
     * User preference migration should migrate multiple legacy reminder rows.
     */
    public function test_migrate_user_preferences_migrates_multiple_rows(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $bbb1 = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $bbb2 = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);

        $user1 = (int) $this->getDataGenerator()->create_user()->id;
        $user2 = (int) $this->getDataGenerator()->create_user()->id;

        $DB->insert_record('user_preferences', (object) [
            'userid' => $user1,
            'name' => 'bbbext_bnreminders_' . $bbb1->id,
            'value' => '1',
        ]);
        $DB->insert_record('user_preferences', (object) [
            'userid' => $user2,
            'name' => 'bbbext_bnreminders_' . $bbb2->id,
            'value' => '0',
        ]);

        bbbext_bnx_migrate_bnreminders_user_preferences();

        $this->assertSame('1', (string) get_user_preferences('bbbext_bnx_reminder_' . $bbb1->id, null, $user1));
        $this->assertSame('0', (string) get_user_preferences('bbbext_bnx_reminder_' . $bbb2->id, null, $user2));
    }

    /**
     * User preference migration should not overwrite an existing BNX preference.
     */
    public function test_migrate_user_preferences_preserves_existing_new_preference(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $userid = (int) $this->getDataGenerator()->create_user()->id;

        $DB->insert_record('user_preferences', (object) [
            'userid' => $userid,
            'name' => 'bbbext_bnreminders_' . $bbb->id,
            'value' => '1',
        ]);
        set_user_preference('bbbext_bnx_reminder_' . $bbb->id, '0', $userid);

        bbbext_bnx_migrate_bnreminders_user_preferences();

        $this->assertSame('0', (string) get_user_preferences('bbbext_bnx_reminder_' . $bbb->id, null, $userid));
    }

    /**
     * Core lock settings should migrate into BNX settings with proper inversion.
     */
    public function test_migrate_core_locksettings_with_inverted_logic(): void {
        global $DB;

        unset_config('locksettings_core_migrated', 'bbbext_bnx');
        unset_config('cam_default', 'bbbext_bnx');
        unset_config('cam_editable', 'bbbext_bnx');
        unset_config('userlist_default', 'bbbext_bnx');
        unset_config('userlist_editable', 'bbbext_bnx');

        // Core admin config semantics are disable/hide by default.
        set_config('disablecam_default', 1, 'mod_bigbluebuttonbn');
        set_config('disablecam_editable', 1, 'mod_bigbluebuttonbn');
        set_config('hideuserlist_default', 0, 'mod_bigbluebuttonbn');
        set_config('hideuserlist_editable', 1, 'mod_bigbluebuttonbn');

        $course = $this->getDataGenerator()->create_course();
        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', [
            'course' => $course->id,
            'disablecam' => 1,
            'hideuserlist' => 0,
        ]);

        // Ensure the source core values are exactly what the migration should convert.
        $DB->set_field('bigbluebuttonbn', 'disablecam', 1, ['id' => $bbb->id]);
        $DB->set_field('bigbluebuttonbn', 'hideuserlist', 0, ['id' => $bbb->id]);

        // Ensure lock settings are not already present for this activity.
        $bnxid = $this->ensure_bnx_instance($bbb->id);
        $DB->delete_records('bbbext_bnx_settings', ['bnxid' => $bnxid, 'name' => 'enablecam']);
        $DB->delete_records('bbbext_bnx_settings', ['bnxid' => $bnxid, 'name' => 'enableuserlist']);

        bbbext_bnx_migrate_core_locksettings_data();

        // Admin defaults are inverted from core disable/hide logic.
        $this->assertSame('0', (string)get_config('bbbext_bnx', 'cam_default'));
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'cam_editable'));
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'userlist_default'));
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'userlist_editable'));

        // Per-instance values are also inverted into BNX enable/show settings.
        $bnx = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $bbb->id], 'id', MUST_EXIST);
        $camsetting = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnx->id,
            'name' => 'enablecam',
        ], '*', MUST_EXIST);
        $userlistsetting = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnx->id,
            'name' => 'enableuserlist',
        ], '*', MUST_EXIST);

        $this->assertSame('0', (string)$camsetting->value);
        $this->assertSame('1', (string)$userlistsetting->value);
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'locksettings_core_migrated'));
    }

    /**
     * Core lock migration should overwrite initial BNX defaults on first run.
     *
     * @return void
     */
    public function test_migrate_core_locksettings_overwrites_existing_bnx_defaults_once(): void {
        unset_config('locksettings_core_migrated', 'bbbext_bnx');

        // Simulate pre-existing BNX defaults created before migration runs.
        set_config('cam_default', 1, 'bbbext_bnx');
        set_config('cam_editable', 1, 'bbbext_bnx');

        // Core semantics: 1 means disabled in core, so BNX default should become 0 after inversion.
        set_config('disablecam_default', 1, 'mod_bigbluebuttonbn');
        set_config('disablecam_editable', 0, 'mod_bigbluebuttonbn');

        bbbext_bnx_migrate_core_locksettings_data();

        $this->assertSame('0', (string)get_config('bbbext_bnx', 'cam_default'));
        $this->assertSame('0', (string)get_config('bbbext_bnx', 'cam_editable'));
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'locksettings_core_migrated'));
    }

    /**
     * Core lock settings should be synchronized on enable without duplicating rows.
     *
     * @return void
     */
    public function test_sync_core_locksettings_updates_existing_values_without_duplicates(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $bbb = $this->getDataGenerator()->create_module('bigbluebuttonbn', [
            'course' => $course->id,
            'disablecam' => 1,
        ]);

        // Existing BNX values (stale) that should be refreshed by sync.
        $bnxid = $this->ensure_bnx_instance($bbb->id);
        $existing = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'enablecam',
        ], 'id');
        if ($existing) {
            $DB->set_field('bbbext_bnx_settings', 'value', '0', ['id' => $existing->id]);
        } else {
            $DB->insert_record('bbbext_bnx_settings', (object) [
                'bnxid' => $bnxid,
                'name' => 'enablecam',
                'value' => '0',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        // Simulate core changes while BNX is disabled.
        $DB->set_field('bigbluebuttonbn', 'disablecam', 0, ['id' => $bbb->id]);
        set_config('disablecam_default', 0, 'mod_bigbluebuttonbn');
        set_config('disablecam_editable', 1, 'mod_bigbluebuttonbn');

        bbbext_bnx_sync_core_locksettings_data();

        $camsetting = $DB->get_record('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'enablecam',
        ], '*', MUST_EXIST);

        // Core disablecam=0 becomes BNX enablecam=1.
        $this->assertSame('1', (string)$camsetting->value);

        // Admin defaults/editability should also be refreshed.
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'cam_default'));
        $this->assertSame('1', (string)get_config('bbbext_bnx', 'cam_editable'));

        // No duplicate settings row should be created.
        $this->assertSame(1, $DB->count_records('bbbext_bnx_settings', [
            'bnxid' => $bnxid,
            'name' => 'enablecam',
        ]));
    }

    /**
     * Create the legacy BN reminders tables used by migration.
     */
    private function create_legacy_bnreminders_tables(): void {
        global $DB;

        $dbman = $DB->get_manager();

        $table = new \xmldb_table(bbbext_bnx_legacy_bnreminders_table());
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('bigbluebuttonbnid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('reminderenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('remindertoguestsenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        } else {
            $field = new \xmldb_field('remindertoguestsenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new \xmldb_table(bbbext_bnx_legacy_bnreminders_table('_rem'));
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('bigbluebuttonbnid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timespan', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('lastsent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new \xmldb_table(bbbext_bnx_legacy_bnreminders_table('_guests'));
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('bigbluebuttonbnid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('email', XMLDB_TYPE_CHAR, '254');
        $table->add_field('userfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issent', XMLDB_TYPE_INTEGER, '1', null, null, null, '0');
        $table->add_field('isenabled', XMLDB_TYPE_INTEGER, '1', null, null, null, '1');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    /**
     * Ensure there is a BNX row for the given activity id and return its id.
     *
     * @param int $activityid
     * @return int
     */
    private function ensure_bnx_instance(int $activityid): int {
        global $DB;

        $existing = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $activityid], 'id');
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) $DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $activityid,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }
}
