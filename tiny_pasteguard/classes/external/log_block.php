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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function logging a blocked paste (char count only, never content).
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_block extends external_api {
    /**
     * Describe the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Module context id where the paste was blocked'),
            'charcount' => new external_value(PARAM_INT, 'Character count of the blocked content'),
        ]);
    }

    /**
     * Log a blocked paste event.
     *
     * @param int $contextid Module context id.
     * @param int $charcount Character count of the blocked content.
     * @return array
     */
    public static function execute(int $contextid, int $charcount): array {
        ['contextid' => $contextid, 'charcount' => $charcount] = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'charcount' => $charcount]
        );

        $context = \core\context::instance_by_id($contextid);
        self::validate_context($context);

        if (!$context instanceof \core\context\module) {
            throw new \invalid_parameter_exception('Context must be a module context.');
        }

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        // Only log when the site has opted in.
        if (!get_config('tiny_pasteguard', 'logevents')) {
            return ['logged' => false];
        }

        // Only log for course modules where PasteGuard is actually enabled,
        // so arbitrary authenticated POSTs cannot pollute the logs.
        if (
            !class_exists('\local_pasteguard\api')
                || !\local_pasteguard\api::is_enabled_for_cm((int) $context->instanceid)
        ) {
            return ['logged' => false];
        }

        \tiny_pasteguard\event\paste_blocked::create([
            'context' => $context,
            'other' => ['charcount' => max(0, $charcount)],
        ])->trigger();

        return ['logged' => true];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'logged' => new external_value(PARAM_BOOL, 'Whether an event was written'),
        ]);
    }
}
