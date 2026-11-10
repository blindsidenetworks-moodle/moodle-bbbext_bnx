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
 * Group selection helpers for BNX join flows.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\local\helpers;

use context_module;

/**
 * Group selection helpers for BNX join flows.
 *
 * @package   bbbext_bnx
 */
class group_helper {
    /**
     * Get the group that should be used when joining an activity.
     *
     * @param \stdClass|\cm_info $cm
     * @param context_module $context
     * @return int|null
     */
    public static function get_groupid_for_join($cm, context_module $context): ?int {
        $groupid = groups_get_activity_group($cm, true);
        if ($groupid === false) {
            return null;
        }

        $groupid = (int) $groupid;
        $accessallgroups = has_capability('moodle/site:accessallgroups', $context);
        $groups = $groupid === 0 && $accessallgroups
            ? groups_get_all_groups($cm->course, 0, $cm->groupingid)
            : [];

        return self::resolve_groupid_for_join($groupid, $accessallgroups, $groups);
    }

    /**
     * Resolve the join group from Moodle's active-group value.
     *
     * An active group of zero means that an access-all-groups user has selected
     * all groups. BNX uses the first real group for that user so they join the
     * same guest lobby as group members.
     *
     * @param int $groupid Active group identifier; zero means all groups.
     * @param bool $accessallgroups Whether the user can access all groups.
     * @param array $groups Allowed groups.
     * @return int|null
     */
    public static function resolve_groupid_for_join(int $groupid, bool $accessallgroups, array $groups): ?int {
        if ($groupid === 0 && $accessallgroups) {
            foreach ($groups as $group) {
                if ($group->id > 0) {
                    return (int) $group->id;
                }
            }
        }

        return $groupid ?: null;
    }
}
