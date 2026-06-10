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

use mod_bigbluebuttonbn\test\testcase_helper_trait;

/**
 * Tests for {@see \bbbext_bnx\external\get_meeting_info}.
 *
 * Locks the OL-3.2.10 remediation: even though the subclass extends
 * `mod_bigbluebuttonbn\external\meeting_info`, `execute()` must run
 * `validate_parameters()` against `execute_parameters()` first and use the
 * cleaned values for the remainder of the call. The happy-path test below
 * documents that the override still augments the response with the BNX-only
 * `presentationtitle` key so that the validate-parameters wiring has not
 * regressed the BNX-specific response shape.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_meeting_info::class)]
final class get_meeting_info_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Set up test environment with mock server.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->initialise_mock_server();
    }

    /**
     * `execute_parameters()` must keep the contract advertised in
     * `db/services.php`: three named parameters with the documented types.
     * If a future change drops any of these, both `validate_parameters()`
     * inside `execute()` and external clients would silently break.
     *
     * @return void
     */
    public function test_execute_parameters_describes_expected_inputs(): void {
        $params = get_meeting_info::execute_parameters();

        $this->assertArrayHasKey('bigbluebuttonbnid', $params->keys);
        $this->assertArrayHasKey('groupid', $params->keys);
        $this->assertArrayHasKey('updatecache', $params->keys);
    }

    /**
     * `execute_returns()` must extend the parent structure with the BNX-only
     * `presentationtitle` key so the subclass response shape stays stable.
     *
     * @return void
     */
    public function test_execute_returns_includes_presentation_title(): void {
        $returns = get_meeting_info::execute_returns();

        $this->assertArrayHasKey('presentationtitle', $returns->keys);
    }

    /**
     * Happy-path execution against a real instance: returns an array with the
     * BNX-only `presentationtitle` key populated. Exercises the full
     * `validate_parameters()` + `parent::execute()` + override pipeline.
     *
     * @return void
     */
    public function test_execute_returns_array_with_presentation_title(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance(['course' => $course->id]);

        $result = get_meeting_info::execute(
            bigbluebuttonbnid: (int) $activity->id,
            groupid: 0,
            updatecache: false
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('presentationtitle', $result);
        $this->assertNotSame('', $result['presentationtitle']);
    }
}
