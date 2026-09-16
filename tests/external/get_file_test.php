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

use bbbext_bnx\local\helpers\presentation_helper;
use bbbext_bnx\local\helpers\presentation_token_helper;

/**
 * Tests for {@see get_file}.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_file::class)]
final class get_file_test extends \advanced_testcase {
    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        require_once($CFG->dirroot . '/webservice/lib.php');
    }

    /**
     * Test a valid token is consumed and revoked after its final permitted use.
     *
     * @return void
     */
    public function test_valid_token_serves_file_and_is_revoked_after_final_use(): void {
        global $DB;

        [$bnxid, $file] = $this->create_presentation();
        $token = presentation_token_helper::create_token($bnxid, 2);
        $this->assertNotNull($token);

        $coretoken = (new \webservice())->get_user_ws_token($token);
        $this->assertSame((int)$file->get_id(), (int)get_file::get_file_for_token($file->get_id(), $token)->get_id());
        $limit = $DB->get_record(presentation_token_helper::TOKENS_TABLE, ['tokenid' => $coretoken->id], '*', MUST_EXIST);
        $this->assertSame(1, (int)$limit->remaininguses);
        $this->assertSame(0, (int)$limit->revoked);

        $this->assertSame((int)$file->get_id(), (int)get_file::get_file_for_token($file->get_id(), $token)->get_id());
        $limit = $DB->get_record(presentation_token_helper::TOKENS_TABLE, ['tokenid' => $coretoken->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$limit->remaininguses);
        $this->assertSame(1, (int)$limit->revoked);

        $this->expectException(\moodle_exception::class);
        get_file::get_file_for_token($file->get_id(), $token);
    }

    /**
     * Test a token cannot be used to access a presentation from another activity.
     *
     * @return void
     */
    public function test_token_cannot_access_presentation_from_another_activity(): void {
        [$bnxid, $file] = $this->create_presentation();
        [, $otherfile] = $this->create_presentation();
        $token = presentation_token_helper::create_token($bnxid, 1);
        $this->assertNotNull($token);

        $this->expectException(\moodle_exception::class);
        get_file::get_file_for_token($otherfile->get_id(), $token);
    }

    /**
     * Test expired and malformed token values are rejected.
     *
     * @return void
     */
    public function test_expired_and_malformed_tokens_are_rejected(): void {
        global $DB;

        [$bnxid, $file] = $this->create_presentation();
        $token = presentation_token_helper::create_token($bnxid, 1);
        $this->assertNotNull($token);

        $coretoken = (new \webservice())->get_user_ws_token($token);
        $DB->set_field(presentation_token_helper::TOKENS_TABLE, 'validuntil', time() - 1, ['tokenid' => $coretoken->id]);

        try {
            get_file::get_file_for_token($file->get_id(), $token);
            $this->fail('Expired tokens must not serve files.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('invalidtoken', $exception->errorcode);
        }

        $this->expectException(\moodle_exception::class);
        get_file::get_file_for_token($file->get_id(), 'notavalidtoken');
    }

    /**
     * Create one BNX presentation record and its stored file.
     *
     * @return array{int, \stored_file}
     */
    private function create_presentation(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $activity->id, $course->id, false, MUST_EXIST);
        $now = time();
        $bnxid = $DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $activity->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_module::instance($cm->id)->id,
            'component' => 'bbbext_bnx',
            'filearea' => presentation_helper::FILEAREA,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'presentation.pdf',
        ], 'Presentation content');
        $DB->insert_record(presentation_helper::PRESENTATIONS_TABLE, (object) [
            'bnxid' => $bnxid,
            'fileid' => $file->get_id(),
            'filename' => $file->get_filename(),
        ]);

        return [$bnxid, $file];
    }
}
