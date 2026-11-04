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
 * Controls the Waiting Room site-setting dependencies.
 *
 * @module     bbbext_bnx/waiting_room_settings
 */

const ENABLED_SELECTOR = '#id_s_bbbext_bnx_approvalbeforejoin_enabled';
const DEPENDENT_SELECTORS = [
    '#id_s_bbbext_bnx_approvalbeforejoin_default',
    '#id_s_bbbext_bnx_approvalbeforejoin_editable',
];

/**
 * Enable or disable the dependent Waiting Room configuration controls.
 *
 * @param {HTMLInputElement} enabledControl The feature master toggle.
 */
const updateDependentControls = (enabledControl) => {
    const disabled = !enabledControl.checked;

    DEPENDENT_SELECTORS.forEach((selector) => {
        const control = document.querySelector(selector);
        if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
            control.disabled = disabled;
        }
    });
};

/**
 * Initialise the Waiting Room setting dependencies.
 */
export const init = () => {
    const enabledControl = document.querySelector(ENABLED_SELECTOR);
    if (!(enabledControl instanceof HTMLInputElement)) {
        return;
    }

    updateDependentControls(enabledControl);
    enabledControl.addEventListener('change', () => updateDependentControls(enabledControl));
};
