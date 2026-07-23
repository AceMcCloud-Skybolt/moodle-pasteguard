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
 * Options helper for the Tiny PasteGuard plugin.
 *
 * @module      tiny_pasteguard/options
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {pluginName} from './common';
import {getPluginOptionName} from 'editor_tiny/options';

const activeName = getPluginOptionName(pluginName, 'active');
const blockMessageName = getPluginOptionName(pluginName, 'blockmessage');
const contextIdName = getPluginOptionName(pluginName, 'contextid');
const logEventsName = getPluginOptionName(pluginName, 'logevents');

/**
 * Register PasteGuard options with the editor.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 */
export const register = (editor) => {
    const registerOption = editor.options.register;

    registerOption(activeName, {
        processor: 'boolean',
        "default": false,
    });
    registerOption(blockMessageName, {
        processor: 'string',
        "default": '',
    });
    registerOption(contextIdName, {
        processor: 'number',
        "default": 0,
    });
    registerOption(logEventsName, {
        processor: 'boolean',
        "default": false,
    });
};

/**
 * Whether PasteGuard is active for this editor.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @returns {boolean}
 */
export const isActive = (editor) => editor.options.get(activeName);

/**
 * The student-facing block message.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @returns {string}
 */
export const getBlockMessage = (editor) => editor.options.get(blockMessageName);

/**
 * The module context id for logging.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @returns {number}
 */
export const getContextId = (editor) => editor.options.get(contextIdName);

/**
 * Whether blocked pastes should be logged.
 *
 * @param {TinyMCE.Editor} editor The editor instance.
 * @returns {boolean}
 */
export const shouldLogEvents = (editor) => editor.options.get(logEventsName);
