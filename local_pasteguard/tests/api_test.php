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
 * Tests for the local_pasteguard API.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_pasteguard\api
 */
final class api_test extends \advanced_testcase {
    /**
     * Enabling, disabling and reading the flag round-trips.
     */
    public function test_flag_round_trip(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $cmid = (int) $forum->cmid;

        $this->assertFalse(api::is_enabled_for_cm($cmid));

        api::set_for_cm($cmid, true);
        $this->assertTrue(api::is_enabled_for_cm($cmid));
        $this->assertEquals(1, $DB->count_records('local_pasteguard', ['cmid' => $cmid]));

        // Setting again must not create a duplicate row.
        api::set_for_cm($cmid, true);
        $this->assertEquals(1, $DB->count_records('local_pasteguard', ['cmid' => $cmid]));

        api::set_for_cm($cmid, false);
        $this->assertFalse(api::is_enabled_for_cm($cmid));

        // Disabling an unset cm must not create a row.
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        api::set_for_cm((int) $assign->cmid, false);
        $this->assertEquals(0, $DB->count_records('local_pasteguard', ['cmid' => $assign->cmid]));
    }

    /**
     * Deleting a course module removes its flag row via the event observer.
     */
    public function test_cleanup_on_course_module_deleted(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        api::set_for_cm((int) $forum->cmid, true);
        $this->assertEquals(1, $DB->count_records('local_pasteguard', ['cmid' => $forum->cmid]));

        course_delete_module($forum->cmid);

        $this->assertEquals(0, $DB->count_records('local_pasteguard', ['cmid' => $forum->cmid]));
    }

    /**
     * Module support respects the tiny_pasteguard admin settings.
     */
    public function test_is_module_supported(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'tiny_pasteguard');
        set_config('supportedmodules', 'assign,forum,quiz,lesson', 'tiny_pasteguard');

        $this->assertTrue(api::is_module_supported('forum'));
        $this->assertTrue(api::is_module_supported('assign'));
        $this->assertFalse(api::is_module_supported('wiki'));

        set_config('supportedmodules', '', 'tiny_pasteguard');
        $this->assertFalse(api::is_module_supported('forum'));

        set_config('supportedmodules', 'assign,forum,quiz,lesson', 'tiny_pasteguard');
        set_config('enabled', 0, 'tiny_pasteguard');
        $this->assertFalse(api::is_module_supported('forum'));
    }

    /**
     * The generator creates usable flag rows.
     */
    public function test_generator(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        $this->getDataGenerator()->get_plugin_generator('local_pasteguard')->create_flag(['cmid' => $forum->cmid]);
        $this->assertTrue(api::is_enabled_for_cm((int) $forum->cmid));
    }
}
