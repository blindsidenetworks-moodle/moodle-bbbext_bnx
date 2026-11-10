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
 * Test definitions for subscription utilities.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx;

/**
 * Tests for subscription utilities.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(subscription_utils::class)]
final class subscription_utils_test extends \advanced_testcase {
    /**
     * Signed email-link tokens must bind the activity and recipient identity.
     *
     * @return void
     */
    public function test_unsubscribe_token_binds_recipient_identity(): void {
        $cmid = 123;
        $email = 'guest@example.com';
        $token = subscription_utils::get_unsubscribe_token($cmid, $email);

        $this->assertTrue(subscription_utils::verify_unsubscribe_token($token, $cmid, $email));
        $this->assertFalse(subscription_utils::verify_unsubscribe_token($token, $cmid, 'other@example.com'));
        $this->assertFalse(subscription_utils::verify_unsubscribe_token($token, $cmid + 1, $email));
    }
}
