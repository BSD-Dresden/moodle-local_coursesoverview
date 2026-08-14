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
 * Callbacks.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the completion status of this course to the course navigation.
 *
 * This is the entry point for anyone who only holds the capability on a single
 * course and therefore never sees the overview: they reach their course the
 * usual way and find the completion status in the course menu.
 *
 * @param navigation_node $navigation the course node
 * @param stdClass $course
 * @param context_course $context
 */
function local_coursesoverview_extend_navigation_course($navigation, $course, $context) {
    if (!has_capability('local/coursesoverview:view', $context)) {
        return;
    }

    $navigation->add(
        get_string('completionstatus', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'coursesoverviewparticipants',
        new pix_icon('i/report', '')
    );
}
