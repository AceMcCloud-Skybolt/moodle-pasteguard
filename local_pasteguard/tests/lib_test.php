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
 * Tests for the local_pasteguard course module form callbacks.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_pasteguard_coursemodule_edit_post_actions
 */
final class lib_test extends \advanced_testcase {
    /**
     * The post actions callback persists the checkbox value.
     */
    public function test_post_actions_persists_flag(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/pasteguard/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $data = (object) ['coursemodule' => $forum->cmid, 'pasteguard_enabled' => 1];
        $result = local_pasteguard_coursemodule_edit_post_actions($data, $course);
        $this->assertSame($data, $result);
        $this->assertTrue(api::is_enabled_for_cm((int) $forum->cmid));

        $data->pasteguard_enabled = 0;
        local_pasteguard_coursemodule_edit_post_actions($data, $course);
        $this->assertFalse(api::is_enabled_for_cm((int) $forum->cmid));

        // Absent field (unsupported module form) leaves state untouched.
        api::set_for_cm((int) $forum->cmid, true);
        local_pasteguard_coursemodule_edit_post_actions((object) ['coursemodule' => $forum->cmid], $course);
        $this->assertTrue(api::is_enabled_for_cm((int) $forum->cmid));
    }
}
