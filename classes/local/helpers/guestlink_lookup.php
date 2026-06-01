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

use mod_bigbluebuttonbn\instance as core_instance;

/**
 * Documented shim isolating direct reads of the mod_bigbluebuttonbn table
 * by guest-link UID.
 *
 * Moodle's Component Communication policy forbids one plugin from reaching
 * directly into another plugin's tables; access must go through the owning
 * component's API. mod_bigbluebuttonbn currently exposes
 * `\mod_bigbluebuttonbn\instance::get_from_instanceid()` and
 * `::get_from_cmid()` but no factory keyed on `guestlinkuid`. Until that
 * upstream API exists, every guest-link lookup BNX needs is funnelled
 * through this single shim so the violation is isolated, audited, and
 * trivially removable.
 *
 * TODO MDL-99999: replace both methods with
 *   \mod_bigbluebuttonbn\instance::get_from_guestlinkuid($uid)
 * once the upstream API ships. The MDL-99999 placeholder will be replaced
 * with the real tracker id once the request is filed (see
 * .copilot/BNX_CODE_REVIEW_202605_PLAN.md §10 item 2).
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class guestlink_lookup {
    /**
     * Resolve a guest-link UID to a mod_bigbluebuttonbn instance, or null.
     *
     * @param string $uid The guest-link UID from the request.
     * @return core_instance|null
     */
    public static function get_instance_from_uid(string $uid): ?core_instance {
        global $DB;

        // TODO MDL-99999: replace with mod_bigbluebuttonbn\instance::get_from_guestlinkuid($uid).
        $bbid = $DB->get_field('bigbluebuttonbn', 'id', ['guestlinkuid' => trim($uid)]);
        if (empty($bbid)) {
            return null;
        }

        return core_instance::get_from_instanceid($bbid);
    }

    /**
     * Read the guest-link UID stored on a mod_bigbluebuttonbn record.
     *
     * Returns an empty string when no UID is present.
     *
     * @param int $instanceid mod_bigbluebuttonbn instance id.
     * @return string
     */
    public static function get_uid_for_instance(int $instanceid): string {
        global $DB;

        // TODO MDL-99999: replace with an accessor on \mod_bigbluebuttonbn\instance
        // once the upstream API exposes guestlinkuid (currently the field is read
        // straight from the bigbluebuttonbn table).
        $uid = $DB->get_field('bigbluebuttonbn', 'guestlinkuid', ['id' => $instanceid]);

        return (string) ($uid ?: '');
    }
}
