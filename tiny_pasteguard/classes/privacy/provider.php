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

namespace tiny_pasteguard\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for tiny_pasteguard.
 *
 * The plugin stores no user data itself; when site logging is enabled it
 * writes paste_blocked events (userid, timestamp, context, character count)
 * to the standard log stores, which handle export and deletion.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data written to the standard log stores.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('logstore', [
            'userid' => 'privacy:metadata:logstore:userid',
            'timecreated' => 'privacy:metadata:logstore:timecreated',
            'contextid' => 'privacy:metadata:logstore:contextid',
            'charcount' => 'privacy:metadata:logstore:charcount',
        ], 'privacy:metadata:logstore');

        return $collection;
    }

    /**
     * No plugin-owned tables, so no contexts to report.
     *
     * @param int $userid The user id.
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        return new \core_privacy\local\request\contextlist();
    }

    /**
     * No plugin-owned tables, so no users to add.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist.
     * @return void
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist): void {
    }

    /**
     * No plugin-owned data to export (log stores export the events).
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist): void {
    }

    /**
     * No plugin-owned data to delete.
     *
     * @param \context $context The context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
    }

    /**
     * No plugin-owned data to delete.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist The approved contexts.
     * @return void
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist): void {
    }

    /**
     * No plugin-owned data to delete.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist The approved users.
     * @return void
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist): void {
    }
}
