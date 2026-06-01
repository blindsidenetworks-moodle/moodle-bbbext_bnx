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
 * Module to override the secondary navigation label.
 *
 * Rewritten as an ES module without the jquery dependency (OL-3.1.1).
 *
 * @module     bbbext_bnx/overridenav
 */

// Target the BigBlueButton activity link in secondary nav (links to view.php).
const SELECTOR = '.secondary-navigation a.nav-link[href*="/mod/bigbluebuttonbn/view.php"]';
const LABEL_SOURCE_SELECTOR = '.bbbext-bnx-navlabel';

/**
 * Determine the label to use for the secondary navigation node.
 *
 * @param {string} [provided] Optional label supplied by the caller.
 * @returns {string|undefined}
 */
const resolveLabel = (provided) => {
    if (typeof provided === 'string' && provided.trim() !== '') {
        return provided;
    }

    const source = document.querySelector(LABEL_SOURCE_SELECTOR);
    if (!source) {
        return undefined;
    }

    const label = source.dataset.label;
    if (typeof label === 'string' && label.trim() !== '') {
        return label;
    }

    return undefined;
};

/**
 * Apply the resolved label to the secondary navigation node.
 *
 * @param {string} [label]
 */
const applyLabel = (label) => {
    const node = document.querySelector(SELECTOR);
    if (!node) {
        return;
    }

    const resolvedLabel = resolveLabel(label);
    if (!resolvedLabel) {
        return;
    }

    node.textContent = resolvedLabel;
};

/**
 * Entry point invoked via js_call_amd.
 *
 * @param {string} [label]
 */
export const init = (label) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => applyLabel(label), {once: true});
    } else {
        applyLabel(label);
    }
};
