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
 * Restore support for the per course module PasteGuard flag.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_pasteguard_plugin extends restore_local_plugin {
    /**
     * Declare the restore paths handled by this plugin.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element('pasteguard', $this->get_pathfor('/pasteguard')),
        ];
    }

    /**
     * Restore a PasteGuard flag against the newly restored course module.
     *
     * @param array|\stdClass $data The parsed backup data.
     * @return void
     */
    public function process_pasteguard($data) {
        $data = (object) $data;
        \local_pasteguard\api::set_for_cm((int) $this->task->get_moduleid(), (bool) $data->enabled);
    }
}
