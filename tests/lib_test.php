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
 * Tests for BigBlueButton BN Experience
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lib_test extends \advanced_testcase {
    /**
     * Plugin version should be available after installation.
     */
    public function test_plugin_installed(): void {
        $this->assertNotEmpty(get_config('bbbext_bnx', 'version'));
    }

    /**
     * Guest users must not access BNX presentation files.
     *
     * @return void
     */
    public function test_pluginfile_rejects_guest_user(): void {
        $this->resetAfterTest();
        [$course, $cm, $context] = $this->create_activity();
        $this->setGuestUser();

        $this->expectException(\core\exception\moodle_exception::class);
        bbbext_bnx_pluginfile($course, $cm, $context, 'presentation', [0, 'presentation.pdf'], false);
    }

    /**
     * A presentation record for another activity must not authorize a file.
     *
     * @return void
     */
    public function test_pluginfile_rejects_presentation_linked_to_another_activity(): void {
        global $DB;

        $this->resetAfterTest();
        [, $firstcm] = $this->create_activity();
        [$secondcourse, $secondcm, $secondcontext] = $this->create_activity();
        $this->setAdminUser();

        $file = get_file_storage()->create_file_from_string([
            'contextid' => $secondcontext->id,
            'component' => 'bbbext_bnx',
            'filearea' => 'presentation',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'presentation.pdf',
        ], 'Presentation content');
        $now = time();
        $bnxid = $DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $firstcm->instance,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $DB->insert_record('bbbext_bnx_presentations', (object) [
            'bnxid' => $bnxid,
            'fileid' => $file->get_id(),
            'filename' => $file->get_filename(),
        ]);

        $this->assertFalse(
            bbbext_bnx_pluginfile($secondcourse, $secondcm, $secondcontext, 'presentation', [0, 'presentation.pdf'], false)
        );
    }

    /**
     * Create a BigBlueButton activity and return its course module and context.
     *
     * @return array{\stdClass, \stdClass, \context_module}
     */
    private function create_activity(): array {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $activity->id, $course->id, false, MUST_EXIST);

        return [$course, $cm, \context_module::instance($cm->id)];
    }
}
