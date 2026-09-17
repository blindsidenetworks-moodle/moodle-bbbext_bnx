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

use bbbext_bnx\local\helpers\presentation_helper;
use restore_date_testcase;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**
 * Tests BNX presentation backup and restore behaviour.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_bbbext_bnx_subplugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_bbbext_bnx_subplugin::class)]
final class backup_restore_test extends restore_date_testcase {
    /**
     * Test a restored presentation record points to its restored stored file.
     *
     * @return void
     */
    public function test_backup_restore_presentation(): void {
        global $DB;

        $this->resetAfterTest(true);
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
        unset_config('disabled', 'bbbext_bnx');
        set_config('disabled', 'disabled', 'bbbext_bnx_preuploads');
        \core_plugin_manager::reset_caches();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('bigbluebuttonbn', [
            'course' => $course->id,
            'name' => 'Presentation room',
        ]);
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $activity->id, $course->id, false, MUST_EXIST);
        $bnx = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $activity->id], 'id', IGNORE_MISSING);
        $bnxid = $bnx === false ? $DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $activity->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]) : (int)$bnx->id;
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($cm->id)->id,
            'component' => 'bbbext_bnx',
            'filearea' => presentation_helper::FILEAREA,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'slides.pdf',
        ], 'Presentation content');
        $DB->insert_record(presentation_helper::PRESENTATIONS_TABLE, (object) [
            'bnxid' => $bnxid,
            'fileid' => $file->get_id(),
            'filename' => $file->get_filename(),
        ]);

        $newcourseid = $this->backup_and_restore($course);
        $restoredactivity = $DB->get_record('bigbluebuttonbn', [
            'course' => $newcourseid,
            'name' => 'Presentation room',
        ], '*', MUST_EXIST);
        $restoredbnx = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $restoredactivity->id], '*', MUST_EXIST);
        $presentation = $DB->get_record(
            presentation_helper::PRESENTATIONS_TABLE,
            ['bnxid' => $restoredbnx->id],
            '*',
            MUST_EXIST
        );
        $restoredfile = presentation_helper::get_file_by_id((int)$presentation->fileid);

        $this->assertSame('slides.pdf', $presentation->filename);
        $this->assertNotFalse($restoredfile);
        $this->assertSame('Presentation content', $restoredfile->get_content());
        $this->assertNotSame((int)$file->get_id(), (int)$presentation->fileid);
    }
}
