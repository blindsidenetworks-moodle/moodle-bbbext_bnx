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

namespace bbbext_bnx\check;

use core\check\check;
use core\check\result;

/**
 * Surface the conflict with the legacy BN Reminders sidecar.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bnreminders_conflict extends check {
    /**
     * Get the unique identifier for this check.
     *
     * @return string
     */
    public function get_ref(): string {
        return 'bbbext_bnx_bnreminders_conflict';
    }

    /**
     * Get the display name for this check.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_bnreminders_conflict', 'bbbext_bnx');
    }

    /**
     * Get the action link for this check.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/plugins.php'),
            get_string('check_bnreminders_conflict_action', 'bbbext_bnx')
        );
    }

    /**
     * Get the check result.
     *
     * @return result
     */
    public function get_result(): result {
        require_once(__DIR__ . '/../../db/migration.php');

        if (bbbext_bnx_has_bnreminders_conflict()) {
            return new result(
                result::WARNING,
                get_string('check_bnreminders_conflict_warning', 'bbbext_bnx'),
                get_string('check_bnreminders_conflict_warning_details', 'bbbext_bnx')
            );
        }

        return new result(
            result::OK,
            get_string('check_bnreminders_conflict_ok', 'bbbext_bnx'),
            get_string('check_bnreminders_conflict_ok_details', 'bbbext_bnx')
        );
    }
}
