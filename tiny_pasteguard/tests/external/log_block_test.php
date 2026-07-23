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

namespace tiny_pasteguard\external;

use context_module;
use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the tiny_pasteguard_log_block external function.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_pasteguard\external\log_block
 */
final class log_block_test extends \externallib_advanced_testcase {
    /**
     * Create a forum and an enrolled student.
     *
     * @return array [module context, student]
     */
    private function setup_forum(): array {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);

        return [context_module::instance($forum->cmid), $this->getDataGenerator()->create_and_enrol($course, 'student')];
    }

    /**
     * With logging enabled, a paste_blocked event is written with the char count.
     */
    public function test_logs_event_when_enabled(): void {
        $this->resetAfterTest();
        set_config('logevents', 1, 'tiny_pasteguard');
        [$context, $student] = $this->setup_forum();
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $result = external_api::clean_returnvalue(
            log_block::execute_returns(),
            log_block::execute($context->id, 1234)
        );
        $events = $sink->get_events();
        $sink->close();

        $this->assertTrue($result['logged']);
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(\tiny_pasteguard\event\paste_blocked::class, $event);
        $this->assertSame($context->id, $event->contextid);
        $this->assertEquals($student->id, $event->userid);
        $this->assertSame(1234, $event->other['charcount']);
        // The blocked content itself must never appear in the event.
        $this->assertArrayNotHasKey('content', $event->other);
    }

    /**
     * With logging disabled, no event is written.
     */
    public function test_no_event_when_logging_disabled(): void {
        $this->resetAfterTest();
        set_config('logevents', 0, 'tiny_pasteguard');
        [$context, $student] = $this->setup_forum();
        $this->setUser($student);

        $sink = $this->redirectEvents();
        $result = external_api::clean_returnvalue(
            log_block::execute_returns(),
            log_block::execute($context->id, 10)
        );
        $this->assertFalse($result['logged']);
        $this->assertCount(0, $sink->get_events());
        $sink->close();
    }

    /**
     * A user without access to the module context is rejected.
     */
    public function test_unenrolled_user_rejected(): void {
        $this->resetAfterTest();
        set_config('logevents', 1, 'tiny_pasteguard');
        [$context] = $this->setup_forum();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\moodle_exception::class);
        log_block::execute($context->id, 10);
    }

    /**
     * Login is required.
     */
    public function test_requires_login(): void {
        $this->resetAfterTest();
        set_config('logevents', 1, 'tiny_pasteguard');
        [$context] = $this->setup_forum();
        $this->setUser(0);

        $this->expectException(\moodle_exception::class);
        log_block::execute($context->id, 10);
    }

    /**
     * Non-module contexts are rejected.
     */
    public function test_non_module_context_rejected(): void {
        $this->resetAfterTest();
        set_config('logevents', 1, 'tiny_pasteguard');
        $this->setAdminUser();

        $this->expectException(\invalid_parameter_exception::class);
        log_block::execute(\context_system::instance()->id, 10);
    }
}
