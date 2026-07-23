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

namespace tiny_pasteguard;

use context;
use context_module;
use editor_tiny\editor;
use editor_tiny\plugin;
use editor_tiny\plugin_with_configuration;

/**
 * Tiny PasteGuard plugin information.
 *
 * Blocks pasting of external content into TinyMCE in activities where a
 * teacher has enabled the flag (stored by local_pasteguard).
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugininfo extends plugin implements plugin_with_configuration {
    /**
     * Build the runtime configuration passed to the editor plugin JS.
     *
     * Returns active=false unless every activation condition holds, so
     * disabled contexts carry zero runtime cost in the browser.
     *
     * @param context $context The context that the editor is used within.
     * @param array $options The options passed in when requesting the editor.
     * @param array $fpoptions The filepicker options passed in when requesting the editor.
     * @param editor|null $editor The editor instance in which the plugin is initialised.
     * @return array
     */
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?editor $editor = null
    ): array {
        $inactive = ['active' => false];

        // The local_pasteguard plugin depends on this plugin, so Moodle cannot declare the
        // reverse dependency. Without this guard, every editor on the site would
        // fatal during the install window (or a tiny-only install) because the
        // flag table does not exist yet.
        if (!class_exists('\local_pasteguard\api')) {
            return $inactive;
        }

        if (!get_config('tiny_pasteguard', 'enabled')) {
            return $inactive;
        }

        if (!$context instanceof context_module) {
            return $inactive;
        }

        $cmid = (int) $context->instanceid;
        if (!\local_pasteguard\api::is_enabled_for_cm($cmid)) {
            return $inactive;
        }

        // Check the module type is still within the supported set.
        try {
            [, $cm] = get_course_and_cm_from_cmid($cmid);
        } catch (\moodle_exception $e) {
            return $inactive;
        }
        if (!\local_pasteguard\api::is_module_supported($cm->modname)) {
            return $inactive;
        }

        // Individual exemption, e.g. an accessibility support role.
        if (has_capability('tiny/pasteguard:bypass', $context)) {
            return $inactive;
        }

        $blockmessage = get_config('tiny_pasteguard', 'blockmessage');
        if ($blockmessage === false || trim((string) $blockmessage) === '') {
            $blockmessage = get_string('blockmessagedefault', 'tiny_pasteguard');
        }

        return [
            'active' => true,
            'blockmessage' => format_string($blockmessage),
            'cmid' => $cmid,
            'contextid' => $context->id,
            'logevents' => (bool) get_config('tiny_pasteguard', 'logevents'),
        ];
    }
}
