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

use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\logger;
use mod_bigbluebuttonbn\test\testcase_helper_trait;

/**
 * Tests for bbbext_bnx\meeting.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversClass(meeting::class)]
final class meeting_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Set up test environment with mock server.
     */
    public function setUp(): void {
        parent::setUp();
        $this->initialise_mock_server();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Test that join_meeting recovers a missing recording row when a prior join attempt
     * failed after BigBlueButton created the meeting but before the database insert completed.
     *
     */
    public function test_join_meeting_recovers_missing_recording_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');

        // Create a recorded activity.
        $activity = $bbbgenerator->create_instance([
            'course' => $this->get_course()->id,
            'record' => 1,
        ]);
        $instance = instance::get_from_instanceid($activity->id);

        // Simulate the partial failure: BigBlueButton has the meeting on its side (the create
        // API call succeeded) but no recording row was written to Moodle's database (the DB
        // insert never ran because the PHP process failed between the two operations).
        $bbbgenerator->create_meeting(['instanceid' => $instance->get_instance_id()]);

        $this->assertFalse(
            $DB->record_exists('bigbluebuttonbn_recordings', ['bigbluebuttonbnid' => $activity->id]),
            'No recording row should exist before join_meeting() is called'
        );

        // The user retries joining. join_meeting() detects the meeting is already running on
        // BigBlueButton and should recover the missing recording row using the internalMeetingID
        // returned by getMeetingInfo.
        meeting::join_meeting($instance, logger::ORIGIN_BASE);

        $this->assertTrue(
            $DB->record_exists('bigbluebuttonbn_recordings', ['bigbluebuttonbnid' => $activity->id]),
            'Recording row should have been created by the join_meeting() recovery logic'
        );
    }
}
