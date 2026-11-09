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
    approvalbeforejoin: ['approvalbeforejoin_default', 'approvalbeforejoin_editable'],
    earlyaccess: ['earlyaccess_default', 'earlyaccess_editable'],
    locksettings: [
        'cam_default', 'cam_editable', 'mic_default', 'mic_editable',
        'publicchat_default', 'publicchat_editable', 'privatechat_default', 'privatechat_editable',
        'notes_default', 'notes_editable', 'userlist_default', 'userlist_editable',
        'hideviewerscursor_default', 'hideviewerscursor_editable',
    ],
    logo: ['logo_source', 'logo_default', 'logo_editable'],
    preuploads: ['maxfiles'],
    reminder: ['reminder_default', 'reminder_editable', 'emailsubject', 'emailtemplate', 'emailfooter'],
};

/**
 * Set the mode for a TinyMCE editor backed by an admin-setting textarea.
 *
 * @param {HTMLTextAreaElement} control The editor's source textarea.
 * @param {boolean} disabled Whether the editor should be read-only.
 */
const updateEditorMode = (control, disabled) => {
    if (!window.tinymce) {
        return;
    }

    const editor = window.tinymce.get(control.id);
    if (editor) {
        editor.mode.set(disabled ? 'readonly' : 'design');
    }
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
        const controlDisabled = disabled || originallyDisabled;
        control.disabled = controlDisabled;
        if (control instanceof HTMLTextAreaElement) {
            updateEditorMode(control, controlDisabled);
        }
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
            const control = document.querySelector(`#id_s_bbbext_bnx_${dependency}`);
            if (!(
                control instanceof HTMLInputElement || control instanceof HTMLSelectElement ||
                control instanceof HTMLTextAreaElement
            )) {
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

        updateDependentControls(enabledControl, controls);
        enabledControl.addEventListener('change', () => updateDependentControls(enabledControl, controls));
    });
};
