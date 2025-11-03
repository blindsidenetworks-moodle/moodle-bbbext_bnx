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

namespace bbbext_bnx\local\service;

defined('MOODLE_INTERNAL') || die();

use moodle_database;

/**
 * Service wrapper for BNX settings persistence.
 *
 * This helper intentionally restricts itself to the `bbbext_bnx_settings`
 * table. It never creates, updates, or deletes rows from the parent
 * `bbbext_bnx` table, leaving lifecycle management for that table to other
 * components.
 *
 * @package   bbbext_bnx
 */
class bnx_settings_service {
    public const BNX_SETTINGS_TABLE = 'bbbext_bnx_settings';

    /**
     * Fetch settings for a BNX record.
     *
     * @param int $bnxid BNX table primary key
     * @return array<string, int>
     */
    public function get_settings(int $bnxid): array {
        global $DB;
        $records = $DB->get_records(self::BNX_SETTINGS_TABLE, ['bnxid' => $bnxid], 'setting ASC', 'setting, value');
        $result = [];
        foreach ($records as $record) {
            $result[$record->setting] = (int)$record->value;
        }
        return $result;
    }

    /**
     * Fetch a single setting value for the BNX record.
     *
     * @param int $bnxid
     * @param string $name
     * @return int|null
     */
    public function get_setting(int $bnxid, string $name): ?int {
        global $DB;
        $record = $DB->get_record(self::BNX_SETTINGS_TABLE, [
            'bnxid' => $bnxid,
            'setting' => $name,
        ], 'value', IGNORE_MISSING);

        return $record ? (int)$record->value : null;
    }

    /**
     * Upsert multiple settings for a BNX record.
     *
     * @param int $bnxid
     * @param array<string, mixed> $values
     */
    public function set_settings(int $bnxid, array $values): void {
        foreach ($values as $name => $value) {
            $this->set_setting($bnxid, (string)$name, $value);
        }
    }

    /**
     * Remove all settings for a BNX record.
     *
     * @param int $bnxid
     */
    public function delete_settings(int $bnxid): void {
        global $DB;
        $DB->delete_records(self::BNX_SETTINGS_TABLE, ['bnxid' => $bnxid]);
    }

    /**
     * Remove a specific setting entry for a BNX record.
     *
     * @param int $bnxid
     * @param string $name
     */
    public function delete_setting(int $bnxid, string $name): void {
        global $DB;
        $DB->delete_records(self::BNX_SETTINGS_TABLE, [
            'bnxid' => $bnxid,
            'setting' => $name,
        ]);
    }

    /**
     * Internal helper to upsert a single setting row.
     *
     * @param int $bnxid
     * @param string $name
     * @param mixed $value
     */
    private function set_setting(int $bnxid, string $name, $value): void {
        global $DB;
        $normalised = $this->normalise_value($value);
        $now = time();

        $record = $DB->get_record(self::BNX_SETTINGS_TABLE, [
            'bnxid' => $bnxid,
            'setting' => $name,
        ]);

        if ($record) {
            if ((int)$record->value === $normalised) {
                return;
            }
            $record->value = $normalised;
            $record->timemodified = $now;
            $DB->update_record(self::BNX_SETTINGS_TABLE, $record);
            return;
        }

        $DB->insert_record(self::BNX_SETTINGS_TABLE, (object) [
            'bnxid' => $bnxid,
            'setting' => $name,
            'value' => $normalised,
            'timemodified' => $now,
        ]);
    }

    /**
     * Normalise incoming values so they match the schema.
     *
     * @param mixed $value Raw value
     * @return int
     */
    private function normalise_value($value): int {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return empty($value) ? 0 : 1;
    }
}
