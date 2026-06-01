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

use mod_bigbluebuttonbn\instance;
use stdClass;

/**
 * Helper for checking sidecar plugin availability.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class sidecar_helper {
    /** @var string Class pattern for optional room alert providers implemented by sidecars. */
    private const ROOM_ALERT_PROVIDER_CLASS = '\\bbbext_{pluginname}\\local\\helpers\\alert_provider';

    /** @var string Class pattern for optional presentation providers implemented by sidecars. */
    private const PRESENTATION_PROVIDER_CLASS = '\\bbbext_{pluginname}\\local\\helpers\\presentation_helper';

    /**
     * @var array<string,array>|null Per-request memoization of sorted sidecar plugins keyed by
     *      the required class pattern (or '__none__' when no filter is applied). Populated lazily
     *      by {@see get_sorted_sidecar_plugins()} so repeated calls in a single request avoid the
     *      per-plugin get_config() loop (OL-3.1.9).
     */
    private static ?array $sortedcache = null;

    /**
     * @var array<string,int>|null Per-request memoization of bbbext_* sortorder values.
     *      Populated lazily on first use and shared across every sort invocation within a request.
     */
    private static ?array $sortordercache = null;

    /**
     * Get the list of enabled bbbext plugins.
     *
     * @return array Associative array of enabled plugin names to paths.
     */
    private static function get_enabled_plugins(): array {
        return \core_plugin_manager::instance()->get_enabled_plugins('bbbext');
    }

    /**
     * Reset the per-request caches. Intended for PHPUnit and explicit invalidation only.
     */
    public static function reset_caches(): void {
        self::$sortedcache = null;
        self::$sortordercache = null;
    }

    /**
     * Check if a sidecar plugin is installed, enabled, and optionally has a required class.
     *
     * @param string $sidecarname The name of the sidecar plugin (e.g., 'bnx_preuploads', 'bnx_insights').
     * @param string|null $requiredclass Optional fully qualified class name that must exist.
     * @return bool True if the sidecar is available for use.
     */
    public static function is_available(string $sidecarname, ?string $requiredclass = null): bool {
        $enabledplugins = self::get_enabled_plugins();
        if (!isset($enabledplugins[$sidecarname])) {
            return false;
        }
        // Optionally check if a specific class exists (plugin properly installed).
        if ($requiredclass !== null && !class_exists($requiredclass)) {
            return false;
        }
        return true;
    }

    /**
     * Apply room adjustments from first available sidecar plugin.
     *
     * @param instance $instance
     * @param stdClass $roomdata
     * @return stdClass
     */
    public static function apply_room_adjustments(instance $instance, stdClass $roomdata): stdClass {
        $requiredclass = "\\bbbext_{pluginname}\\local\\helpers\\meeting_helper";
        $sortedplugins = self::get_sorted_sidecar_plugins($requiredclass);

        // Override room data with first available sidecar plugin that implements class.
        if (!empty($sortedplugins)) {
            $pluginname = reset($sortedplugins);
            $helperclass = "\\bbbext_{$pluginname}\\local\\helpers\\meeting_helper";
            return $helperclass::adjust_meeting_data($instance, $roomdata);
        }

        return $roomdata;
    }

    /**
     * Get ordered sidecar plugin names based on extension sort order.
     *
     * @return array
     */
    public static function get_ordered_sidecar_plugins(): array {
        return array_values(self::get_sorted_sidecar_plugins());
    }

    /**
     * Render room alerts from enabled sidecar providers.
     *
     * Sidecars can implement:
     *   \bbbext_{pluginname}\local\helpers\alert_provider::render_room_alerts(string $lang): string
     *
     * @param string $lang Current user language code.
     * @return string Rendered HTML from all providers (ordered by sort order).
     */
    public static function render_room_alerts(string $lang): string {
        $providers = self::get_room_alert_provider_classes();
        if (empty($providers)) {
            return '';
        }

        $output = '';
        foreach ($providers as $providerclass) {
            $rendered = $providerclass::render_room_alerts($lang);
            if (!empty($rendered)) {
                $output .= $rendered;
            }
        }

        return $output;
    }

    /**
     * Resolve the first enabled sidecar implementing the named presentation-provider method.
     *
     * Sidecars may implement:
     *   \bbbext_{pluginname}\local\helpers\presentation_helper::get_presentations(int $bigbluebuttonbnid): array
     *   \bbbext_{pluginname}\local\helpers\presentation_helper::get_presentations_for_ws(int $bigbluebuttonbnid): array
     *
     * The first enabled bnx_ sidecar (by sortorder) whose presentation_helper class exists and
     * implements the requested method is used. If none is available, an empty array is returned so
     * BNX continues to work with no presentation sidecar installed.
     *
     * @param int $bigbluebuttonbnid Activity instance id.
     * @param string $method Either 'get_presentations' or 'get_presentations_for_ws'.
     * @return array
     */
    public static function get_presentations_from_provider(int $bigbluebuttonbnid, string $method): array {
        foreach (self::get_ordered_sidecar_plugins() as $pluginname) {
            $classname = str_replace('{pluginname}', $pluginname, self::PRESENTATION_PROVIDER_CLASS);
            if (!class_exists($classname) || !method_exists($classname, $method)) {
                continue;
            }
            return $classname::$method($bigbluebuttonbnid);
        }
        return [];
    }

    /**
     * Resolve enabled sidecar classes implementing the room alert provider contract.
     *
     * @return array Ordered list of fully-qualified provider class names.
     */
    public static function get_room_alert_provider_classes(): array {
        $providers = [];
        foreach (self::get_ordered_sidecar_plugins() as $pluginname) {
            $classname = str_replace('{pluginname}', $pluginname, self::ROOM_ALERT_PROVIDER_CLASS);
            if (!class_exists($classname) || !method_exists($classname, 'render_room_alerts')) {
                continue;
            }
            $providers[] = $classname;
        }

        return $providers;
    }

    /**
     * Get sorted sidecar plugins by sortorder, optionally filtered by class.
     *
     * Results are memoized per-request and keyed by the required class pattern so the
     * per-plugin `get_config()` lookup runs at most once per pattern per request
     * (OL-3.1.9 N+1 remediation).
     *
     * @param string|null $requiredclass Class pattern with {pluginname} placeholder.
     * @return array
     */
    private static function get_sorted_sidecar_plugins(?string $requiredclass = null): array {
        $cachekey = $requiredclass ?? '__none__';
        if (self::$sortedcache !== null && array_key_exists($cachekey, self::$sortedcache)) {
            return self::$sortedcache[$cachekey];
        }

        $enabledplugins = self::get_enabled_plugins();
        $sortorders = self::get_sortorder_map(array_keys($enabledplugins));
        $result = [];
        foreach (array_keys($enabledplugins) as $name) {
            // Only sort bnx sidecar plugins.
            if (!str_starts_with($name, 'bnx_')) {
                continue;
            }

            // If a required class is specified, check if it exists.
            if ($requiredclass !== null) {
                $classname = str_replace('{pluginname}', $name, $requiredclass);
                if (!class_exists($classname) || !method_exists($classname, 'adjust_meeting_data')) {
                    continue;
                }
            }

            $idx = $sortorders[$name] ?? 0;
            while (array_key_exists($idx, $result)) {
                $idx += 1;
            }
            $result[$idx] = $name;
        }
        ksort($result);

        if (self::$sortedcache === null) {
            self::$sortedcache = [];
        }
        self::$sortedcache[$cachekey] = $result;
        return $result;
    }

    /**
     * Resolve the `sortorder` config for every enabled bbbext plugin in one pass.
     *
     * The result is memoized for the lifetime of the request so subsequent calls re-use the
     * already-resolved values instead of re-issuing one `get_config()` per plugin per call
     * site (OL-3.1.9).
     *
     * @param string[] $pluginnames Plugin short names (without the `bbbext_` prefix).
     * @return array<string,int> Map of plugin short name => integer sortorder (default 0).
     */
    private static function get_sortorder_map(array $pluginnames): array {
        if (self::$sortordercache !== null) {
            return self::$sortordercache;
        }

        $map = [];
        foreach ($pluginnames as $name) {
            $idx = get_config('bbbext_' . $name, 'sortorder');
            $map[$name] = $idx !== false && $idx !== null ? (int) $idx : 0;
        }
        self::$sortordercache = $map;
        return $map;
    }
}
