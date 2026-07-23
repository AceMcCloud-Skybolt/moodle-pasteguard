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
 * Unit tests for tiny_pasteguard/comparison.
 *
 * Run with: npm test   (or: node --test tiny_pasteguard/tests/js/)
 * Requires Node >= 22 and the jsdom dev dependency.
 *
 * jsdom provides a real DOMParser so the production stripHtmlDom path is
 * exercised directly, rather than a regex approximation of it.
 *
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {JSDOM} from 'jsdom';

// Install a genuine DOMParser globally before importing the module under test,
// so stripHtmlDom and the default stripper resolve to the real DOM path.
const {window} = new JSDOM('');
globalThis.DOMParser = window.DOMParser;

const {
    normalise,
    matches,
    stripHtmlDom,
    stripHtmlRegex,
    MAXCOMPARELENGTH,
} = await import('../../amd/src/comparison.js');

/**
 * Represent what TinyMCE's getContent({format: 'text'}) yields: innerText,
 * which separates block-level elements with newlines. Used to check that a
 * text-format capture and the same content arriving as pasted HTML normalise
 * to the same string.
 *
 * @param {string} text Newline-separated block text.
 * @returns {string}
 */
const textFormatCapture = (text) => normalise(text);

test('normalise collapses whitespace runs to single spaces', () => {
    assert.equal(normalise("one\n  two\t\tthree   four"), 'one two three four');
});

test('normalise collapses non-breaking spaces (U+00A0)', () => {
    const input = 'one two  three';
    assert.ok(input.includes(' '));
    assert.equal(normalise(input), 'one two three');
});

test('normalise trims leading and trailing whitespace', () => {
    assert.equal(normalise('  padded  '), 'padded');
});

test('normalise applies NFC so composed and decomposed forms compare equal', () => {
    // Built from escapes so no editor/tool can silently NFC-normalise them into
    // the same byte sequence (which would make the guard below pass vacuously).
    const composed = 'caf\u00e9';        // e-acute as one code point (U+00E9).
    const decomposed = 'cafe\u0301';      // e + combining acute (U+0065 U+0301).
    assert.notEqual(composed, decomposed);
    assert.equal(normalise(decomposed), normalise(composed));
});

test('stripHtmlDom inserts a boundary after block elements', () => {
    assert.equal(normalise('<ul><li>a</li><li>b</li></ul>', true, stripHtmlDom), 'a b');
    assert.equal(normalise('<p>one</p><p>two</p>', true, stripHtmlDom), 'one two');
});

test('stripHtmlDom fuses adjacent inline tags (no boundary)', () => {
    assert.equal(normalise('<b>bold</b>words', true, stripHtmlDom), 'boldwords');
});

test('regex and DOM strippers diverge on adjacent inline tags (pinned)', () => {
    const html = '<b>bold</b>words';
    assert.equal(normalise(html, true, stripHtmlRegex), 'bold words');
    assert.equal(normalise(html, true, stripHtmlDom), 'boldwords');
    assert.notEqual(
        normalise(html, true, stripHtmlRegex),
        normalise(html, true, stripHtmlDom)
    );
});

test('regex and DOM strippers diverge on HTML entities (pinned)', () => {
    // DOMParser decodes entities; the regex fallback leaves them literal.
    assert.equal(normalise('a&amp;b', true, stripHtmlDom), 'a&b');
    assert.equal(normalise('a&amp;b', true, stripHtmlRegex), 'a&amp;b');
    assert.notEqual(
        normalise('a&amp;b', true, stripHtmlDom),
        normalise('a&amp;b', true, stripHtmlRegex)
    );
    // &nbsp; decodes to U+00A0 under DOM, then collapses to a plain space.
    assert.equal(normalise('one&nbsp;two', true, stripHtmlDom), 'one two');
    assert.equal(normalise('one&nbsp;two', true, stripHtmlRegex), 'one&nbsp;two');
});

test('round-trip: multi-block text-format capture matches the same pasted HTML', () => {
    // List.
    assert.equal(
        normalise('<ul><li>Alpha</li><li>Beta</li><li>Gamma</li></ul>', true, stripHtmlDom),
        textFormatCapture('Alpha\nBeta\nGamma')
    );
    // Two paragraphs.
    assert.equal(
        normalise('<p>First para.</p><p>Second para.</p>', true, stripHtmlDom),
        textFormatCapture('First para.\n\nSecond para.')
    );
    // Blockquote followed by a paragraph.
    assert.equal(
        normalise('<blockquote>Quoted line</blockquote><p>After</p>', true, stripHtmlDom),
        textFormatCapture('Quoted line\nAfter')
    );
    // Combined multi-block selection.
    const html = '<p>Intro.</p><ul><li>one</li><li>two</li></ul><blockquote>Cited.</blockquote>';
    const captured = textFormatCapture('Intro.\n\none\ntwo\n\nCited.');
    assert.equal(normalise(html, true, stripHtmlDom), captured);
    // And that captured form actually matches an allowlist comparison.
    assert.equal(matches(normalise(html, true, stripHtmlDom), captured), true);
});

test('truncation caps normalised length at MAXCOMPARELENGTH', () => {
    assert.equal(normalise('a'.repeat(MAXCOMPARELENGTH + 500)).length, MAXCOMPARELENGTH);
});

test('a normalised string matches its own truncation', () => {
    const s = normalise('a'.repeat(MAXCOMPARELENGTH + 500));
    assert.equal(matches(s, s), true);
});

test('strings differing within the first 100 KB do not match', () => {
    const a = normalise('a'.repeat(MAXCOMPARELENGTH - 1) + 'X');
    const b = normalise('a'.repeat(MAXCOMPARELENGTH - 1) + 'Y');
    assert.notEqual(a, b);
    assert.equal(matches(a, b), false);
});

test('KNOWN LIMITATION: inputs sharing a >100 KB prefix collide after truncation', () => {
    const a = normalise('a'.repeat(MAXCOMPARELENGTH) + 'external tail one');
    const b = normalise('a'.repeat(MAXCOMPARELENGTH) + 'different tail two');
    assert.equal(a, b);
    assert.equal(matches(a, b), true);
});

test('normalise handles null/undefined input', () => {
    assert.equal(normalise(null), '');
    assert.equal(normalise(undefined), '');
});

test('matches requires exact equality', () => {
    assert.equal(matches('my own words', 'my own words'), true);
    assert.equal(matches('my own words', 'my own words!'), false);
});

test('one internal word must not licence an external paragraph containing it', () => {
    const internal = normalise('economy');
    const external = normalise('The economy, according to the AI tool, is best understood as follows.');
    assert.equal(matches(internal, external), false);
    assert.equal(matches(external, internal), false);
});

test('empty incoming content is never allowed', () => {
    assert.equal(matches('', ''), false);
    assert.equal(matches('something', ''), false);
    assert.equal(matches(null, ''), false);
});

test('null allowlist (no internal copy yet) never matches', () => {
    assert.equal(matches(null, 'anything'), false);
});
