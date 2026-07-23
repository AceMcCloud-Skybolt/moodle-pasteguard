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
 * Repository for the Tiny PasteGuard plugin.
 *
 * @module      tiny_pasteguard/repository
 * @copyright   2026 Murdoch Business School
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Log a blocked paste. Fire-and-forget: failures are swallowed so logging
 * can never interfere with the editing experience.
 *
 * @param {number} contextid The module context id.
 * @param {number} charcount Character count of the blocked content.
 * @returns {Promise}
 */
export const logBlock = (contextid, charcount) => Ajax.call([{
    methodname: 'tiny_pasteguard_log_block',
    args: {contextid, charcount},
}])[0].catch(() => null);
