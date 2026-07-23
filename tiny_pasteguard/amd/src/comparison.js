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

// Cap comparison strings to bound memory for pathological selections. Known
// limitation: two distinct inputs sharing an identical first 100 KB collide
// after truncation and will compare equal. Acceptable for the deterrent model.
export const MAXCOMPARELENGTH = 100000;

// Block-level (and line-break) elements after which a whitespace boundary is
// inserted before reading textContent, so adjacent blocks do not fuse. This
// makes HTML normalisation agree with TinyMCE's getContent({format: 'text'}),
// which separates blocks with newlines.
const BLOCKSELECTOR = [
    'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'div', 'dl', 'dt',
    'figure', 'figcaption', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'table',
    'tbody', 'td', 'th', 'thead', 'tr', 'ul',
].join(',');

/**
 * Strip HTML markup using DOMParser, returning textContent with a whitespace
 * boundary inserted after every block-level element.
 *
 * This is the production path: browsers always have DOMParser. Plain textContent
 * concatenates adjacent text nodes with no separator, so "<li>a</li><li>b</li>"
 * would become "ab"; inserting a boundary after each block yields "a b", which
 * matches the newline-separated output of getContent({format: 'text'}) once
 * whitespace is collapsed. Inline tags still fuse: "<b>bold</b>words" -> "boldwords".
 *
 * @param {string} html The HTML input.
 * @returns {string}
 */
export const stripHtmlDom = (html) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    doc.body.querySelectorAll(BLOCKSELECTOR).forEach((el) => {
        el.insertAdjacentText('afterend', ' ');
    });
    return doc.body.textContent || '';
};

/**
 * Strip HTML markup with a tag-replacing regex, returning text with each tag
 * replaced by a space.
 *
 * This is the fallback for environments without DOMParser (node unit tests).
 * It DIVERGES from stripHtmlDom on adjacent tags: "<b>bold</b>words" becomes
 * " bold words" (collapsing to "bold words"). Do not treat the two as
 * equivalent — see comparison.test.mjs for the pinned divergence.
 *
 * @param {string} html The HTML input.
 * @returns {string}
 */
export const stripHtmlRegex = (html) => html.replace(/<[^>]*>/g, ' ');

/**
 * The default stripper: DOMParser where available (production/browser),
 * otherwise the regex fallback (node). Exposed via the normalise parameter so
 * tests can drive a specific implementation deterministically.
 *
 * @param {string} html The HTML input.
 * @returns {string}
 */
export const defaultStripHtml = (html) =>
    (typeof DOMParser !== 'undefined' ? stripHtmlDom(html) : stripHtmlRegex(html));

/**
 * Normalise text for comparison: strip HTML, collapse whitespace (including
 * non-breaking spaces and newlines) to single spaces, trim, NFC normalise.
 *
 * @param {string} text Plain text or HTML.
 * @param {boolean} isHtml Whether the input should be parsed as HTML first.
 * @param {function} stripHtml HTML stripper to use; defaults to DOMParser with
 *   a regex fallback. Injectable so unit tests can exercise each path.
 * @returns {string}
 */
export const normalise = (text, isHtml = false, stripHtml = defaultStripHtml) => {
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
