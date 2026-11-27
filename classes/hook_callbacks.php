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

namespace local_coursesoverview;

/**
 * Callbacks for the hooks of Moodle core.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Keep the final numbers of an order before its course is deleted.
     *
     * The course_deleted event comes too late for that, by then the
     * completions are gone.
     *
     * @param \core_course\hook\before_course_deleted $hook
     */
    public static function before_course_deleted(\core_course\hook\before_course_deleted $hook): void {
        order\history::freeze($hook->course);
    }

    /**
     * Keep the course content out of sight of people who only look after a course.
     *
     * Moodle cannot hide course content by role: whoever is enrolled sees the
     * sections, and access restrictions know no role condition. An organiser
     * only needs the progress block and the completion status, so for them
     * the sections on the course page and the course index are not shown.
     * This hides, it does not lock: the address of an activity still works.
     * Somebody who takes part as well, or may edit the course, sees all.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        if (during_initial_install() || !isloggedin() || isguestuser()) {
            return;
        }
        $course = $PAGE->course;
        if (empty($course->id) || $course->id == SITEID) {
            return;
        }

        $context = \context_course::instance($course->id, IGNORE_MISSING);
        if (!$context || !helper::only_organiser($context)) {
            return;
        }

        $hook->add_html(\html_writer::tag('style', '
            body[id^="page-course-view-"] .course-content,
            #theme_boost-drawers-courseindex,
            .drawer-toggler.drawer-left-toggle {
                display: none !important;
            }
            #page.drawers.show-drawer-left {
                margin-left: 0 !important;
            }
        '));
    }
}
