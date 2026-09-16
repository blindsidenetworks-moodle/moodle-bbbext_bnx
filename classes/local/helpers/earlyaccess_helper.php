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

use bbbext_bnx\local\services\bnx_settings_service;
use mod_bigbluebuttonbn\instance;
use stdClass;

/**
 * Applies Early Access rules to BNX meeting data.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class earlyaccess_helper {
    /**
     * Adjust meeting data based on Early Access and room availability rules.
     *
     * @param instance $instance BigBlueButton activity instance.
     * @param stdClass $meetingdata Meeting data to adjust.
     * @return stdClass
     */
    public static function adjust_meeting_data(instance $instance, stdClass $meetingdata): stdClass {
        if (self::user_has_early_access($instance) && empty($meetingdata->statusrunning)) {
            $meetingdata->canjoin = true;
            $meetingdata->statusmessage = get_string('view_early_message', 'bbbext_bnx');
        }

        if (!$instance->is_moderator() && !$instance->is_currently_open() && !empty($meetingdata->statusrunning)) {
            $meetingdata->statusmessage = get_string('view_message_conference_not_started', 'mod_bigbluebuttonbn');
            $meetingdata->statusrunning = false;
        }

        return $meetingdata;
    }

    /**
     * Determine whether the current user can join before the opening time.
     *
     * @param instance $instance BigBlueButton activity instance.
     * @return bool
     */
    public static function user_has_early_access(instance $instance): bool {
        if (!has_capability('bbbext/bnx:earlyjoinaccess', $instance->get_context())) {
            return false;
        }

        if (!$instance->before_start_time()) {
            return false;
        }

        if (!mod_form_helper::is_feature_editable('earlyaccess')) {
            return (bool)mod_form_helper::get_feature_default('earlyaccess');
        }

        $service = bnx_settings_service::get_service();
        $setting = $service->get_setting_for_module($instance->get_instance_id(), 'bnx_earlyaccess_enable_access');

        return $setting !== null && (bool)$setting;
    }
}
