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
 * Run with: node --test tiny_pasteguard/tests/js/
 * (requires Node >= 22; no dependencies. In node there is no DOMParser, so
 * the HTML path exercises the regex fallback in stripHtml.)
 *
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {test} from 'node:test';
import assert from 'node:assert/strict';
import {normalise, matches, MAXCOMPARELENGTH} from '../../amd/src/comparison.js';

test('normalise collapses whitespace runs to single spaces', () => {
    assert.equal(normalise("one\n  two\t\tthree   four"), 'one two three four');
});

test('normalise collapses non-breaking spaces (U+00A0)', () => {
    assert.equal(normalise('one two  three'), 'one two three');
});

test('normalise trims leading and trailing whitespace', () => {
    assert.equal(normalise('  padded  '), 'padded');
});

test('normalise applies NFC so composed and decomposed forms compare equal', () => {
    const composed = 'café';
    const decomposed = 'café';
    assert.equal(normalise(decomposed), normalise(composed));
});

test('normalise strips HTML markup when isHtml is set', () => {
    assert.equal(normalise('<p>one <strong>two</strong></p><ul><li>three</li></ul>', true), 'one two three');
});

test('HTML and plain-text forms of the same content normalise identically', () => {
    const plain = 'bold words and a list item';
    const html = '<p><b>bold words</b> and a <a href="https://example.com">list item</a></p>';
    assert.equal(normalise(html, true), normalise(plain));
});

test('normalise truncates both sides identically at the cap', () => {
    const long = 'a'.repeat(MAXCOMPARELENGTH + 500);
    const alsoLong = 'a'.repeat(MAXCOMPARELENGTH + 900);
    assert.equal(normalise(long).length, MAXCOMPARELENGTH);
    // Equality is preserved under symmetric truncation.
    assert.equal(normalise(long), normalise(alsoLong));
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

test('formatted internal copy matches its plain-text paste equivalent', () => {
    const copied = normalise('One  two\nthree');
    const pasted = normalise('<p>One <em>two</em></p><p>three</p>', true);
    assert.equal(matches(copied, pasted), true);
});
