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

    /** BNX configuration key that identifies the service account it created. */
    public const USERID_CONFIG = 'presentation_service_userid';

    /** Authentication method for the non-interactive service account. */
    private const AUTH = 'webservice';

    /** Immutable email address of the BNX-owned service account. */
    private const EMAIL = 'bnx_preuploads_systemuser@invalid';

    /** Legacy email address used before the account was made non-interactive. */
    private const LEGACY_EMAIL = 'bnx_preuploads_systemuser@noemail.blindsidenetworks.com';

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
            'email' => self::EMAIL,
            'auth' => self::AUTH,
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'password' => hash_internal_user_password(bin2hex(random_bytes(16))),
        ];
        $user->id = \core\user::create_user($user, false, false);
        set_config(self::USERID_CONFIG, $user->id, 'bbbext_bnx');

        return $user;
    }

    /**
     * Migrate a marked legacy service account to non-interactive authentication.
     *
     * @return void
     */
    public static function migrate_owned_user_to_webservice(): void {
        global $DB;

        $userid = get_config('bbbext_bnx', self::USERID_CONFIG);
        if ($userid === false) {
            return;
        }

        $user = $DB->get_record('user', ['id' => (int) $userid], '*', IGNORE_MISSING);
        if (!$user || !self::matches_legacy_owned_user($user)) {
            return;
        }

        $user->auth = self::AUTH;
        $user->email = self::EMAIL;
        \core\user::update_user($user, false, false);
    }

    /**
     * Return the service account when it is owned by BNX.
     *
     * Accounts that merely share the historic username are never treated as
     * owned unless BNX recorded their identifier when it created them.
     *
     * @return \stdClass|null
     */
    public static function get_owned_user(): ?\stdClass {
        global $DB;

        $userid = get_config('bbbext_bnx', self::USERID_CONFIG);
        if ($userid === false) {
            return null;
        }

        $user = $DB->get_record('user', ['id' => (int) $userid], '*', IGNORE_MISSING);
        if (!$user || !self::matches_owned_user($user)) {
            return null;
        }

        return $user;
    }

    /**
     * Delete the BNX-owned service account.
     *
     * @param \stdClass $user Service account returned by get_owned_user().
     * @return void
     */
    public static function delete_owned_user(\stdClass $user): void {
        global $CFG;

        if (!self::matches_owned_user($user)) {
            return;
        }

        require_once($CFG->dirroot . '/user/lib.php');
        user_delete_user($user);
        unset_config(self::USERID_CONFIG, 'bbbext_bnx');
    }

    /**
     * Confirm that an account still has BNX's service-account identity.
     *
     * @param \stdClass $user User record.
     * @return bool
     */
    private static function matches_owned_user(\stdClass $user): bool {
        return $user->username === self::USERNAME
            && $user->firstname === 'BNX Preuploads'
            && $user->lastname === 'System User'
            && $user->email === self::EMAIL
            && $user->auth === self::AUTH
            && empty($user->deleted);
    }

    /**
     * Confirm that an account has the original BNX-owned service identity.
     *
     * @param \stdClass $user User record.
     * @return bool
     */
    private static function matches_legacy_owned_user(\stdClass $user): bool {
        return $user->username === self::USERNAME
            && $user->firstname === 'BNX Preuploads'
            && $user->lastname === 'System User'
            && $user->email === self::LEGACY_EMAIL
            && $user->auth === 'manual'
            && empty($user->deleted);
    }
}
