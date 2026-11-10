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
 * Test definitions for reminder subscriptions output.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\output;

use mod_bigbluebuttonbn\instance;
use renderer_base;

/**
 * Tests for reminder subscriptions output.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(subscriptions::class)]
final class subscriptions_test extends \advanced_testcase {
    /**
     * Reminder subscriptions are listed only for visible activities whose BNX
     * reminder setting is enabled.
     *
     * @return void
     */
    public function test_export_lists_visible_reminder_enabled_activities(): void {
        global $DB;

        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $bbbgenerator = $generator->get_plugin_generator('mod_bigbluebuttonbn');
        $bnxgenerator = $generator->get_plugin_generator('bbbext_bnx');

        $enabled = $bbbgenerator->create_instance([
            'course' => $course->id,
            'name' => 'Enabled reminders',
        ]);
        $enabledinstance = instance::get_from_instanceid($enabled->id);
        $bnxgenerator->enable_reminder($enabledinstance->get_instance_id());

        $disabled = $bbbgenerator->create_instance([
            'course' => $course->id,
            'name' => 'Disabled reminders',
        ]);
        $disabledinstance = instance::get_from_instanceid($disabled->id);
        $bnxgenerator->disable_reminder($disabledinstance->get_instance_id());

        $hidden = $bbbgenerator->create_instance([
            'course' => $course->id,
            'name' => 'Hidden reminders',
        ]);
        $hiddeninstance = instance::get_from_instanceid($hidden->id);
        $bnxgenerator->enable_reminder($hiddeninstance->get_instance_id());
        $hiddencm = get_coursemodule_from_instance('bigbluebuttonbn', $hidden->id, $course->id, false, MUST_EXIST);
        $DB->set_field('course_modules', 'visible', 0, ['id' => $hiddencm->id]);
        rebuild_course_cache($course->id, true);

        get_fast_modinfo($course->id, $user->id, true);
        $this->setUser($user);
        $subscriptions = new subscriptions($user->id);
        $data = $subscriptions->export_for_template($this->createMock(renderer_base::class));

        $this->assertCount(1, $data->instances);
        $this->assertSame('Enabled reminders', $data->instances[0]->name);
    }
}
