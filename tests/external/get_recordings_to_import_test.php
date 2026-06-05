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
 * Tests for {@see \bbbext_bnx\external\get_recordings_to_import}.
 *
 * Locks two remediations:
 *   - OL-3.1.6: `instance::get_from_instanceid()` results are guarded; a
 *     bogus destination or source ID must throw `invalid_parameter_exception`
 *     rather than fatal on a null dereference.
 *   - OL-3.2.10 / External Functions guide: `execute()` runs `validate_parameters()`
 *     before touching any other state.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @coversDefaultClass \bbbext_bnx\external\get_recordings_to_import
 */
final class get_recordings_to_import_test extends \advanced_testcase {
    use testcase_helper_trait;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * A non-existent destination instance id must throw
     * `invalid_parameter_exception` from the null-guard, not a fatal
     * "method call on null" error.
     *
     * @covers ::execute
     * @return void
     */
    public function test_execute_throws_invalid_parameter_when_destination_missing(): void {
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/Destination BigBlueButton ID is invalid/');

        get_recordings_to_import::execute(
            destinstanceid: 999999,
            sourceinstanceid: 0,
            sourcecourseid: 0,
            tools: 'protect,unprotect,publish,unpublish,delete',
            groupid: null
        );
    }

    /**
     * A non-existent source instance id (when a destination is valid) must
     * throw `invalid_parameter_exception` from the null-guard.
     *
     * @covers ::execute
     * @return void
     */
    public function test_execute_throws_invalid_parameter_when_source_missing(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $destactivity = $bbbgenerator->create_instance(['course' => $course->id]);

        $this->expectException(\invalid_parameter_exception::class);
        $this->expectExceptionMessageMatches('/Source BigBlueButton ID is invalid/');

        get_recordings_to_import::execute(
            destinstanceid: (int) $destactivity->id,
            sourceinstanceid: 999999,
            sourcecourseid: 0,
            tools: 'protect,unprotect,publish,unpublish,delete',
            groupid: null
        );
    }

    /**
     * A non-existent source course id must surface as a dml/db not-found
     * exception via `MUST_EXIST` rather than silently returning an empty list.
     *
     * Documents that `normalise_parameters()` validates source-course existence
     * before any expensive recording lookup.
     *
     * @covers ::execute
     * @return void
     */
    public function test_execute_throws_when_source_course_missing(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $destactivity = $bbbgenerator->create_instance(['course' => $course->id]);

        $this->expectException(\dml_exception::class);

        get_recordings_to_import::execute(
            destinstanceid: (int) $destactivity->id,
            sourceinstanceid: 0,
            sourcecourseid: 999999,
            tools: 'protect,unprotect,publish,unpublish,delete',
            groupid: null
        );
    }

    /**
     * Happy path: a valid destination instance yields a successful response
     * with the documented envelope (status + tabledata + warnings) so the
     * `validate_parameters()` and null-guard rewrites have not regressed the
     * normal flow.
     *
     * @covers ::execute
     * @return void
     */
    public function test_execute_returns_envelope_for_valid_destination(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $destactivity = $bbbgenerator->create_instance(['course' => $course->id]);

        $result = get_recordings_to_import::execute(
            destinstanceid: (int) $destactivity->id,
            sourceinstanceid: 0,
            sourcecourseid: 0,
            tools: 'protect,unprotect,publish,unpublish,delete',
            groupid: null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertTrue($result['status']);
    }

    /**
     * Users without the import capability must be denied before any recording
     * import data is returned.
     *
     * @covers ::execute
     * @return void
     */
    public function test_execute_throws_when_import_capability_is_missing(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $bbbgenerator = $generator->get_plugin_generator('mod_bigbluebuttonbn');
        $destactivity = $bbbgenerator->create_instance(['course' => $course->id]);
        $user = $generator->create_and_enrol($course, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);

        get_recordings_to_import::execute(
            destinstanceid: (int) $destactivity->id,
            sourceinstanceid: 0,
            sourcecourseid: 0,
            tools: 'protect,unprotect,publish,unpublish,delete',
            groupid: null
        );
    }
}
