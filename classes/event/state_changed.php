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

namespace bbbext_bnx\event;

use core\event\base;

/**
 * Public BNX state change event.
 *
 * Fired whenever the bbbext_bnx plugin transitions between enabled and
 * disabled. Sidecar plugins that want to mirror BNX state should subscribe
 * to this event in their own db/events.php and act only on their own
 * enablement; BNX itself never mutates sibling sub-plugin state.
 *
 * Payload:
 *   other['enabled'] => bool  True when BNX has just been enabled, false when disabled.
 *
 * @package    bbbext_bnx
 * @copyright  2026 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class state_changed extends base {
    /**
     * Initialise event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventstate_changed', 'bbbext_bnx');
    }

    /**
     * Return the localised event description.
     *
     * @return string
     */
    public function get_description(): string {
        $enabled = !empty($this->other['enabled']);
        return get_string(
            $enabled ? 'eventstate_changed_enabled_desc' : 'eventstate_changed_disabled_desc',
            'bbbext_bnx'
        );
    }

    /**
     * Validate the payload.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['enabled']) || !is_bool($this->other['enabled'])) {
            throw new \coding_exception('The \'enabled\' value (bool) must be set in other.');
        }
    }
}
