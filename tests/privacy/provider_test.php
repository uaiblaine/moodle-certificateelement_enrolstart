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

namespace certificateelement_enrolstart\privacy;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the privacy provider.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \advanced_testcase {
    /**
     * The plugin counts as compliant, which plugin CI never asks: core's own sweep is outside its testsuite.
     */
    public function test_the_component_is_compliant(): void {
        $this->assertTrue((new \core_privacy\manager())->component_is_compliant('certificateelement_enrolstart'));
    }

    /**
     * The reason given for storing nothing is a string the plugin ships.
     */
    public function test_the_reason_is_a_string_of_the_plugin(): void {
        $this->assertTrue(
            get_string_manager()->string_exists(provider::get_reason(), 'certificateelement_enrolstart')
        );
    }
}
