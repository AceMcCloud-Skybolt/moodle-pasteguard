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

namespace local_pasteguard;

/**
 * API helpers for reading and writing per course module PasteGuard flags.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /**
     * Is PasteGuard enabled for the given course module?
     *
     * @param int $cmid Course module id.
     * @return bool
     */
    public static function is_enabled_for_cm(int $cmid): bool {
        global $DB;

        return (bool) $DB->get_field('local_pasteguard', 'enabled', ['cmid' => $cmid]);
    }

    /**
     * Set the PasteGuard flag for a course module.
     *
     * @param int $cmid Course module id.
     * @param bool $enabled Whether PasteGuard should be active.
     * @return void
     */
    public static function set_for_cm(int $cmid, bool $enabled): void {
        global $DB;

        $existing = $DB->get_record('local_pasteguard', ['cmid' => $cmid]);
        if ($existing) {
            if ((bool) $existing->enabled !== $enabled) {
                $existing->enabled = (int) $enabled;
                $existing->timemodified = time();
                $DB->update_record('local_pasteguard', $existing);
            }
            return;
        }

        if (!$enabled) {
            // No row means disabled; avoid storing redundant rows.
            return;
        }

        $DB->insert_record('local_pasteguard', (object) [
            'cmid' => $cmid,
            'enabled' => 1,
            'timemodified' => time(),
        ]);
    }

    /**
     * Delete the flag row for a course module, if any.
     *
     * @param int $cmid Course module id.
     * @return void
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;

        $DB->delete_records('local_pasteguard', ['cmid' => $cmid]);
    }

    /**
     * Is the activity level toggle offered for this module type?
     *
     * @param string $modname Module name, e.g. 'assign'.
     * @return bool
     */
    public static function is_module_supported(string $modname): bool {
        if (!get_config('tiny_pasteguard', 'enabled')) {
            return false;
        }
        $supported = get_config('tiny_pasteguard', 'supportedmodules');
        if ($supported === false || $supported === '') {
            return false;
        }

        return in_array($modname, explode(',', $supported), true);
    }
}
