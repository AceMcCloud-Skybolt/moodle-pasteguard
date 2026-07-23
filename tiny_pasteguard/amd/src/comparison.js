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
 * Pure text comparison logic for the Tiny PasteGuard plugin.
 *
 * Deliberately free of Moodle and TinyMCE imports so it can be unit tested
 * outside the browser (see tests/js/comparison.test.mjs).
 *
 * @module      tiny_pasteguard/comparison
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Cap comparison strings to bound memory for pathological selections. Both
// sides are truncated identically so equality is preserved.
export const MAXCOMPARELENGTH = 100000;

/**
 * Strip HTML markup from a string, returning its text content.
 *
 * Uses DOMParser where available (browsers); falls back to a tag-stripping
 * regex where it is not (unit tests under node).
 *
 * @param {string} html The HTML input.
 * @returns {string}
 */
const stripHtml = (html) => {
    if (typeof DOMParser !== 'undefined') {
        return new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
    }
    return html.replace(/<[^>]*>/g, ' ');
};

/**
 * Normalise text for comparison: strip HTML, collapse whitespace (including
 * non-breaking spaces and newlines) to single spaces, trim, NFC normalise.
 *
 * @param {string} text Plain text or HTML.
 * @param {boolean} isHtml Whether the input should be parsed as HTML first.
 * @returns {string}
 */
export const normalise = (text, isHtml = false) => {
    let plain = text || '';
    if (isHtml) {
        plain = stripHtml(plain);
    }
    // \s matches all Unicode whitespace including non-breaking spaces (U+00A0).
    return plain
        .replace(/\s+/g, ' ')
        .trim()
        .normalize('NFC')
        .substring(0, MAXCOMPARELENGTH);
};

/**
 * Decide whether normalised incoming content matches the internal allowlist.
 *
 * Exact match only: substring matching would let one internal word licence an
 * external paragraph containing it. Empty incoming content is never allowed,
 * as provenance cannot be established (e.g. image-only clipboard).
 *
 * @param {string|null} internal The normalised internal allowlist entry, or null.
 * @param {string} incoming Normalised incoming text.
 * @returns {boolean}
 */
export const matches = (internal, incoming) => {
    if (incoming === '') {
        return false;
    }
    return internal !== null && incoming === internal;
};
