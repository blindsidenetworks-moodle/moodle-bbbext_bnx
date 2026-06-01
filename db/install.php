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
 * Install script for BigBlueButton Override Recordings View
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    bbbext_bnx
 * @copyright  2025 Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */

/**
 * Perform the post-install procedures.
 *
 * @return bool
 */
function xmldb_bbbext_bnx_install() {
    require_once(__DIR__ . '/migration.php');

    // Enable the plugin by default. BNX never enables sibling or parent plugins;
    // administrators are responsible for enabling mod_bigbluebuttonbn explicitly.
    set_config('enabled', 1, 'bbbext_bnx');

    // Migrate legacy BN Reminders data/settings and disable bnreminders if present.
    bbbext_bnx_migrate_bnreminders_data();

    // One-time migration from core lock settings into BNX lock settings.
    bbbext_bnx_migrate_core_locksettings_data();
}
