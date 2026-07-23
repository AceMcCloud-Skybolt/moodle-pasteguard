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

use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup and restore tests for the PasteGuard course module flag.
 *
 * @package    local_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_pasteguard_plugin
 * @covers     \restore_local_pasteguard_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * The enabled flag travels through course backup and restore.
     */
    public function test_flag_survives_course_duplication(): void {
        global $CFG, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        // Keep the backup temp directory so the restore controller can read
        // the unpacked moodle2 structure directly.
        $CFG->keeptempdirectoriesonbackup = true;

        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        api::set_for_cm((int) $forum->cmid, true);

        // Backup the course.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id
        );
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        // Restore into a new course.
        $newcourseid = restore_dbops::create_new_course('Restored', 'RST', $course->category);
        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_SAMESITE,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $modinfo = get_fast_modinfo($newcourseid);
        $newcmids = array_keys($modinfo->get_cms());
        $this->assertCount(1, $newcmids);
        $this->assertTrue(api::is_enabled_for_cm((int) reset($newcmids)));
    }
}
