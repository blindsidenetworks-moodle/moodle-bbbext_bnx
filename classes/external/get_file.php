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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Serves a presentation file to BigBlueButton through a temporary token.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_file extends external_api {
    /**
     * Describe the external function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fileid' => new external_value(PARAM_INT, 'Stored presentation file identifier'),
        ]);
    }

    /**
     * Validate and send the requested file.
     *
     * @param int $fileid Stored presentation file identifier.
     * @return void
     */
    public static function execute(int $fileid): void {
        $params = self::validate_parameters(self::execute_parameters(), ['fileid' => $fileid]);
        self::validate_context(\context_system::instance());

        $token = optional_param('wstoken', '', PARAM_ALPHANUMEXT);
        $file = self::get_file_for_token((int)$params['fileid'], $token);
        send_stored_file($file, 0, 0, true);
    }

    /**
     * Validate token and file membership, then consume a token use.
     *
     * This separate method permits security tests without triggering send_stored_file().
     *
     * @param int $fileid Stored presentation file identifier.
     * @param string $token Temporary presentation token.
     * @return \stored_file
     */
    public static function get_file_for_token(int $fileid, string $token): \stored_file {
        $presentation = presentation_helper::get_presentation_by_fileid($fileid);
        if ($presentation === false) {
            throw new \moodle_exception('filenotfound', 'error');
        }

        if (!presentation_token_helper::consume_token($token, (int)$presentation->bnxid)) {
            throw new \moodle_exception('invalidtoken', 'webservice');
        }

        $file = presentation_helper::get_file_by_id($fileid);
        if ($file === false) {
            throw new \moodle_exception('filenotfound', 'error');
        }

        return $file;
    }

    /**
     * Describe the external function return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([]);
    }
}
