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
 * Privacy provider tests for tiny_pasteguard.
 *
 * @package    tiny_pasteguard
 * @copyright  2026 Murdoch Business School
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tiny_pasteguard\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * The metadata declares the logstore link and nothing else.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('tiny_pasteguard'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\external_location::class, $items[0]);
        $this->assertSame('logstore', $items[0]->get_name());
        $this->assertArrayHasKey('charcount', $items[0]->get_privacy_fields());
    }

    /**
     * No plugin-owned contexts are ever reported.
     */
    public function test_no_contexts_for_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid((int) $user->id));
    }
}
