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
 * Guest access implementation for BNX.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */

use bbbext_bnx\form\guest_login;
use bbbext_bnx\local\helpers\guestlink_lookup;
use mod_bigbluebuttonbn\local\exceptions\server_not_available_exception;
use mod_bigbluebuttonbn\local\proxy\bigbluebutton_proxy;
use mod_bigbluebuttonbn\meeting;

// We should not have any require_login in this file as it is a guest entrypoint.
// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../../config.php');

global $PAGE, $OUTPUT, $DB, $SITE;

// Note here that we do not use require_login as $CFG->forcelogin would prevent guest users from accessing this page.
$PAGE->set_course($SITE);
$uid = required_param('uid', PARAM_ALPHANUMEXT);

// Guest-link lookups go through the documented shim that isolates the
// (currently unavoidable) direct read of the mod_bigbluebuttonbn table
// pending an upstream get_from_guestlinkuid() API.
$instance = guestlink_lookup::get_instance_from_uid($uid);
if ($instance === null) {
    throw new moodle_exception('guestaccess_activitynotfound', 'mod_bigbluebuttonbn');
}

if (!$instance->is_guest_allowed()) {
    throw new moodle_exception('guestaccess_feature_disabled', 'mod_bigbluebuttonbn');
}

$PAGE->set_url('/mod/bigbluebuttonbn/extension/bnx/guest.php', ['uid' => $uid]);
$title = $instance->get_course()->shortname . ': ' . format_string(
    $instance->get_meeting_name(),
    true,
    ['context' => $instance->get_context()]
);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

$reason = optional_param('reason', '', PARAM_TEXT);
// The 'errors' value is an internal status code echoed back by BBB on session end;
// it must be a short alphanumeric identifier, never free-form text or HTML.
$errors = optional_param('errors', '', PARAM_ALPHANUMEXT);

// BBB appends reason/errors when ending the session; in that case we only need to auto-close this tab.
if ($reason !== '' || $errors !== '') {
    echo $OUTPUT->header();
    if (!defined('BEHAT_SITE_RUNNING')) {
        $PAGE->requires->js_call_amd('mod_bigbluebuttonbn/rooms', 'setupWindowAutoClose');
    }
    echo html_writer::div(get_string('view_message_tab_close', 'mod_bigbluebuttonbn'));
    echo html_writer::div(html_writer::link(
        new moodle_url('/mod/bigbluebuttonbn/extension/bnx/guest.php', ['uid' => $uid]),
        get_string('guestaccess_meeting_link', 'mod_bigbluebuttonbn')
    ));
    echo $OUTPUT->footer();
    exit;
}

$form = new guest_login(null, ['uid' => $uid, 'instance' => $instance]);
if (defined('BEHAT_SITE_RUNNING')) {
    // PARAM_RAW_TRIMMED preserves printable password characters while trimming whitespace;
    // the value is compared with hash_equals() in guest_login::validation() and never echoed.
    $form->set_data(['password' => optional_param('password', '', PARAM_RAW_TRIMMED)]);
}

if ($data = $form->get_data()) {
    $username = $data->username;
    try {
        $meeting = new meeting($instance);
        if (!empty($meeting->get_meeting_info()->createtime)) {
            $url = $meeting->get_guest_join_url($username);
            redirect($url);
        } else {
            \core\notification::add(
                get_string('guestaccess_meeting_not_started', 'mod_bigbluebuttonbn'),
                \core\output\notification::NOTIFY_ERROR
            );
        }
    } catch (server_not_available_exception $e) {
        bigbluebutton_proxy::handle_server_not_available($instance);
    }
}

// Open the join flow in a script-opened child window so logout auto-close is permitted by browsers.
// Moved out of inline js_init_code into an AMD module (OL-4.1.4).
$PAGE->requires->js_call_amd('bbbext_bnx/guest_login', 'init', ['form.mform']);

echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->footer();
