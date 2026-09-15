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
 * CLI script to migrate legacy BN Reminders data into BNX.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../db/migration.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($unrecognized)) {
    $unrecognized = implode(' ', $unrecognized);
    cli_error("Unrecognised option(s): {$unrecognized}");
}

if (!empty($options['help'])) {
    $help = <<<TXT
Migrate legacy BN Reminders data into BNX.

This command runs the idempotent BN Reminders migration helper. BNX cannot run
while legacy BN Reminders remains enabled. After migration, disable or remove BN
Reminders in Plugins overview, then enable BNX.

Run this from the Moodle root as: php public/mod/bigbluebuttonbn/extension/bnx/cli/migrate_bnreminders.php

Options:
  -h, --help  Print this help
TXT;
    cli_writeln($help);
    exit(0);
}

cli_heading('BNX BN Reminders migration');
cli_writeln('Running legacy BN Reminders migration helper...');
bbbext_bnx_migrate_bnreminders_data();
cli_writeln('Migration helper finished.');
