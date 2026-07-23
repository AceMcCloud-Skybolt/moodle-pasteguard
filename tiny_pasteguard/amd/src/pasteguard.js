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
 * Paste interception for the Tiny PasteGuard plugin.
 *
 * Maintains a page-scoped allowlist of the most recent cut/copy performed
 * inside any PasteGuard-active editor on the page. A paste is permitted only
 * when its normalised plain text exactly matches that allowlist entry, so a
 * student can freely move their own text within and between protected editors
 * on the same page, but cannot bring in external content.
 *
 * @module      tiny_pasteguard/pasteguard
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getBlockMessage, getContextId, shouldLogEvents} from './options';
import {logBlock} from './repository';

// Cap comparison strings to bound memory for pathological selections. Both
// sides are truncated identically so equality is preserved.
const MAXCOMPARELENGTH = 100000;

// Minimum interval between log calls per editor.
const LOGTHROTTLEMS = 10000;

// How long an "approved"/"blocked" decision made by the primary paste handler
// remains valid for the beforeinput backstop, to avoid double handling.
const DECISIONTTLMS = 500;

// Page-scoped state, shared by every PasteGuard-active editor on the page.
const state = {
    // Normalised text of the most recent internal cut/copy, or null.
    internalClipboard: null,
    // True while a drag that started inside an active editor is in flight.
    internalDrag: false,
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
        plain = new DOMParser().parseFromString(plain, 'text/html').body.textContent || '';
    }
    // \s matches all Unicode whitespace including non-breaking spaces (U+00A0).
    return plain
        .replace(/\s+/g, ' ')
        .trim()
        .normalize('NFC')
        .substring(0, MAXCOMPARELENGTH);
};

/**
 * Record an internal cut/copy in the page-scoped allowlist.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 */
const captureInternalCopy = (editor) => {
    const text = editor.selection.getContent({format: 'text'});
    state.internalClipboard = normalise(text);
};

/**
 * Decide whether incoming content is the student's own internal copy.
 *
 * @param {string} incoming Normalised incoming text.
 * @returns {boolean}
 */
const isAllowed = (incoming) => {
    if (incoming === '') {
        // Nothing textual to compare (e.g. image-only clipboard): block, as we
        // cannot establish provenance.
        return false;
    }
    // Exact match only: substring matching would let one internal word licence
    // an external paragraph containing it.
    return state.internalClipboard !== null && incoming === state.internalClipboard;
};

/**
 * Show the block message via the TinyMCE notification manager, degrading
 * silently to blocking only if the API is unavailable.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 */
const notifyBlocked = (editor) => {
    try {
        if (editor.notificationManager) {
            editor.notificationManager.open({
                text: getBlockMessage(editor),
                type: 'warning',
                timeout: 5000,
            });
        }
    } catch (e) {
        // Never let notification failures break the editor.
    }
};

/**
 * Report a block: notification plus optional throttled logging.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {number} charcount Character count of the blocked content.
 */
const reportBlock = (editor, charcount) => {
    notifyBlocked(editor);
    if (!shouldLogEvents(editor)) {
        return;
    }
    const now = Date.now();
    if (editor.pasteguardLastLog && (now - editor.pasteguardLastLog) < LOGTHROTTLEMS) {
        return;
    }
    editor.pasteguardLastLog = now;
    logBlock(getContextId(editor), charcount);
};

/**
 * Record the decision made by a primary handler so the beforeinput backstop
 * does not re-evaluate (and potentially re-block) the same insertion.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {boolean} allowed Whether the paste was allowed.
 */
const recordDecision = (editor, allowed) => {
    editor.pasteguardDecision = {allowed, time: Date.now()};
};

/**
 * Get a still-valid decision from a primary handler, if any.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @returns {boolean|null} The decision, or null if none is current.
 */
const currentDecision = (editor) => {
    const decision = editor.pasteguardDecision;
    if (decision && (Date.now() - decision.time) < DECISIONTTLMS) {
        return decision.allowed;
    }
    return null;
};

