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
 * Privacy Subsystem for bbbext_bnx.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for bbbext_bnx implementing metadata and user preference providers.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /**
     * Provides metadata about the personal data stored.
     *
     * @param collection $collection The metadata collection to update.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'bbbext_bnx_reminder',
            'privacy:metadata:preference:bbbext_bnx_reminder'
        );
        $collection->add_database_table(
            'bbbext_bnx_reminders_guests',
            [
                'email' => 'privacy:metadata:bbbext_bnx_reminders_guests:email',
                'userfrom' => 'privacy:metadata:bbbext_bnx_reminders_guests:userfrom',
                'isenabled' => 'privacy:metadata:bbbext_bnx_reminders_guests:isenabled',
            ],
            'privacy:metadata:bbbext_bnx_reminders_guests'
        );
        return $collection;
    }

    /**
     * Export the user preferences for reminders.
     *
     * @param int $userid The user ID to export data for.
     * @return void
     */
    public static function export_user_preferences(int $userid): void {
        global $DB;

        $preferences = $DB->get_records_sql(
            "SELECT * FROM {user_preferences} WHERE userid = ? AND name LIKE ?",
            [$userid, 'bbbext_bnx_reminder_%']
        );

        foreach ($preferences as $pref) {
            // Extract the activity ID from the preference name.
            $activityid = str_replace('bbbext_bnx_reminder_', '', $pref->name);
            $preference = (int) $pref->value
                ? 'privacy:reminderpreferenceyes'
                : 'privacy:reminderpreferenceno';
            $description = get_string($preference, 'bbbext_bnx', ['activityid' => $activityid]);

            writer::export_user_preference(
                'bbbext_bnx',
                $pref->name,
                $pref->value,
                $description
            );
        }
    }

    /**
     * Get contexts containing data for the provided user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {bbbext_bnx_reminders_guests} guests ON guests.bigbluebuttonbnid = cm.instance
                 WHERE ctx.contextlevel = :contextlevel
                   AND guests.userfrom = :userid";

        $params = [
            'modname' => 'bigbluebuttonbn',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export user data for approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('bigbluebuttonbn', $context->instanceid, 0, false, MUST_EXIST);
            $records = $DB->get_records('bbbext_bnx_reminders_guests', [
                'bigbluebuttonbnid' => $cm->instance,
                'userfrom' => $userid,
            ]);

            if (empty($records)) {
                continue;
            }

            $export = [];
            foreach ($records as $record) {
                $export[] = (object) [
                    'email' => $record->email,
                    'isenabled' => $record->isenabled,
                    'timecreated' => transform::datetime((int) $record->timecreated),
                    'timemodified' => transform::datetime((int) $record->timemodified),
                ];
            }

            $exportpath = [get_string('privacy:export:guestreminders', 'bbbext_bnx')];
            writer::with_context($context)->export_data($exportpath, (object) ['records' => $export]);
        }
    }

    /**
     * Delete data for all users in the provided context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('bigbluebuttonbn', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $DB->delete_records('bbbext_bnx_reminders_guests', ['bigbluebuttonbnid' => $cm->instance]);
    }

    /**
     * Delete data for a specific user in approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('bigbluebuttonbn', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $DB->delete_records('bbbext_bnx_reminders_guests', [
                'bigbluebuttonbnid' => $cm->instance,
                'userfrom' => $userid,
            ]);
        }
    }

    /**
     * Get users with data in the specified context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT DISTINCT guests.userfrom AS userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {bbbext_bnx_reminders_guests} guests ON guests.bigbluebuttonbnid = cm.instance
                 WHERE cm.id = :cmid
                   AND guests.userfrom IS NOT NULL";

        $params = [
            'modname' => 'bigbluebuttonbn',
            'cmid' => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete data for users in an approved userlist.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('bigbluebuttonbn', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge([
            'bigbluebuttonbnid' => $cm->instance,
        ], $params);

        $DB->delete_records_select(
            'bbbext_bnx_reminders_guests',
            "bigbluebuttonbnid = :bigbluebuttonbnid AND userfrom {$insql}",
            $params
        );
    }
}
