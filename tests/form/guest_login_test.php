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

namespace bbbext_bnx\form;

use mod_bigbluebuttonbn\instance;

/**
 * Tests for {@see \bbbext_bnx\form\guest_login::validation()}.
 *
 * Locks the OL-3.2.6 remediation: guest-password comparison must use
 * `hash_equals()` against string-cast operands so that:
 *   - wrong passwords are rejected,
 *   - correct passwords are accepted, and
 *   - PHP type-juggling does not let numeric-looking inputs like `"0"`
 *     authenticate against an empty or differently-typed expected value.
 *
 * @package   bbbext_bnx
 * @copyright 2026 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
final class guest_login_test extends \advanced_testcase {
    /**
     * Build a guest-login form instance bound to a freshly-created BBB activity.
     *
     * @return array{0: guest_login, 1: instance} Form and the underlying instance.
     */
    private function build_form(): array {
        global $CFG;

        $CFG->bigbluebuttonbn['guestaccess_enabled'] = 1;

        $course = $this->getDataGenerator()->create_course();
        $bbbgenerator = $this->getDataGenerator()->get_plugin_generator('mod_bigbluebuttonbn');
        $activity = $bbbgenerator->create_instance([
            'course' => $course->id,
            'guestallowed' => 1,
        ]);
        $instance = instance::get_from_instanceid($activity->id);

        // Build the form non-interactively. `_customdata` is the only payload
        // the form needs; `definition()` will run via the parent constructor.
        $form = new guest_login(null, [
            'instance' => $instance,
            'uid' => 'test-uid',
        ]);
        return [$form, $instance];
    }

    /**
     * The correct password must be accepted (no `password` error returned).
     *
     * @return void
     */
    public function test_validation_accepts_correct_password(): void {
        $this->resetAfterTest(true);

        [$form, $instance] = $this->build_form();
        $expected = (string) $instance->get_guest_access_password();
        $this->assertNotSame('', $expected, 'Activity should expose a guest access password.');

        $errors = $form->validation(['password' => $expected], []);
        $this->assertArrayNotHasKey('password', $errors);
    }

    /**
     * An obviously-wrong password must be rejected.
     *
     * @return void
     */
    public function test_validation_rejects_wrong_password(): void {
        $this->resetAfterTest(true);

        [$form] = $this->build_form();

        $errors = $form->validation(['password' => 'definitely-not-the-password'], []);
        $this->assertArrayHasKey('password', $errors);
    }

    /**
     * The numeric string `"0"` must NOT authenticate against a non-empty password.
     *
     * Before OL-3.2.6 the comparison was `!=`, which under PHP type-juggling
     * could evaluate `"0" != "some-password"` correctly but also allowed other
     * juggle cases (e.g. `0 == ""`). The `hash_equals()`-on-strings rewrite
     * removes that whole class of risk; this test pins down the most common
     * juggle vector.
     *
     * @return void
     */
    public function test_validation_rejects_numeric_zero_against_real_password(): void {
        $this->resetAfterTest(true);

        [$form] = $this->build_form();

        $errors = $form->validation(['password' => '0'], []);
        $this->assertArrayHasKey('password', $errors);
    }

    /**
     * An empty submitted password must be rejected even when the expected
     * password is a non-empty string.
     *
     * @return void
     */
    public function test_validation_rejects_empty_password(): void {
        $this->resetAfterTest(true);

        [$form] = $this->build_form();

        $errors = $form->validation(['password' => ''], []);
        $this->assertArrayHasKey('password', $errors);
    }

    /**
     * A missing `password` key in the submitted data must be treated as empty
     * and rejected, never as a match.
     *
     * @return void
     */
    public function test_validation_rejects_missing_password_key(): void {
        $this->resetAfterTest(true);

        [$form] = $this->build_form();

        $errors = $form->validation([], []);
        $this->assertArrayHasKey('password', $errors);
    }
}
