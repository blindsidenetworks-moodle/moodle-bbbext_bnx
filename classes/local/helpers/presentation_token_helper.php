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

use context_system;
use core_external\util;

/**
 * Creates and consumes temporary, activity-bound presentation file tokens.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presentation_token_helper {
    /** Temporary presentation-file service name. */
    public const SERVICE_NAME = 'bbbext_bnx_get_file';

    /** Table storing BNX-owned token limits. */
    public const TOKENS_TABLE = 'bbbext_bnx_presentation_tokens';

    /** Token lifetime in seconds. */
    private const TOKEN_LIFETIME = 600;

    /**
     * Create a short-lived token for one BNX activity.
     *
     * @param int $bnxid BNX instance identifier.
     * @param int $uses Number of permitted file requests.
     * @return string|null
     */
    public static function create_token(int $bnxid, int $uses): ?string {
        global $DB;

        if ($uses < 1 || !$DB->record_exists('bbbext_bnx', ['id' => $bnxid])) {
            return null;
        }

        try {
            $service = util::get_service_by_name(self::SERVICE_NAME);
            $validuntil = time() + self::TOKEN_LIFETIME;
            $token = util::generate_token(
                EXTERNAL_TOKEN_PERMANENT,
                $service,
                presentation_user_helper::get_or_create_user()->id,
                context_system::instance(),
                $validuntil,
                '',
                'BNX presentation file'
            );
            $coretoken = self::get_webservice_manager()->get_user_ws_token($token);
        } catch (\Exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        $DB->insert_record(self::TOKENS_TABLE, (object) [
            'tokenid' => $coretoken->id,
            'bnxid' => $bnxid,
            'remaininguses' => $uses,
            'validuntil' => $validuntil,
            'revoked' => 0,
            'timecreated' => time(),
        ]);

        return $token;
    }

    /**
     * Consume one permitted request from a temporary presentation token.
     *
     * @param string $token Token value.
     * @param int $bnxid BNX instance that owns the requested presentation.
     * @return bool
     */
    public static function consume_token(string $token, int $bnxid): bool {
        global $DB;

        try {
            $webservice = self::get_webservice_manager();
            $coretoken = $webservice->get_user_ws_token($token);
            $service = util::get_service_by_name(self::SERVICE_NAME);
        } catch (\Exception $exception) {
            return false;
        }

        $record = $DB->get_record(self::TOKENS_TABLE, ['tokenid' => $coretoken->id], '*', IGNORE_MISSING);
        $now = time();
        if (
            !$record
            || (int)$coretoken->externalserviceid !== (int)$service->id
            || (int)$record->bnxid !== $bnxid
            || (int)$record->revoked !== 0
            || (int)$record->remaininguses < 1
            || (int)$record->validuntil <= $now
            || ((int)$coretoken->validuntil !== 0 && (int)$coretoken->validuntil <= $now)
        ) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $record->remaininguses--;
        if ((int)$record->remaininguses === 0) {
            $record->revoked = 1;
        }
        $DB->update_record(self::TOKENS_TABLE, $record);

        if ((int)$record->revoked === 1) {
            $webservice->delete_user_ws_token($coretoken->id);
        }
        $transaction->allow_commit();

        return true;
    }

    /**
     * Load and return Moodle's webservice manager.
     *
     * @return \webservice
     */
    private static function get_webservice_manager(): \webservice {
        global $CFG;

        require_once($CFG->dirroot . '/webservice/lib.php');
        return new \webservice();
    }
}
