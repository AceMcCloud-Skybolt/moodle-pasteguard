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

use context_course;
use context_module;

/**
 * Tests for the tiny_pasteguard plugin configuration.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_pasteguard\plugininfo
 */
final class plugininfo_test extends \advanced_testcase {
    /**
     * Set up a course, forum, enrolled student and default settings.
     *
     * @return array [course, forum cm context, student]
     */
    private function setup_forum(): array {
        set_config('enabled', 1, 'tiny_pasteguard');
        set_config('supportedmodules', 'assign,forum,quiz,lesson', 'tiny_pasteguard');

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $context = context_module::instance($forum->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        return [$course, $context, $student];
    }

    /**
     * Fetch the configuration for a context.
     *
     * @param \context $context The editor context.
     * @return array
     */
    private function get_config(\context $context): array {
        return plugininfo::get_plugin_configuration_for_context($context, ['pluginname' => 'pasteguard'], []);
    }

    /**
     * Active configuration is returned when every condition holds.
     */
    public function test_active_when_flag_set(): void {
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);
        $this->setUser($student);

        $config = $this->get_config($context);

        $this->assertTrue($config['active']);
        $this->assertSame((int) $context->instanceid, $config['cmid']);
        $this->assertSame($context->id, $config['contextid']);
        $this->assertNotEmpty($config['blockmessage']);
        $this->assertFalse($config['logevents']);
    }

    /**
     * No flag row (or disabled flag) means inactive.
     */
    public function test_inactive_without_flag(): void {
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        $this->setUser($student);

        $this->assertFalse($this->get_config($context)['active']);

        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, false);
        $this->assertFalse($this->get_config($context)['active']);
    }

    /**
     * Non-module contexts are always inactive.
     */
    public function test_inactive_in_course_context(): void {
        $this->resetAfterTest();
        [$course, , $student] = $this->setup_forum();
        $this->setUser($student);

        $config = $this->get_config(context_course::instance($course->id));
        $this->assertFalse($config['active']);
    }

    /**
     * A module type removed from supportedmodules deactivates existing flags.
     */
    public function test_inactive_for_unsupported_module(): void {
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);
        $this->setUser($student);

        set_config('supportedmodules', 'assign,quiz', 'tiny_pasteguard');
        $this->assertFalse($this->get_config($context)['active']);
    }

    /**
     * Site-wide disable wins over everything.
     */
    public function test_inactive_when_site_disabled(): void {
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);
        $this->setUser($student);

        set_config('enabled', 0, 'tiny_pasteguard');
        $this->assertFalse($this->get_config($context)['active']);
    }

    /**
     * The bypass capability exempts individual users.
     */
    public function test_bypass_capability(): void {
        global $DB;
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);

        $roleid = create_role('PasteGuard Exempt', 'pasteguardexempt', '');
        set_role_contextlevels($roleid, [CONTEXT_MODULE]);
        assign_capability('tiny/pasteguard:bypass', CAP_ALLOW, $roleid, $context->id);
        role_assign($roleid, $student->id, $context->id);

        $this->setUser($student);
        $this->assertFalse($this->get_config($context)['active']);

        // A different student without the role is still blocked.
        $other = $this->getDataGenerator()->create_and_enrol(
            get_course($DB->get_field('course_modules', 'course', ['id' => $context->instanceid])),
            'student'
        );
        $this->setUser($other);
        $this->assertTrue($this->get_config($context)['active']);
    }

    /**
     * A custom admin block message is passed through; empty falls back to default.
     */
    public function test_blockmessage_setting(): void {
        $this->resetAfterTest();
        [, $context, $student] = $this->setup_forum();
        \local_pasteguard\api::set_for_cm((int) $context->instanceid, true);
        $this->setUser($student);

        set_config('blockmessage', 'Custom message', 'tiny_pasteguard');
        $this->assertSame('Custom message', $this->get_config($context)['blockmessage']);

        set_config('blockmessage', '', 'tiny_pasteguard');
        $this->assertSame(
            get_string('blockmessagedefault', 'tiny_pasteguard'),
            $this->get_config($context)['blockmessage']
        );
    }
}