/**
 * Primary hook: TinyMCE paste_preprocess. Receives the incoming content and
 * can cancel insertion by emptying it.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {Object} args The paste_preprocess arguments ({content, internal, ...}).
 */
const handlePastePreprocess = (editor, args) => {
    // TinyMCE marks pastes originating from its own internal clipboard.
    if (args.internal) {
        recordDecision(editor, true);
        return;
    }
    const incoming = normalise(args.content, true);
    if (isAllowed(incoming)) {
        recordDecision(editor, true);
        return;
    }
    recordDecision(editor, false);
    const charcount = incoming.length || String(args.content || '').length;
    args.content = '';
    // A cancelled paste must not leave an undo step or placeholder.
    args.preventDefault?.();
    reportBlock(editor, charcount);
};

/**
 * Backstop: native paste event on the editor body. Catches paths where
 * paste_preprocess is not invoked.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {ClipboardEvent} event The native paste event.
 */
const handleNativePaste = (editor, event) => {
    const decision = currentDecision(editor);
    if (decision !== null) {
        if (!decision) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
        return;
    }
    const text = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
    const incoming = normalise(text);
    if (isAllowed(incoming)) {
        recordDecision(editor, true);
        return;
    }
    recordDecision(editor, false);
    event.preventDefault();
    event.stopImmediatePropagation();
    reportBlock(editor, incoming.length);
};

/**
 * Drag and drop: text dropped into the editor bypasses paste events. Only
 * drags that started inside a PasteGuard-active editor on this page are
 * permitted.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {DragEvent} event The drop event.
 */
const handleDrop = (editor, event) => {
    if (state.internalDrag) {
        return;
    }
    event.preventDefault();
    let charcount = 0;
    try {
        charcount = normalise(event.dataTransfer?.getData('text/plain') || '').length;
    } catch (e) {
        // Inaccessible dataTransfer: still block, count unknown.
    }
    reportBlock(editor, charcount);
};

/**
 * beforeinput backstop for IME/browser paths that skip classic paste events.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {InputEvent} event The beforeinput event.
 */
const handleBeforeInput = (editor, event) => {
    if (event.inputType !== 'insertFromPaste' && event.inputType !== 'insertFromDrop') {
        return;
    }
    if (event.inputType === 'insertFromDrop' && state.internalDrag) {
        return;
    }
    const decision = currentDecision(editor);
    if (decision === true) {
        return;
    }
    if (decision === false) {
        event.preventDefault();
        return;
    }
    // No primary handler saw this insertion: evaluate here.
    let incoming = '';
    if (event.dataTransfer) {
        incoming = normalise(event.dataTransfer.getData('text/plain'));
    } else if (typeof event.data === 'string') {
        incoming = normalise(event.data);
    }
    if (isAllowed(incoming)) {
        return;
    }
    event.preventDefault();
    reportBlock(editor, incoming.length);
};

/**
 * Set up PasteGuard interception on an active editor.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 */
export const setup = (editor) => {
    // Cancel external pastes before TinyMCE inserts them.
    editor.options.set('paste_preprocess', (unused, args) => handlePastePreprocess(editor, args));

    editor.on('cut', () => captureInternalCopy(editor));
    editor.on('copy', () => captureInternalCopy(editor));

    // Track drags originating in this (or any active) editor so internal
    // drag-and-drop keeps working while external drops are blocked.
    editor.on('dragstart', () => {
        state.internalDrag = true;
    });
    editor.on('dragend', () => {
        state.internalDrag = false;
    });
    editor.on('drop', (event) => {
        handleDrop(editor, event);
        // Reset after the drop completes, whatever its origin.
        setTimeout(() => {
            state.internalDrag = false;
        });
    });

    editor.on('paste', (event) => handleNativePaste(editor, event), true);

    editor.on('init', () => {
        // Native backstop for paths that bypass TinyMCE's paste pipeline.
        // Programmatic setContent (e.g. autosave draft restore) never raises
        // these input types, so drafts are unaffected.
        editor.getBody().addEventListener('beforeinput', (event) => handleBeforeInput(editor, event));
    });
};
