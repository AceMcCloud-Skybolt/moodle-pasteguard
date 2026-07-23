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
 * Admin settings for tiny_pasteguard.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configcheckbox(
        'tiny_pasteguard/enabled',
        get_string('setting_enabled', 'tiny_pasteguard'),
        get_string('setting_enabled_desc', 'tiny_pasteguard'),
        1
    ));

    $modules = [];
    foreach (core_component::get_plugin_list('mod') as $modname => $unused) {
        $modules[$modname] = get_string('pluginname', 'mod_' . $modname);
    }
    core_collator::asort($modules);

    $settings->add(new admin_setting_configmulticheckbox(
        'tiny_pasteguard/supportedmodules',
        get_string('setting_supportedmodules', 'tiny_pasteguard'),
        get_string('setting_supportedmodules_desc', 'tiny_pasteguard'),
        ['assign' => 1, 'forum' => 1, 'quiz' => 1, 'lesson' => 1],
        $modules
    ));

    $settings->add(new admin_setting_configcheckbox(
        'tiny_pasteguard/logevents',
        get_string('setting_logevents', 'tiny_pasteguard'),
        get_string('setting_logevents_desc', 'tiny_pasteguard'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'tiny_pasteguard/blockmessage',
        get_string('setting_blockmessage', 'tiny_pasteguard'),
        get_string('setting_blockmessage_desc', 'tiny_pasteguard'),
        get_string('blockmessagedefault', 'tiny_pasteguard')
    ));
}
