<?php
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
 * Language strings for tiny_pasteguard.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['blockmessagedefault'] = 'Pasting from outside this editor is disabled for this activity. You can still cut, copy and paste text you have written here.';
$string['event_paste_blocked'] = 'External paste blocked';
$string['pasteguard:bypass'] = 'Bypass PasteGuard paste blocking';
$string['pasteguard:use'] = 'Use PasteGuard';
$string['pluginname'] = 'PasteGuard';
$string['privacy:metadata:logstore'] = 'When the site administrator enables event logging, blocked paste attempts are recorded in the standard Moodle log stores.';
$string['privacy:metadata:logstore:charcount'] = 'The number of characters in the blocked content. The content itself is never recorded.';
$string['privacy:metadata:logstore:contextid'] = 'The activity context in which the paste was blocked.';
$string['privacy:metadata:logstore:timecreated'] = 'The time the paste was blocked.';
$string['privacy:metadata:logstore:userid'] = 'The ID of the user whose paste was blocked.';
$string['setting_blockmessage'] = 'Block message';
$string['setting_blockmessage_desc'] = 'Message shown to students when a paste from outside the editor is blocked.';
$string['setting_enabled'] = 'Enable PasteGuard';
$string['setting_enabled_desc'] = 'Make PasteGuard available. When disabled, the activity-level toggle is hidden and no pastes are blocked anywhere.';
$string['setting_logevents'] = 'Log blocked pastes';
$string['setting_logevents_desc'] = 'Record an event each time a paste is blocked. Only the character count is recorded — never the pasted content.';
$string['setting_supportedmodules'] = 'Supported activity modules';
$string['setting_supportedmodules_desc'] = 'Activity modules where teachers are offered the per-activity "Block external pasting" toggle.';
