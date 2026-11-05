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
 * Controls BNX feature site-setting dependencies.
 *
 * @module     bbbext_bnx/waiting_room_settings
 */

const FEATURES = {
    approvalbeforejoin: ['approvalbeforejoin'],
    locksettings: ['cam', 'mic', 'publicchat', 'privatechat', 'notes', 'userlist', 'hideviewerscursor'],
};

/**
 * Enable or disable dependent feature configuration controls.
 *
 * @param {HTMLInputElement} enabledControl The feature master toggle.
 * @param {Array<[HTMLInputElement|HTMLSelectElement, boolean]>} controls Dependent controls and their original states.
 */
const updateDependentControls = (enabledControl, controls) => {
    const disabled = !enabledControl.checked;

    controls.forEach(([control, originallyDisabled]) => {
        control.disabled = disabled || originallyDisabled;
    });
};

/**
 * Initialise feature setting dependencies.
 */
export const init = () => {
    Object.entries(FEATURES).forEach(([feature, dependencies]) => {
        const enabledControl = document.querySelector(`#id_s_bbbext_bnx_${feature}_enabled`);
        if (!(enabledControl instanceof HTMLInputElement)) {
            return;
        }

        const controls = [];
        dependencies.forEach((dependency) => {
            ['default', 'editable'].forEach((suffix) => {
                const control = document.querySelector(`#id_s_bbbext_bnx_${dependency}_${suffix}`);
                if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement)) {
                    return;
                }
                controls.push([control, control.disabled]);
                if (control instanceof HTMLInputElement) {
                    const hiddenControl = control.parentElement?.querySelector('input[type="hidden"]');
                    if (hiddenControl instanceof HTMLInputElement && hiddenControl.name === control.name) {
                        controls.push([hiddenControl, hiddenControl.disabled]);
                    }
                }
            });
        });

        updateDependentControls(enabledControl, controls);
        enabledControl.addEventListener('change', () => updateDependentControls(enabledControl, controls));
    });
};
