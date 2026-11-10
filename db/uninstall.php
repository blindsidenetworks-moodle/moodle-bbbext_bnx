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

/**
 * Uninstall cleanup for BN Experience extension.
 *
 * @package    bbbext_bnx
 * @copyright  2026 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove BNX-owned external records before Moodle removes plugin tables.
 *
 * The core presentation setting is intentionally not restored: BNX no longer
 * changes it and historic changes cannot be attributed safely to BNX.
 *
 * @return bool
 */
function xmldb_bbbext_bnx_uninstall(): bool {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $user = \bbbext_bnx\local\helpers\presentation_user_helper::get_owned_user();
    if ($user !== null) {
        \bbbext_bnx\local\helpers\presentation_token_helper::delete_owned_tokens((int) $user->id);
        \bbbext_bnx\local\helpers\presentation_user_helper::delete_owned_user($user);
    }

    $preference = $DB->sql_like_escape('bbbext_bnx_reminder_') . '%';
    $DB->delete_records_select(
        'user_preferences',
        $DB->sql_like('name', ':preference'),
        ['preference' => $preference]
    );

    $transaction->allow_commit();

    return true;
}
