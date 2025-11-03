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

namespace bbbext_bnx\bigbluebuttonbn;

defined('MOODLE_INTERNAL') || die();

use moodle_database;
use stdClass;

/**
 * BNX lifecycle helper.
 *
 * @package   bbbext_bnx
 */
class mod_instance_helper extends \mod_bigbluebuttonbn\local\extension\mod_instance_helper {
    /**
     * Ensure a BNX base row exists for the created module.
     *
     * @param stdClass $bigbluebuttonbn
     */
    public function add_instance(stdClass $bigbluebuttonbn) {
        $this->persist_bnx_record($bigbluebuttonbn);
    }

    /**
     * Keep BNX data in sync when the module is updated.
     *
     * @param stdClass $bigbluebuttonbn
     */
    public function update_instance(stdClass $bigbluebuttonbn): void {
        $this->persist_bnx_record($bigbluebuttonbn);
    }

    /**
     * Drop BNX data when the module is deleted.
     *
     * @param int $moduleid
     */
    public function delete_instance(int $moduleid): void {
        global $DB;
        $bnxrecord = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $moduleid], 'id');
        if (!$bnxrecord) {
            return;
        }

        $DB->delete_records('bbbext_bnx_settings', ['bnxid' => $bnxrecord->id]);
        $DB->delete_records('bbbext_bnx', ['id' => $bnxrecord->id]);
    }

    /**
     * Report join tables owned by this extension.
     *
     * @return string[]
     */
    public function get_join_tables(): array {
        return [
            'bbbext_bnx',
        ];
    }

    /**
     * Guarantee the BNX base record exists for the supplied module data.
     *
     * @param stdClass $data
     */
    private function persist_bnx_record(stdClass $data): void {
        $moduleid = $this->resolve_module_id($data);
        if ($moduleid !== null) {
            $this->upsert_bnx_record($moduleid);
        }
    }

    /**
     * Extract the module id from the supplied data payload.
     *
     * @param stdClass $data
     * @return int|null
     */
    private function resolve_module_id(stdClass $data): ?int {
        return match (true) {
            !empty($data->id) => (int)$data->id,
            !empty($data->instance) => (int)$data->instance,
            !empty($data->bigbluebuttonbnid) => (int)$data->bigbluebuttonbnid,
            default => null,
        };
    }

    /**
     * Create or refresh the BNX base record for the module id.
     *
     * @param int $moduleid
     */
    private function upsert_bnx_record(int $moduleid): void {
        global $DB;
        $record = $DB->get_record('bbbext_bnx', ['bigbluebuttonbnid' => $moduleid]);
        $now = time();

        if ($record) {
            $record->timemodified = $now;
            $DB->update_record('bbbext_bnx', $record);
            return;
        }

        $DB->insert_record('bbbext_bnx', (object) [
            'bigbluebuttonbnid' => $moduleid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
