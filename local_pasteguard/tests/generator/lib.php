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
 * Data generator for local_pasteguard.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_pasteguard_generator extends component_generator_base {
    /**
     * Create a PasteGuard flag row for a course module.
     *
     * @param array|stdClass $record Must contain cmid; enabled defaults to 1.
     * @return stdClass The created record.
     */
    public function create_flag($record): stdClass {
        global $DB;

        $record = (object) $record;
        if (empty($record->cmid)) {
            throw new coding_exception('cmid is required for local_pasteguard flag generator');
        }
        $record->enabled = isset($record->enabled) ? (int) $record->enabled : 1;
        $record->timemodified = $record->timemodified ?? time();
        $record->id = $DB->insert_record('local_pasteguard', $record);

        return $record;
    }
}
