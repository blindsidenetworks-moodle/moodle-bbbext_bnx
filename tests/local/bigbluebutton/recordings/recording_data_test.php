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
 * Test definitions for BNX recording table data.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\local\bigbluebutton\recordings;

use bbbext_bnx\recording;
use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\test\testcase_helper_trait;

/**
 * Tests for BNX recording table data.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(recording_data::class)]
final class recording_data_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Headless recording metadata is rendered directly by the table template,
     * so it must be HTML-escaped before it reaches the template.
     *
     * @return void
     */
    public function test_headless_recording_metadata_is_html_escaped(): void {
        $this->initialise_mock_server();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($user);

        $recordings = $this->create_activity_with_recordings(
            $course,
            instance::TYPE_ALL,
            [[
                'name' => '<script>alert(1)</script>Recording',
                'description' => '<img src=x onerror=alert(1)>Description',
            ]]
        );
        $instance = instance::get_from_instanceid($recordings['activity']->id);
        $recordinglist = recording::get_recordings_for_instance($instance);
        $recording = reset($recordinglist);

        $row = recording_data::row(null, $recording, [], $course->id);

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('<script>', $row->recording);
        $this->assertStringNotContainsString('<img', $row->description);
        $this->assertStringContainsString('&lt;script&gt;', $row->recording);
        $this->assertStringContainsString('&lt;img', $row->description);
    }
}
