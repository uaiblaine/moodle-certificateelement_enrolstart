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

namespace certificateelement_enrolstart;

use certificateelement_enrolstart\local\snapshot;

/**
 * Event observers of the enrolment start date element.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Freeze the enrolment start date into a certificate issue as it is created.
     *
     * tool_certificate triggers the event after it inserts the issue and before it builds the
     * PDF, and reads the issue again in between ({@see \tool_certificate\template::issue_certificate()}),
     * so what is written here is what the first PDF prints.
     *
     * @param \tool_certificate\event\certificate_issued $event The issue that was created.
     * @return void
     */
    public static function certificate_issued(\tool_certificate\event\certificate_issued $event): void {
        snapshot::capture((int) $event->objectid);
    }
}
