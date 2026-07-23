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
 * Course module form callbacks for local_pasteguard.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_pasteguard\api;

/**
 * Inject the PasteGuard checkbox into the activity settings form.
 *
 * @param moodleform_mod $formwrapper The moodleform wrapper for the module form.
 * @param MoodleQuickForm $mform The form to add elements to.
 * @return void
 */
function local_pasteguard_coursemodule_standard_elements($formwrapper, $mform): void {
    $current = $formwrapper->get_current();
    $modname = $current->modulename ?? '';
    if (!api::is_module_supported($modname)) {
        return;
    }

    $mform->addElement('header', 'pasteguard', get_string('sectionheader', 'local_pasteguard'));
    $mform->addElement('advcheckbox', 'pasteguard_enabled', get_string('enableforactivity', 'local_pasteguard'));
    $mform->addHelpButton('pasteguard_enabled', 'enableforactivity', 'local_pasteguard');
    $mform->setDefault('pasteguard_enabled', 0);

    if (!empty($current->coursemodule)) {
        $mform->setDefault('pasteguard_enabled', (int) api::is_enabled_for_cm((int) $current->coursemodule));
    }
}

/**
 * Persist the PasteGuard checkbox after the activity settings form is saved.
 *
 * @param stdClass $data Form data, including coursemodule id.
 * @param stdClass $course The course record.
 * @return stdClass The (unmodified) form data.
 */
function local_pasteguard_coursemodule_edit_post_actions($data, $course) {
    if (isset($data->pasteguard_enabled)) {
        api::set_for_cm((int) $data->coursemodule, (bool) $data->pasteguard_enabled);
    }

    return $data;
}
