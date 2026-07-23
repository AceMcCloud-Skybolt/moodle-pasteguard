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

import {normalise, matches} from './comparison';
import {getBlockMessage, getContextId, shouldLogEvents} from './options';
import {logBlock} from './repository';

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
 * Record an internal cut/copy in the page-scoped allowlist.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 */
const captureInternalCopy = (editor) => {
    const text = editor.selection.getContent({format: 'text'});
    state.internalClipboard = normalise(text);
};

/**
 * Decide whether incoming (already normalised) content is the student's own
 * internal copy.
 *
 * @param {string} incoming Normalised incoming text.
 * @returns {boolean}
 */
const isAllowed = (incoming) => matches(state.internalClipboard, incoming);

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
 * Primary hook: TinyMCE PastePreProcess event. Receives the incoming content
 * and cancels insertion by emptying it.
 *
 * Emptying args.content means TinyMCE inserts nothing; because the document is
 * unchanged, the undo manager records no new level. Confirmed in a real browser
 * (Chrome, Moodle 5.1): after a blocked paste the undo depth is unchanged and
 * the next undo reverts the user's own prior typing, not a phantom empty step.
 * In practice the native paste handler cancels the paste before this runs; this
 * path applies when only paste_preprocess fires.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {Object} args The PastePreProcess event ({content, internal, ...}).
 */
const handlePastePreprocess = (editor, args) => {
    // The native paste handler runs first (prepended 'paste' listener) and may
    // already have reached a verdict on this same paste. Honour it rather than
    // re-evaluating the HTML, which could overturn a plain-text allow.
    const decision = currentDecision(editor);
    if (decision === true) {
        return;
    }
    if (decision === false) {
        // Native handler already blocked and reported; just ensure nothing is
        // inserted, without a second block report.
        args.content = '';
        return;
    }

    // TinyMCE marks pastes from its own internal clipboard via the
    // 'x-tinymce/html' mime and a '<!-- x-tinymce/html -->' comment embedded in
    // text/html (tinymce.js setHtml5Clipboard / getData). That marker is
    // clipboard-scoped, not editor-scoped, so a copy from one editor pasted into
    // a different editor on the same page arrives with args.internal === true —
    // this is what carries §3 edge case 1 for rich pastes (the page-scoped
    // allowlist is the backstop for plain-text-only pastes). The marker is
    // client-side and forgeable with devtools, consistent with the plugin's
    // deterrent (not enforcement) threat model — see "Honest limitations".
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
    reportBlock(editor, charcount);
};

/**
 * Backstop: native paste event on the editor body. Catches paths where
 * paste_preprocess is not invoked. Blocked pastes are cancelled with
 * preventDefault only — propagation is not stopped, so other plugins still
 * observe the (cancelled) event.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @param {ClipboardEvent} event The native paste event.
 */
const handleNativePaste = (editor, event) => {
    const decision = currentDecision(editor);
    if (decision !== null) {
        if (!decision) {
            event.preventDefault();
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
    // PastePreProcess is a multi-listener editor event, so registering here
    // cannot clobber core or other plugins' handlers regardless of plugin load
    // order. (The older paste_preprocess option is single-slot and would need
    // manual chaining that only works if PasteGuard initialises last.)
    editor.on('PastePreProcess', (args) => handlePastePreprocess(editor, args));

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
