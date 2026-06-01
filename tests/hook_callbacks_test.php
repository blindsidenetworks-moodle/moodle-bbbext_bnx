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

/**
 * Tests for hook_callbacks.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 * @coversDefaultClass \bbbext_bnx\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Test the enablement helper reports enabled when BNX is active.
     *
     * @return void
     * @covers ::is_enabled
     */
    public function test_is_enabled_returns_true_when_plugin_is_enabled(): void {
        $this->resetAfterTest(true);

        // BNX plugininfo gates `is_enabled()` on the parent `mod_bigbluebuttonbn`
        // being enabled. The parent module installs itself with visible=0, and
        // since Phase 3 BNX no longer auto-enables it. Tests that need a
        // fully-enabled BNX state must enable the parent explicitly.
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 1);
        unset_config('disabled', 'bbbext_bnx');
        \core_plugin_manager::reset_caches();

        $this->assertTrue(hook_callbacks::is_enabled());
    }

    /**
     * Test the enablement helper reports disabled when BNX is disabled.
     *
     * @return void
     * @covers ::is_enabled
     */
    public function test_is_enabled_returns_false_when_plugin_is_disabled(): void {
        $this->resetAfterTest(true);

        set_config('disabled', 1, 'bbbext_bnx');
        \core_plugin_manager::reset_caches();

        $this->assertFalse(hook_callbacks::is_enabled());
    }
}
