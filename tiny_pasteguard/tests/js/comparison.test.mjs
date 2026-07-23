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
 * Run with: node --test tiny_pasteguard/tests/js/comparison.test.mjs
 * (requires Node >= 22; no dependencies.)
 *
 * The HTML stripper is injected explicitly in each test rather than relying on
 * ambient DOMParser presence, so both the production (DOMParser) semantics and
 * the node regex fallback are exercised deterministically.
 *
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {
    normalise,
    matches,
    stripHtmlRegex,
    MAXCOMPARELENGTH,
} from '../../amd/src/comparison.js';

// Stand-in for the browser DOMParser path. In a real browser,
// `new DOMParser().parseFromString(html, 'text/html').body.textContent`
// concatenates text nodes with NO inserted whitespace; for markup without
// entities or scripts that equals stripping tags and inserting nothing.
// This lets the node harness drive the production semantics without jsdom.
const stripHtmlDomLike = (html) => html.replace(/<[^>]*>/g, '');

test('normalise collapses whitespace runs to single spaces', () => {
    assert.equal(normalise("one\n  two\t\tthree   four"), 'one two three four');
});

test('normalise collapses non-breaking spaces (U+00A0)', () => {
    // Build the input from escapes so the NBSP bytes cannot be lost in editing.
    const input = 'one two  three';
    assert.ok(input.includes(' '));
    assert.equal(normalise(input), 'one two three');
});

test('normalise trims leading and trailing whitespace', () => {
    assert.equal(normalise('  padded  '), 'padded');
});

test('normalise applies NFC so composed and decomposed forms compare equal', () => {
    const composed = 'café';        // é as a single code point (U+00E9).
    const decomposed = 'café';     // e + combining acute (U+0065 U+0301).
    // Guard: the two inputs must be genuinely different byte sequences,
    // otherwise this test would pass vacuously.
    assert.notEqual(composed, decomposed);
    assert.equal(normalise(decomposed), normalise(composed));
});

test('regex and DOM HTML strippers diverge on adjacent tags (pinned)', () => {
    const html = '<b>bold</b>words';
    // Regex fallback (node, non-browser): each tag becomes a space.
    assert.equal(normalise(html, true, stripHtmlRegex), 'bold words');
    // DOMParser (production, browser): textContent concatenates, no space.
    assert.equal(normalise(html, true, stripHtmlDomLike), 'boldwords');
    // The divergence is real and intended to be visible: if someone changes
    // stripHtmlRegex to also concatenate, this assertion fails and flags it.
    assert.notEqual(
        normalise(html, true, stripHtmlRegex),
        normalise(html, true, stripHtmlDomLike)
    );
});

test('DOM stripper (production) concatenates adjacent block text', () => {
    // Documents that a pasted list normalises without spaces between items
    // under production DOMParser semantics.
    const html = '<p>one <strong>two</strong></p><ul><li>three</li></ul>';
    assert.equal(normalise(html, true, stripHtmlDomLike), 'one twothree');
    // The regex fallback would instead yield 'one two three'.
    assert.equal(normalise(html, true, stripHtmlRegex), 'one two three');
});

test('HTML and plain-text forms normalise identically when spacing is explicit', () => {
    // Where the source HTML already has whitespace between text runs, both
    // strippers agree with the plain-text form.
    const plain = 'bold words and a list item';
    const html = '<p><b>bold words</b> and a <a href="https://example.com">list item</a></p>';
    assert.equal(normalise(html, true, stripHtmlDomLike), normalise(plain));
    assert.equal(normalise(html, true, stripHtmlRegex), normalise(plain));
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
    // Two distinct inputs differing only beyond the cap normalise identically.
    // This is a documented weakness of the length cap, not desired behaviour.
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
    // Security property: substring containment is not a match.
    const internal = normalise('economy');
    const external = normalise('The economy, according to the AI tool, is best understood as follows.');
    assert.equal(matches(internal, external), false);
    // Nor the reverse: internal paragraph, external word.
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
