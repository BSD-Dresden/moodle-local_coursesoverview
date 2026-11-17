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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Certificates of attendance: all of a course as one ZIP, one person's as a PDF, or a preview of a template.
 *
 * One PDF per person who completed the course, each with their own completion
 * date. Our own business like the orders, so not for customers' organisers,
 * who see the completion status too. The preview is for whoever sets up the
 * templates and shows a sample name on a millimetre grid.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursesoverview\certificate;

$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$preview = optional_param('preview', -1, PARAM_INT);

require_login();

if ($preview >= 0) {
    require_capability('moodle/site:config', context_system::instance());
    if (!certificate::exists($preview)) {
        throw new moodle_exception('certificatenotemplate', 'local_coursesoverview');
    }
    \core\session\manager::write_close();
    certificate::send_preview($preview);
}

require_capability('local/coursesoverview:createorder', context_system::instance());

$course = get_course($courseid);
$returnurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]);

$itemid = certificate::template($courseid);
if ($itemid === null) {
    throw new moodle_exception('certificatenotemplate', 'local_coursesoverview', $returnurl->out(false));
}

$people = certificate::people($course);
if ($userid) {
    // Only somebody who completed the course has a certificate to make.
    $people = array_values(array_filter($people, function ($person) use ($userid) {
        return (int) $person->id === $userid;
    }));
}
if (!$people) {
    throw new moodle_exception('certificatenone', 'local_coursesoverview', $returnurl->out(false));
}

\core\session\manager::write_close();
if ($userid) {
    certificate::send_single($course, $itemid, $people[0]);
}
certificate::send($course, $itemid, $people);
