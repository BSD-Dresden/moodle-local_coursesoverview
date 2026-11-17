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
 * Courses marked as done.
 *
 * Done means the completions have been handed on and nothing is left to do.
 * Marking a course hides it, so that the customer's people can no longer get
 * in; releasing it shows it again, for instance when the course is extended.
 * Before November 2026 hiding alone stood for this, which left no room for a
 * course that is hidden for another reason, such as a template.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class done {
    /** @var string */
    const TABLE = 'local_coursesoverview_done';

    /**
     * The done courses.
     *
     * @return array course id => record with timedone and userdone
     */
    public static function all(): array {
        global $DB;

        $records = [];
        foreach ($DB->get_records(self::TABLE) as $record) {
            $records[(int) $record->courseid] = $record;
        }

        return $records;
    }

    /**
     * Is this course done?
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_done(int $courseid): bool {
        global $DB;

        return $DB->record_exists(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Mark a course as done and hide it.
     *
     * @param int $courseid
     */
    public static function mark(int $courseid): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/lib.php');

        if (!self::is_done($courseid)) {
            $DB->insert_record(self::TABLE, (object) [
                'courseid' => $courseid,
                'timedone' => time(),
                'userdone' => $USER->id,
            ]);
        }
        course_change_visibility($courseid, false);
    }

    /**
     * Take the mark back and show the course again.
     *
     * @param int $courseid
     */
    public static function release(int $courseid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
        course_change_visibility($courseid, true);
    }

    /**
     * Forget a course that is gone.
     *
     * @param int $courseid
     */
    public static function forget(int $courseid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }
}
