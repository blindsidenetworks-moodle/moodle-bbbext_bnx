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

use bbbext_bnx\local\helpers\presentation_user_helper;

/**
 * Tests for BNX uninstall cleanup.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(presentation_user_helper::class)]
final class uninstall_test extends \advanced_testcase {
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest(true);
        require_once($CFG->dirroot . '/mod/bigbluebuttonbn/extension/bnx/db/uninstall.php');
    }

    /**
     * Uninstall removes the account and preferences that BNX owns.
     */
    public function test_uninstall_removes_owned_account_and_reminder_preferences(): void {
        global $DB;

        $user = presentation_user_helper::get_or_create_user();
        set_config(presentation_user_helper::USERID_CONFIG, $user->id, 'bbbext_bnx');
        presentation_user_helper::migrate_owned_user_to_webservice();
        $otheruser = $this->getDataGenerator()->create_user();
        set_user_preference('bbbext_bnx_reminder_1', '1', $user->id);
        set_user_preference('bbbext_bnx_reminder_2', '1', $otheruser->id);
        set_user_preference('unrelated_preference', '1', $otheruser->id);

        $this->assertTrue(xmldb_bbbext_bnx_uninstall());

        $this->assertSame(1, (int) $DB->get_field('user', 'deleted', ['id' => $user->id]));
        $this->assertFalse($DB->record_exists_select(
            'user_preferences',
            $DB->sql_like('name', ':preference'),
            ['preference' => $DB->sql_like_escape('bbbext_bnx_reminder_') . '%']
        ));
        $this->assertTrue($DB->record_exists('user_preferences', [
            'userid' => $otheruser->id,
            'name' => 'unrelated_preference',
        ]));
        $this->assertFalse(get_config('bbbext_bnx', presentation_user_helper::USERID_CONFIG));
    }

    /**
     * A marked legacy account is safely made non-interactive by the upgrade.
     */
    public function test_migrate_owned_user_to_webservice_is_idempotent(): void {
        global $DB;

        $user = presentation_user_helper::get_or_create_user();
        $DB->set_field('user', 'auth', 'manual', ['id' => $user->id]);
        $DB->set_field('user', 'email', 'bnx_preuploads_systemuser@noemail.blindsidenetworks.com', ['id' => $user->id]);
        set_config(presentation_user_helper::USERID_CONFIG, $user->id, 'bbbext_bnx');

        presentation_user_helper::migrate_owned_user_to_webservice();
        presentation_user_helper::migrate_owned_user_to_webservice();

        $user = $DB->get_record('user', ['id' => $user->id], 'auth, email', MUST_EXIST);
        $this->assertSame('webservice', $user->auth);
        $this->assertSame('bnx_preuploads_systemuser@invalid', $user->email);
    }

    /**
     * Uninstall must leave an account without BNX's ownership marker untouched.
     */
    public function test_uninstall_preserves_unowned_service_name_account(): void {
        global $DB;

        $user = presentation_user_helper::get_or_create_user();
        unset_config(presentation_user_helper::USERID_CONFIG, 'bbbext_bnx');

        $this->assertTrue(xmldb_bbbext_bnx_uninstall());

        $this->assertSame(0, (int) $DB->get_field('user', 'deleted', ['id' => $user->id]));
    }
}
