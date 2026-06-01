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
 * Provider for collecting action URL parameters based on enabled features.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Shamiso Jaravaza (shamiso [dt] jaravaza [at] blindsidenetworks [dt] com)
 */

namespace bbbext_bnx\local\bigbluebutton;

use bbbext_bnx\bigbluebuttonbn\mod_instance_helper;
use bbbext_bnx\local\services\bnx_settings_service;

/**
 * Collects action URL parameters based on enabled features.
 *
 * This class determines which parameters should be included in the action URL
 * based on which features are enabled for a given module instance.
 *
 * @package   bbbext_bnx
 */
class action_url_parameters {
    /**
     * Collect all parameters that should be added to the action URL.
     *
     * Examines enabled features for the given module instance and returns
     * parameters that should be included in the URL.
     *
     * @param string $action the action being performed
     * @param int $instanceid the BBB instance ID
     * @param array $data action payload data
     * @return array<string, mixed> parameters keyed by name
     */
    public static function get_parameters(string $action, int $instanceid, array $data = []): array {
        $parameters = [];

        if ($action === 'create') {
            $parameters = array_merge($parameters, self::get_lock_settings_parameters($instanceid));
        }

        if (self::is_approval_before_join_enabled($instanceid)) {
            if ($action === 'create') {
                $parameters['guestPolicy'] = 'ASK_MODERATOR';
            }
            if ($action === 'join') {
                if (array_key_exists('guest', $data)) {
                    $parameters['guest'] = self::is_guest_join_value($data['guest']) ? 'true' : null;
                }
            }
        }

        return $parameters;
    }

    /**
     * Collect lock settings parameters for the BBB create call.
     *
     * @param int $instanceid the BigBlueButton module instance ID
     * @return array<string, string>
     */
    private static function get_lock_settings_parameters(int $instanceid): array {
        $parameters = [];

        foreach (mod_instance_helper::LOCK_FEATURE_DEFINITIONS as $feature => $definition) {
            $parameters[$definition['parameter']] = self::format_lock_setting_value(
                self::get_lock_feature_value($instanceid, $feature, $definition),
                (bool)($definition['invert'] ?? true)
            );
        }

        $parameters['lockSettingsLockOnJoin'] = 'true';

        return $parameters;
    }

    /**
     * Resolve the configured value for a lock feature.
     *
     * @param int $instanceid the BigBlueButton module instance ID
     * @param string $feature the feature config key
     * @param array $definition the feature metadata array
     * @return int the resolved feature value
     */
    private static function get_lock_feature_value(int $instanceid, string $feature, array $definition): int {
        if (get_config('bbbext_bnx', $feature . '_editable')) {
            $service = bnx_settings_service::get_service();
            $value = $service->get_setting_for_module($instanceid, $definition['setting']);
            if ($value !== null) {
                return (int)!empty($value);
            }
        }

        $default = get_config('bbbext_bnx', $feature . '_default');
        if ($default === false || $default === null) {
            $default = $definition['default'] ?? 0;
        }

        return (int)!empty($default);
    }

    /**
     * Convert a stored feature value into the BBB API string representation.
     *
     * @param int $value stored feature value
     * @param bool $invert whether the stored value is the inverse of the BBB lock parameter
     * @return string
     */
    private static function format_lock_setting_value(int $value, bool $invert): string {
        if ($invert) {
            return $value ? 'false' : 'true';
        }

        return $value ? 'true' : 'false';
    }

    /**
     * Check if moderator approval before join is enabled for the instance.
     *
     * @param int $instanceid the BigBlueButton module instance ID
     * @return bool true if approval before join is enabled
     */
    private static function is_approval_before_join_enabled(int $instanceid): bool {
        if (get_config('bbbext_bnx', 'approvalbeforejoin_editable')) {
            $service = bnx_settings_service::get_service();
            $value = $service->get_setting_for_module($instanceid, 'approvalbeforejoin');
            return (bool) $value;
        }
        $default = get_config('bbbext_bnx', 'approvalbeforejoin_default');
        return (bool) $default;
    }

    /**
     * Determine whether the provided guest value represents a guest join.
     *
     * @param mixed $guest action payload guest value
     * @return bool
     */
    private static function is_guest_join_value($guest): bool {
        if (is_string($guest)) {
            return strtolower($guest) === 'true' || $guest === '1';
        }

        return $guest === true || $guest === 1;
    }
}
