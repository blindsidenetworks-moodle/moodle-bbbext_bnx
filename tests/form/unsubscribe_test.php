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
 * Test definitions for the unsubscribe confirmation form.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace bbbext_bnx\form;

/**
 * Tests for the unsubscribe confirmation form.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(unsubscribe::class)]
final class unsubscribe_test extends \advanced_testcase {
    /**
     * The interactive form must never carry an identity supplied by the request.
     *
     * @return void
     */
    public function test_form_does_not_include_identity_fields(): void {
        $form = new unsubscribe(null, ['cmid' => 123]);
        $formproperty = new \ReflectionProperty(unsubscribe::class, '_form');
        $mform = $formproperty->getValue($form);

        $this->assertTrue($mform->elementExists('cmid'));
        $this->assertFalse($mform->elementExists('userid'));
        $this->assertFalse($mform->elementExists('email'));
    }
}
