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

/**
 * Strings for the enrolment start date element.
 *
 * @package    certificateelement_enrolstart
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['dateformat'] = 'Date format';
$string['dateformat_help'] = 'How the enrolment start date is written on the certificate.

* Each option shows the same sample date, 6 July 2018, written in that format.
* The date follows the language and the time zone the certificate is generated in.

**Example**: "6 July 2018" prints the day, the month in full and the year.';
$string['pluginname'] = 'Enrolment start date';
$string['privacy:metadata'] = 'The enrolment start date element stores no data of its own. The date it prints is kept with the certificate issue, in a table of the certificate tool, and is deleted with it.';
$string['startrule'] = 'Prints the date the learner\'s enrolment in the course started: the earliest start date among their active enrolments, or the date the enrolment was created when the enrolment method records no start date. The date is fixed when the certificate is issued. Certificates issued outside a course print nothing.';
