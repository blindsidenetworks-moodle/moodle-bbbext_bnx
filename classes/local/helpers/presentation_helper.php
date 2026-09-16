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
}
