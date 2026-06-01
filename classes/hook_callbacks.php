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

use context_module;
use core\hook\output\before_standard_footer_html_generation;
use html_writer;

/**
 * Hook callbacks for BN Experience extension.
 *
 * @package    bbbext_bnx
 * @copyright  2025 onwards, Blindside Networks Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class hook_callbacks {
    /**
     * Check whether BNX is enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('bbbext_bnx');
        return $plugininfo && $plugininfo->is_enabled();
    }

    /**
     * Inject the secondary navigation label override for BigBlueButton module pages.
     *
     * This hook callback runs on every page before the footer is rendered.
     * If the current page is within a BigBlueButton module context, it outputs
     * a hidden span with the custom label and initializes the overridenav JS module.
     *
     * @param before_standard_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function before_footer(before_standard_footer_html_generation $hook): void {
        global $PAGE;

        if (!self::is_enabled()) {
            return;
        }

        // Cheap page-level guard so the hook is a no-op on every non-BBB page
        // footer render and we never reach a DB lookup (OL-3.1.9).
        if (empty($PAGE->cm) || $PAGE->cm->modname !== 'bigbluebuttonbn') {
            return;
        }

        // Build the custom label.
        $label = get_string('navlabel', 'bbbext_bnx');

        // Output hidden span with the label data.
        $hook->add_html(
            html_writer::span('', 'bbbext-bnx-navlabel', [
                'data-label' => $label,
                'hidden' => 'hidden',
            ])
        );

        // Initialize the JavaScript module to override the nav label.
        $PAGE->requires->js_call_amd('bbbext_bnx/overridenav', 'init');
    }
}
