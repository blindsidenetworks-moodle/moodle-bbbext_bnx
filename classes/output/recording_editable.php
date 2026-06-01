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

namespace bbbext_bnx\output;

use core\output\inplace_editable;
use mod_bigbluebuttonbn\instance;
use mod_bigbluebuttonbn\recording;
use mod_bigbluebuttonbn\output\recording_editable as base_recording_editable;

/**
 * Base editable wrapper for BNX recording fields.
 *
 * @package    bbbext_bnx
 * @copyright  2025 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class recording_editable extends base_recording_editable {
    /**
     * Get the real recording value.
     *
     * @param recording $rec
     * @return string
     */
    abstract public function get_recording_value(recording $rec): string;

    /**
     * Constructor.
     *
     * @param recording $rec
     * @param instance $instance
     * @param string $edithint
     * @param string $editlabel
     */
    public function __construct(recording $rec, instance $instance, string $edithint = '', string $editlabel = '') {
        $this->instance = $instance;

        $editable = $this->check_capability();
        $displayvalue = format_string(
            $this->get_recording_value($rec),
            true,
            ['context' => $instance->get_context()]
        );

        inplace_editable::__construct(
            'bbbext_bnx',
            static::get_type(),
            $rec->get('id'),
            $editable,
            $displayvalue,
            $displayvalue,
            $edithint,
            $editlabel
        );
    }

    /**
     * Update the recording with the new value.
     *
     * @param int $itemid
     * @param mixed $value
     * @return recording_editable
     */
    public static function update($itemid, $value) {
        $recording = \bbbext_bnx\recording::get_record(['id' => $itemid]);
        if ($recording === false) {
            throw new \moodle_exception('nosuchinstance', 'mod_bigbluebuttonbn', '', (object) [
                'id' => $itemid,
                'entity' => 'recording',
            ]);
        }
        $bigbluebuttonbnid = (int) $recording->get('bigbluebuttonbnid');
        $instance = instance::get_from_instanceid($bigbluebuttonbnid);
        // The owning activity may have been deleted while this inplace edit was in flight.
        // Refuse the update rather than calling methods on a null instance.
        if ($instance === null) {
            throw new \moodle_exception('nosuchinstance', 'mod_bigbluebuttonbn', '', (object) [
                'id' => $bigbluebuttonbnid,
                'entity' => 'bigbluebuttonbn',
            ]);
        }

        require_login($instance->get_course(), true, $instance->get_cm());
        require_capability('mod/bigbluebuttonbn:managerecordings', $instance->get_context());

        // Moodle Security policy: user-submitted content must be cleaned before storage.
        // Recording 'name' is short text without markup; recording 'description' is short
        // descriptive text rendered inside an inplace editable, so it should not carry
        // active HTML. Both are normalised here using core PARAM cleaners.
        $cleanvalue = clean_param((string) $value, PARAM_NOTAGS);

        $recording->set(static::get_type(), $cleanvalue);
        $recording->update();

        return new static($recording, $instance);
    }
}
