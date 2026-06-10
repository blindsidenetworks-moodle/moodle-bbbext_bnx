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

use bbbext_bnx\observer;

/**
 * Tests for the BNX state_changed event and consolidated observer.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
#[\PHPUnit\Framework\Attributes\CoversClass(state_changed::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class state_changed_test extends \advanced_testcase {
    /**
     * Build a config_log_created event for the given plugin/value transition.
     *
     * @param string $plugin   Plugin name (e.g. 'bbbext_bnx', 'mod_assign').
     * @param string $name     Config name (typically 'disabled').
     * @param string $value    New value as written to mdl_config_log.
     * @param string $oldvalue Previous value.
     * @return \core\event\config_log_created
     */
    private function build_config_event(
        string $plugin,
        string $name,
        string $value,
        string $oldvalue
    ): \core\event\config_log_created {
        return \core\event\config_log_created::create([
            'context' => \context_system::instance(),
            'other'   => [
                'name'     => $name,
                'plugin'   => $plugin,
                'oldvalue' => $oldvalue,
                'value'    => $value,
            ],
        ]);
    }

    /**
     * Enabling BNX (disabled=0) must emit state_changed with enabled=true.
     *
     * @return void
     */
    public function test_enable_transition_triggers_event_with_enabled_true(): void {
        $this->resetAfterTest(true);

        $sink = $this->redirectEvents();
        observer::config_log_created($this->build_config_event('bbbext_bnx', 'disabled', '0', '1'));
        $events = $sink->get_events();
        $sink->close();

        $matching = array_values(array_filter(
            $events,
            static fn($e) => $e instanceof state_changed
        ));

        $this->assertCount(1, $matching, 'Exactly one state_changed event should be triggered');
        $this->assertTrue($matching[0]->other['enabled']);
    }

    /**
     * Disabling BNX (disabled=1) must emit state_changed with enabled=false.
     *
     * @return void
     */
    public function test_disable_transition_triggers_event_with_enabled_false(): void {
        $this->resetAfterTest(true);

        $sink = $this->redirectEvents();
        observer::config_log_created($this->build_config_event('bbbext_bnx', 'disabled', '1', '0'));
        $events = $sink->get_events();
        $sink->close();

        $matching = array_values(array_filter(
            $events,
            static fn($e) => $e instanceof state_changed
        ));

        $this->assertCount(1, $matching);
        $this->assertFalse($matching[0]->other['enabled']);
    }

    /**
     * Config events for other plugins must be ignored (no event triggered).
     *
     * @return void
     */
    public function test_unrelated_plugin_event_is_ignored(): void {
        $this->resetAfterTest(true);

        $sink = $this->redirectEvents();
        observer::config_log_created($this->build_config_event('mod_assign', 'disabled', '0', '1'));
        $events = $sink->get_events();
        $sink->close();

        $matching = array_filter($events, static fn($e) => $e instanceof state_changed);
        $this->assertEmpty($matching);
    }

    /**
     * Config events for unrelated config names (not 'disabled') must be ignored.
     *
     * @return void
     */
    public function test_unrelated_config_name_is_ignored(): void {
        $this->resetAfterTest(true);

        $sink = $this->redirectEvents();
        observer::config_log_created($this->build_config_event('bbbext_bnx', 'someother', '1', '0'));
        $events = $sink->get_events();
        $sink->close();

        $matching = array_filter($events, static fn($e) => $e instanceof state_changed);
        $this->assertEmpty($matching);
    }

    /**
     * BNX must never mutate mod_bigbluebuttonbn enablement when BNX is enabled.
     *
     * Reasserts the OL-3.1.3 / Option A boundary: administrators remain
     * responsible for parent-module enablement.
     *
     * @return void
     */
    public function test_enable_does_not_mutate_parent_module_state(): void {
        global $DB;
        $this->resetAfterTest(true);

        if (!$DB->record_exists('modules', ['name' => 'bigbluebuttonbn'])) {
            $this->markTestSkipped('mod_bigbluebuttonbn is not installed in this test environment.');
        }

        // Disable parent explicitly, then enable BNX, then assert parent is still disabled.
        \core\plugininfo\mod::enable_plugin('bigbluebuttonbn', 0);
        $before = (int) $DB->get_field('modules', 'visible', ['name' => 'bigbluebuttonbn']);

        observer::config_log_created($this->build_config_event('bbbext_bnx', 'disabled', '0', '1'));

        $after = (int) $DB->get_field('modules', 'visible', ['name' => 'bigbluebuttonbn']);
        $this->assertSame(
            $before,
            $after,
            'BNX must not change mod_bigbluebuttonbn enablement on its own state change'
        );
    }
}
