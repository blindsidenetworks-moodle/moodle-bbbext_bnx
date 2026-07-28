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

namespace bbbext_bnx\privacy;

use context_module;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests for bbbext_bnx.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @covers \bbbext_bnx\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Test contexts are returned for users who added guest reminder records.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $contextlist = provider::get_contexts_for_userid($env['users'][0]->id);

        $this->assertCount(1, $contextlist);
        $this->assertEquals(context_module::instance($env['instances'][0]->cmid)->id, $contextlist->current()->id);
    }

    /**
     * Test privacy export includes guest reminder data in module context.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $context = context_module::instance($env['instances'][0]->cmid);

        $this->export_context_data_for_user($env['users'][0]->id, $context, 'bbbext_bnx');
        $writer = writer::with_context($context);

        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([
            get_string('privacy:export:guestreminders', 'bbbext_bnx'),
        ]);
        $this->assertEquals('guest1@example.com', $data->records[0]->email);
    }

    /**
     * Test delete_data_for_user removes only the targeted user's rows.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $context = context_module::instance($env['instances'][0]->cmid);

        $contextlist = new approved_contextlist($env['users'][0], 'bbbext_bnx', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $remainingforuser1 = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][0]->id,
            'userfrom' => $env['users'][0]->id,
        ]);
        $remainingforuser2 = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][0]->id,
            'userfrom' => $env['users'][1]->id,
        ]);

        $this->assertEquals(0, $remainingforuser1);
        $this->assertEquals(1, $remainingforuser2);
    }

    /**
     * Test delete_data_for_all_users_in_context removes all guest reminder rows.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $context = context_module::instance($env['instances'][0]->cmid);

        provider::delete_data_for_all_users_in_context($context);

        $remainingincontext = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][0]->id,
        ]);
        $remaininginothercontext = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][1]->id,
        ]);

        $this->assertEquals(0, $remainingincontext);
        $this->assertEquals(1, $remaininginothercontext);
    }

    /**
     * Test users in context are listed from guest reminder rows.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        if (!class_exists('\\core_privacy\\local\\request\\userlist')) {
            return;
        }

        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $context = context_module::instance($env['instances'][0]->cmid);
        $userlist = new userlist($context, 'bbbext_bnx');

        provider::get_users_in_context($userlist);

        $this->assertCount(2, $userlist);
        $expected = [$env['users'][0]->id, $env['users'][1]->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test delete_data_for_users removes only approved users for the context.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        if (!class_exists('\\core_privacy\\local\\request\\approved_userlist')) {
            return;
        }

        $this->resetAfterTest();

        $env = $this->get_bnx_environment();
        $context = context_module::instance($env['instances'][0]->cmid);
        $approved = new approved_userlist($context, 'bbbext_bnx', [$env['users'][0]->id]);

        provider::delete_data_for_users($approved);

        $remainingforuser1 = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][0]->id,
            'userfrom' => $env['users'][0]->id,
        ]);
        $remainingforuser2 = $DB->count_records('bbbext_bnx_reminders_guests', [
            'bigbluebuttonbnid' => $env['instances'][0]->id,
            'userfrom' => $env['users'][1]->id,
        ]);

        $this->assertEquals(0, $remainingforuser1);
        $this->assertEquals(1, $remainingforuser2);
    }

    /**
     * Build a test environment with two users and two BBB activities.
     *
     * @return array
     */
    private function get_bnx_environment(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance1 = $generator->create_module('bigbluebuttonbn', ['course' => $course->id]);
        $instance2 = $generator->create_module('bigbluebuttonbn', ['course' => $course->id]);

        $users = [
            $generator->create_user(),
            $generator->create_user(),
        ];

        $bnxgenerator = $generator->get_plugin_generator('bbbext_bnx');
        $bnxgenerator->add_guest([
            'bigbluebuttonbnid' => $instance1->id,
            'email' => 'guest1@example.com',
            'userfrom' => $users[0]->id,
            'usermodified' => $users[0]->id,
        ]);
        $bnxgenerator->add_guest([
            'bigbluebuttonbnid' => $instance1->id,
            'email' => 'guest2@example.com',
            'userfrom' => $users[1]->id,
            'usermodified' => $users[1]->id,
        ]);
        $bnxgenerator->add_guest([
            'bigbluebuttonbnid' => $instance2->id,
            'email' => 'guest3@example.com',
            'userfrom' => $users[1]->id,
            'usermodified' => $users[1]->id,
        ]);

        return [
            'course' => $course,
            'instances' => [$instance1, $instance2],
            'users' => $users,
        ];
    }
}
