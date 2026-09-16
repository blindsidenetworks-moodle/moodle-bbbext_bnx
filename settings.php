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
 * Settings for bbbext_bnx extension.
 *
 * Configures BN Experience features and conditionally hides the setup instructions
 * when BigBlueButton is pre-configured via config.php.
 *
 * @package    bbbext_bnx
 * @copyright  2025 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Shamiso Jaravaza (shamiso [dt] jaravaza [at] blindsidenetworks [dt] com)
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/check/bnreminders_migration_pending.php');

// Hide the setup description if BigBlueButton is already configured via config.php.
// Check if BigBlueButton is pre-configured via $CFG->bigbluebuttonbn array.
global $CFG;
$isconfigured = !empty($CFG->bigbluebuttonbn['server_url'] ?? null)
    && !empty($CFG->bigbluebuttonbn['shared_secret'] ?? null);

// Locate the BigBlueButton General settings page and replace the setup description if configured.
$bbbgeneralpage = $ADMIN->locate('modsettingbigbluebuttonbn');
if (
    $isconfigured
    && ($bbbgeneralpage instanceof admin_settingpage)
    && isset($bbbgeneralpage->settings->bigbluebuttonbn_config_general)
) {
    // Replace the setup description with a message indicating credentials are configured in config.php.
    $bbbgeneralpage->settings->bigbluebuttonbn_config_general = new admin_setting_heading(
        'bigbluebuttonbn_config_general',
        '',
        new lang_string('config_general_description_credentials_preconfigured', 'bbbext_bnx')
    );
}

if ($ADMIN->fulltree) {
    global $OUTPUT;

    $bnxchecks = [
        new \bbbext_bnx\check\bnreminders_conflict(),
        new \bbbext_bnx\check\bnreminders_migration_pending(),
    ];
    foreach ($bnxchecks as $bnxcheck) {
        $checkresult = $bnxcheck->get_result();
        if ($checkresult->get_status() === \core\check\result::OK) {
            continue;
        }
        $noticebody = html_writer::tag('strong', $checkresult->get_summary());
        $details = $checkresult->get_details();
        if ($details !== '') {
            $noticebody .= html_writer::div($details);
        }
        $actionlink = $bnxcheck->get_action_link();
        if ($actionlink !== null) {
            $noticebody .= html_writer::div($OUTPUT->render($actionlink));
        }
        $notificationtype = $bnxcheck->get_ref() === 'bbbext_bnx_bnreminders_conflict'
            ? \core\output\notification::NOTIFY_ERROR
            : \core\output\notification::NOTIFY_WARNING;
        $settings->add(new admin_setting_description(
            'bbbext_bnx/notice_' . $bnxcheck->get_ref(),
            '',
            $OUTPUT->notification($noticebody, $notificationtype)
        ));
    }

    $featuresbysection = [
        'waitingroom' => [
            'approvalbeforejoin',
        ],
        'locksettings' => [
            'cam',
            'mic',
            'publicchat',
            'privatechat',
            'notes',
            'userlist',
            'hideviewerscursor',
        ],
        'reminders' => [
            'reminder',
        ],
        'earlyaccess' => [
            'earlyaccess',
        ],
    ];

    $featuredefaults = [
        'earlyaccess' => '0',
    ];

    // Deferred translation: only resolve these strings if the admin actually loads this page (OL-3.1.10).
    $options = [
        '1' => new lang_string('options_enabled', 'bbbext_bnx'),
        '0' => new lang_string('options_disabled', 'bbbext_bnx'),
    ];

    foreach ($featuresbysection as $section => $features) {
        $settings->add(new admin_setting_heading(
            "bbbext_bnx/section_{$section}",
            new lang_string("section_{$section}_heading", 'bbbext_bnx'),
            new lang_string("section_{$section}_desc", 'bbbext_bnx')
        ));

        foreach ($features as $feature) {
            $settings->add(new admin_setting_configselect(
                "bbbext_bnx/{$feature}_default",
                new lang_string("{$feature}_default", 'bbbext_bnx'),
                new lang_string("{$feature}_default_desc", 'bbbext_bnx'),
                $featuredefaults[$feature] ?? '1',
                $options
            ));

            $settings->add(new admin_setting_configcheckbox(
                "bbbext_bnx/{$feature}_editable",
                new lang_string("{$feature}_editable", 'bbbext_bnx'),
                new lang_string("{$feature}_editable_desc", 'bbbext_bnx'),
                1
            ));
        }
    }

    $settings->add(new admin_setting_heading(
        'bbbext_bnx/section_preuploads',
        new lang_string('section_preuploads_heading', 'bbbext_bnx'),
        new lang_string('section_preuploads_desc', 'bbbext_bnx')
    ));
    $settings->add(new admin_setting_configtext(
        'bbbext_bnx/maxfiles',
        new lang_string('maxfiles', 'bbbext_bnx'),
        new lang_string('maxfiles_desc', 'bbbext_bnx'),
        10,
        PARAM_INT
    ));

    // Email customisation settings for reminders.
    $settings->add(new admin_setting_heading(
        'bbbext_bnx/emailcontent',
        new lang_string('emailcontent', 'bbbext_bnx'),
        new lang_string('emailcontent:desc', 'bbbext_bnx')
    ));

    $settings->add(new admin_setting_configtext(
        'bbbext_bnx/emailsubject',
        new lang_string('emailsubject', 'bbbext_bnx'),
        new lang_string('emailsubject:desc', 'bbbext_bnx'),
        new lang_string('emailsubject:default', 'bbbext_bnx')
    ));

    $settings->add(new admin_setting_confightmleditor(
        'bbbext_bnx/emailtemplate',
        new lang_string('emailtemplate', 'bbbext_bnx'),
        new lang_string('emailtemplate:desc', 'bbbext_bnx'),
        new lang_string('emailtemplate:default', 'bbbext_bnx')
    ));

    $settings->add(new admin_setting_confightmleditor(
        'bbbext_bnx/emailfooter',
        new lang_string('emailfooter', 'bbbext_bnx'),
        new lang_string('emailfooter:desc', 'bbbext_bnx'),
        ''
    ));
}
