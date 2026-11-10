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
 * Test definitions for BNX group selection helpers.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\local\helpers;

/**
 * Tests for BNX group selection helpers.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(group_helper::class)]
final class group_helper_test extends \advanced_testcase {
    /**
     * Access-all-groups users with group zero join the first real group.
     *
     * @return void
     */
    public function test_group_zero_uses_first_real_group_for_access_all_groups_user(): void {
        $groups = [
            (object) ['id' => 4],
            (object) ['id' => 7],
        ];

        $this->assertSame(4, group_helper::resolve_groupid_for_join(0, true, $groups));
    }

    /**
     * Regular group selections are retained and group zero is not changed for
     * users who cannot access all groups.
     *
     * @return void
     */
    public function test_regular_group_selection_is_retained(): void {
        $groups = [(object) ['id' => 4]];

        $this->assertSame(4, group_helper::resolve_groupid_for_join(4, false, $groups));
        $this->assertNull(group_helper::resolve_groupid_for_join(0, false, $groups));
    }
}
