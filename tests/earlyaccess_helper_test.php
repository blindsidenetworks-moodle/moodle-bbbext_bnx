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

use bbbext_bnx\local\helpers\earlyaccess_helper;
use bbbext_bnx\local\services\bnx_settings_service;
use mod_bigbluebuttonbn\instance;

/**
 * Tests for BNX Early Access meeting rules.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(earlyaccess_helper::class)]
final class earlyaccess_helper_test extends \advanced_testcase {
    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
        \core_plugin_manager::reset_caches();
    }

    /**
     * Test an authorised user receives Early Access from the instance setting.
     *
     * @return void
     */
    public function test_authorised_user_can_join_early_from_instance_setting(): void {
        $this->setAdminUser();
        $instance = $this->create_future_instance();
        $bnxid = $this->ensure_bnx_record($instance->get_instance_id());

        set_config('earlyaccess_editable', 1, 'bbbext_bnx');
        set_config('earlyaccess_default', 0, 'bbbext_bnx');
        bnx_settings_service::get_service()->set_settings($bnxid, [
            'bnx_earlyaccess_enable_access' => 1,
        ]);

        $meetingdata = (object) [
            'canjoin' => false,
            'statusmessage' => '',
            'statusrunning' => false,
        ];
        $result = earlyaccess_helper::adjust_meeting_data($instance, $meetingdata);

        $this->assertTrue($result->canjoin);
        $this->assertSame(get_string('view_early_message', 'bbbext_bnx'), $result->statusmessage);

        bnx_settings_service::get_service()->set_settings($bnxid, [
            'bnx_earlyaccess_enable_access' => 0,
        ]);
        $this->assertFalse(earlyaccess_helper::user_has_early_access($instance));
    }

    /**
     * Test non-moderators cannot infer a running meeting before its opening time.
     *
     * @return void
     */
    public function test_non_moderator_cannot_see_preopening_meeting_is_running(): void {
        $this->setAdminUser();
        $instance = $this->create_future_instance();
        $this->setUser($this->getDataGenerator()->create_user());

        $meetingdata = (object) [
            'canjoin' => false,
            'statusmessage' => '',
            'statusrunning' => true,
        ];
        $result = earlyaccess_helper::adjust_meeting_data($instance, $meetingdata);

        $this->assertFalse($result->statusrunning);
        $this->assertSame(
            get_string('view_message_conference_not_started', 'mod_bigbluebuttonbn'),
            $result->statusmessage
        );
    }

    /**
     * Create a BigBlueButton activity with an opening time in the future.
     *
     * @return instance
     */
    private function create_future_instance(): instance {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $generator->create_instance([
            'course' => $course->id,
            'openingtime' => time() + HOURSECS,
        ]);

        return instance::get_from_instanceid($activity->id);
    }

    /**
     * Ensure the BNX base record exists for a module instance.
     *
     * @param int $moduleid Module instance identifier.
     * @return int
     */
    private function ensure_bnx_record(int $moduleid): int {
        global $DB;

        $record = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $moduleid]);
        if ($record) {
            return (int)$record->id;
        }

        $now = time();
        return (int)$DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $moduleid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
