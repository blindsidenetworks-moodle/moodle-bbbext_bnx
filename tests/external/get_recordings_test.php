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

namespace bbbext_bnx\external;

use core_external\external_api;
use core_external\restricted_context_exception;
use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\test\testcase_helper_trait;

/**
 * Tests for {@see \bbbext_bnx\external\get_recordings}.
 *
 * Locks the OL-3.2.1 remediation evidence for the remaining `PARAM_RAW`
 * response envelope: the JSON table payload is still safe because user-facing
 * recording values are sanitised before encoding.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_recordings::class)]
final class get_recordings_test extends \core_external\tests\externallib_testcase {
    use testcase_helper_trait;

    /**
     * Set up the external test environment.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Run the external function through Moodle's return-value cleaner.
     *
     * @param mixed ...$params
     * @return array
     */
    private function get_recordings(...$params): array {
        $recordings = get_recordings::execute(...$params);
        return external_api::clean_returnvalue(get_recordings::execute_returns(), $recordings);
    }

    /**
     * Recording names rendered into the JSON table payload must not expose raw
     * HTML even though the outer envelope is declared as `PARAM_RAW`.
     *
     * @return void
     */
    public function test_execute_sanitises_recording_name_inside_json_payload(): void {
        $this->initialise_mock_server();

        $dataset = [
            'type' => instance::TYPE_ALL,
            'groups' => null,
            'users' => [['username' => 't1', 'role' => 'editingteacher']],
            'recordingsdata' => [
                [[
                    'name' => '<script>alert(1)</script>Recording 1',
                    'description' => '<img src=x onerror=alert(1)>Description 1',
                ]],
            ],
        ];

        $activityid = $this->create_from_dataset($dataset);
        $instance = instance::get_from_instanceid($activityid);

        $user = \core_user::get_user_by_username('t1');
        $this->setUser($user);

        $result = $this->get_recordings($instance->get_instance_id());

        $this->assertTrue($result['status']);
        $this->assertIsString($result['tabledata']['data']);
        $this->assertStringNotContainsString('<script>', $result['tabledata']['data']);
        $this->assertStringNotContainsString('<img', $result['tabledata']['data']);

        $rows = json_decode($result['tabledata']['data']);
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertStringContainsString('Recording 1', $rows[0]->recording);
        $this->assertStringNotContainsString('<script>', $rows[0]->recording);
        $this->assertStringNotContainsString('<img', $rows[0]->description);
    }

    /**
     * Users must not be able to request recordings for a group they cannot
     * access.
     *
     * @return void
     */
    public function test_execute_throws_for_restricted_group_access(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmodeforce' => true, 'groupmode' => SEPARATEGROUPS]);
        $groupone = $generator->create_group(['name' => 'G1', 'courseid' => $course->id]);
        $grouptwo = $generator->create_group(['name' => 'G2', 'courseid' => $course->id]);
        $record = $generator->create_module('bigbluebuttonbn', ['course' => $course->id], ['visible' => true]);
        $instance = instance::get_from_instanceid($record->id);

        $studentone = $generator->create_and_enrol($course, 'student', ['username' => 's1']);
        $generator->create_group_member(['userid' => $studentone->id, 'groupid' => $groupone->id]);
        $studenttwo = $generator->create_and_enrol($course, 'student', ['username' => 's2']);
        $generator->create_group_member(['userid' => $studenttwo->id, 'groupid' => $grouptwo->id]);

        $this->setUser($studentone);

        $this->expectException(restricted_context_exception::class);

        $this->get_recordings($instance->get_instance_id(), null, $grouptwo->id);
    }
}
