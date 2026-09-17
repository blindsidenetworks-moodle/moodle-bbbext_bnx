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
 * Provisions the service user that owns temporary presentation file tokens.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presentation_user_helper {
    /** Service user identifier retained from the pre-uploads sidecar. */
    public const USERNAME = 'bnx_preuploads_systemuser';

    /**
     * Return the presentation service user, creating it through Moodle's user API when needed.
     *
     * @return \stdClass
     */
    public static function get_or_create_user(): \stdClass {
        global $CFG;

        $user = \core_user::get_user_by_username(self::USERNAME);
        if ($user !== false) {
            return $user;
        }

        require_once($CFG->dirroot . '/user/lib.php');
        $user = (object) [
            'username' => self::USERNAME,
            'firstname' => 'BNX Preuploads',
            'lastname' => 'System User',
            'email' => 'bnx_preuploads_systemuser@noemail.blindsidenetworks.com',
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'password' => hash_internal_user_password(bin2hex(random_bytes(16))),
        ];
        $user->id = \core\user::create_user($user, false, false);

        return $user;
    }
}
