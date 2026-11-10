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
 * Test definitions for guest reminder tasks.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\task;

/**
 * Tests for guest reminder tasks.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(send_email_reminders::class)]
final class send_email_reminders_test extends \advanced_testcase {
    /**
     * An activity can be deleted after an adhoc task is queued.
     *
     * @return void
     */
    public function test_missing_instance_is_skipped(): void {
        $this->resetAfterTest(true);
        $this->assertTrue(\bbbext_bnx\reminders_utils::is_reminders_enabled());

        $task = new send_email_reminders();
        $task->set_custom_data([
            'emails' => ['guest@example.com'],
            'instanceid' => 999999,
            'reminderid' => 1,
            'subject' => 'Reminder',
            'htmlmessage' => 'Message',
            'emailfooter' => '',
        ]);

        $task->execute();

        $this->assertTrue(true);
    }
}
