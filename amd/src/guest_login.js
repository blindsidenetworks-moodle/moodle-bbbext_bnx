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
 * Guest login form handler.
 *
 * Replaces the previous inline js_init_code block in guest.php (OL-4.1.4).
 * Opens the join flow in a script-opened named child window so the BBB
 * client can later auto-close that window on logout (browsers only allow
 * window.close() on script-opened windows).
 *
 * @module     bbbext_bnx/guest_login
 */

const TARGET_WINDOW_NAME = 'bigbluebutton_conference';

/**
 * Attach the submit handler that retargets the form to a script-opened window.
 *
 * @param {string} [formSelector] CSS selector for the guest login form.
 */
export const init = (formSelector) => {
    const selector = (typeof formSelector === 'string' && formSelector !== '')
        ? formSelector
        : 'form.mform';

    const attach = () => {
        const form = document.querySelector(selector);
        if (!form) {
            return;
        }

        form.addEventListener('submit', () => {
            window.open('', TARGET_WINDOW_NAME);
            form.setAttribute('target', TARGET_WINDOW_NAME);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attach, {once: true});
    } else {
        attach();
    }
};
