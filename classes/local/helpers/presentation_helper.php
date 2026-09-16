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

namespace bbbext_bnx\local\helpers;

/**
 * Looks up BNX presentation records and their stored files.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presentation_helper {
    /** Table linking stored files to BNX instances. */
    public const PRESENTATIONS_TABLE = 'bbbext_bnx_presentations';

    /** Table storing BNX base records. */
    private const BNX_TABLE = 'bbbext_bnx';

    /** Presentation stored-file area. */
    public const FILEAREA = 'presentation';

    /**
     * Find the BNX presentation record that owns a stored file.
     *
     * @param int $fileid Stored file identifier.
     * @return \stdClass|false
     */
    public static function get_presentation_by_fileid(int $fileid): \stdClass|false {
        global $DB;

        $sql = "SELECT p.id, p.bnxid, p.fileid, p.filename, b.bigbluebuttonbnid
                  FROM {bbbext_bnx_presentations} p
                  JOIN {bbbext_bnx} b ON b.id = p.bnxid
                 WHERE p.fileid = :fileid";

        return $DB->get_record_sql($sql, ['fileid' => $fileid], IGNORE_MISSING);
    }

    /**
     * Check whether a linked presentation belongs to the supplied activity.
     *
     * @param int $fileid Stored file identifier.
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @return bool
     */
    public static function belongs_to_module(int $fileid, int $bigbluebuttonbnid): bool {
        $presentation = self::get_presentation_by_fileid($fileid);

        return $presentation !== false && (int)$presentation->bigbluebuttonbnid === $bigbluebuttonbnid;
    }

    /**
     * Get a linked BNX presentation stored file, if it remains in the expected file area.
     *
     * @param int $fileid Stored file identifier.
     * @return \stored_file|false
     */
    public static function get_file_by_id(int $fileid): \stored_file|false {
        $presentation = self::get_presentation_by_fileid($fileid);
        if ($presentation === false) {
            return false;
        }

        $file = get_file_storage()->get_file_by_id($fileid);
        if (
            $file === false
            || $file->is_directory()
            || $file->get_component() !== 'bbbext_bnx'
            || $file->get_filearea() !== self::FILEAREA
        ) {
            return false;
        }

        return $file;
    }

    /**
     * Save presentation draft files and update their BNX links.
     *
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @param int $draftitemid Filemanager draft item identifier.
     * @param int $contextid Module context identifier.
     * @return void
     */
    public static function save_files(int $bigbluebuttonbnid, int $draftitemid, int $contextid): void {
        global $DB;

        $bnxid = self::get_bnx_id($bigbluebuttonbnid);
        if ($bnxid === null) {
            return;
        }

        file_save_draft_area_files($draftitemid, $contextid, 'bbbext_bnx', self::FILEAREA, 0, ['subdirs' => 0]);
        $files = get_file_storage()->get_area_files(
            $contextid,
            'bbbext_bnx',
            self::FILEAREA,
            0,
            'itemid, filepath, filename',
            false
        );

        $DB->delete_records(self::PRESENTATIONS_TABLE, ['bnxid' => $bnxid]);
        foreach ($files as $file) {
            $DB->insert_record(self::PRESENTATIONS_TABLE, (object) [
                'bnxid' => $bnxid,
                'fileid' => $file->get_id(),
                'filename' => $file->get_filename(),
            ]);
        }
    }

    /**
     * Get linked presentation files for an activity.
     *
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @return \stored_file[]
     */
    public static function get_files(int $bigbluebuttonbnid): array {
        $cm = get_coursemodule_from_instance('bigbluebuttonbn', $bigbluebuttonbnid);
        if ($cm === false) {
            return [];
        }

        return get_file_storage()->get_area_files(
            \context_module::instance($cm->id)->id,
            'bbbext_bnx',
            self::FILEAREA,
            0,
            'itemid, filepath, filename',
            false
        );
    }

    /**
     * Get presentations formatted for BNX's meeting information response.
     *
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @return array<int, array<string, string>>
     */
    public static function get_presentations(int $bigbluebuttonbnid): array {
        $presentations = [];
        foreach (self::get_files($bigbluebuttonbnid) as $file) {
            $presentations[] = [
                'icondesc' => get_mimetype_description($file),
                'iconname' => file_file_icon($file),
                'name' => $file->get_filename(),
                'url' => \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false),
            ];
        }

        return $presentations;
    }

    /**
     * Get presentations with temporary URLs for the BigBlueButton create request.
     *
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @return array<int, array<string, string>>
     */
    public static function get_presentations_for_ws(int $bigbluebuttonbnid): array {
        global $CFG;

        $files = self::get_files($bigbluebuttonbnid);
        $bnxid = self::get_bnx_id($bigbluebuttonbnid);
        if (empty($files) || $bnxid === null) {
            return [];
        }

        $token = presentation_token_helper::create_token($bnxid, count($files) * 2);
        if ($token === null) {
            return [];
        }

        $presentations = [];
        foreach ($files as $file) {
            $url = rtrim($CFG->wwwroot, '/') . '/webservice/rest/server.php?' . http_build_query([
                'wsfunction' => presentation_token_helper::SERVICE_NAME,
                'moodlewsrestformat' => 'json',
                'fileid' => $file->get_id(),
                'wstoken' => $token,
            ]);
            $presentations[] = [
                'icondesc' => get_mimetype_description($file),
                'iconname' => file_file_icon($file),
                'name' => $file->get_filename(),
                'url' => $url,
            ];
        }

        return $presentations;
    }

    /**
     * Delete presentation records, token limits, and stored files for BNX.
     *
     * @param int $bnxid BNX record identifier.
     * @return void
     */
    public static function delete_presentations(int $bnxid): void {
        global $DB;

        $bnx = $DB->get_record(self::BNX_TABLE, ['id' => $bnxid], 'bigbluebuttonbnid', IGNORE_MISSING);
        if ($bnx !== false) {
            $cm = get_coursemodule_from_instance('bigbluebuttonbn', $bnx->bigbluebuttonbnid);
            if ($cm !== false) {
                get_file_storage()->delete_area_files(
                    \context_module::instance($cm->id)->id,
                    'bbbext_bnx',
                    self::FILEAREA,
                    0
                );
            }
        }

        $DB->delete_records(presentation_token_helper::TOKENS_TABLE, ['bnxid' => $bnxid]);
        $DB->delete_records(self::PRESENTATIONS_TABLE, ['bnxid' => $bnxid]);
    }

    /**
     * Get the BNX record identifier for an activity.
     *
     * @param int $bigbluebuttonbnid BigBlueButton activity identifier.
     * @return int|null
     */
    public static function get_bnx_id(int $bigbluebuttonbnid): ?int {
        global $DB;

        $record = $DB->get_record(self::BNX_TABLE, ['bigbluebuttonbnid' => $bigbluebuttonbnid], 'id', IGNORE_MISSING);
        return $record === false ? null : (int)$record->id;
    }
}
