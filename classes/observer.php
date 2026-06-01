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

namespace bbbext_bnx;

use bbbext_bnx\event\state_changed;

/**
 * Event observer callbacks for BN Experience extension.
 *
 * @package    bbbext_bnx
 * @copyright  2026 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class observer {
    /**
     * React to bbbext_bnx enable/disable transitions and publish a public state event.
     *
     * BNX does not mutate sibling sub-plugin enablement. Sidecars that wish to mirror
     * BNX state subscribe to {@see \bbbext_bnx\event\state_changed} in their own
     * db/events.php and write only to their own enablement.
     *
     * @param \core\event\config_log_created $event
     * @return void
     */
    public static function config_log_created(\core\event\config_log_created $event): void {
        $other = $event->other ?? [];

        // Fast early-return: this observer fires on every config change site-wide.
        if (($other['name'] ?? '') !== 'disabled') {
            return;
        }
        if (($other['plugin'] ?? '') !== 'bbbext_bnx') {
            return;
        }

        $enabled = (int)($other['value'] ?? 0) !== 1;

        if ($enabled) {
            // Refresh BNX-owned lock settings from core on every enable.
            require_once(__DIR__ . '/../db/migration.php');
            bbbext_bnx_sync_core_locksettings_data();
        }

        // Publish the public state change event. Sidecars own their own reaction.
        state_changed::create([
            'context' => \context_system::instance(),
            'other'   => ['enabled' => $enabled],
        ])->trigger();
    }
}
