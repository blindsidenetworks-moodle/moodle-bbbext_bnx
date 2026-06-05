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
 * @coversDefaultClass \bbbext_bnx\external\get_recordings
 */
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
        $this->initialise_mock_server();
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
     * @covers ::execute
     * @covers ::execute_returns
     * @return void
     */
    public function test_execute_sanitises_recording_name_inside_json_payload(): void {
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
}
