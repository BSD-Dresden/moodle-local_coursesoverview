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

namespace local_coursesoverview\order;

use stdClass;

/**
 * What an order leaves behind once its course is gone.
 *
 * Deleting a course takes its completions with it, and our own delete page
 * removes the accounts first. The numbers worth keeping are therefore taken
 * the moment before that happens, and the list of orders shows them in place
 * of the live ones from then on.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history {
    /**
     * How many people a course tracks and how many of them have completed it.
     *
     * Counted the way the course overview counts, so that the two pages
     * never disagree.
     *
     * @param stdClass $course
     * @return array tracked people, and completed ones or null when completion is off
     */
    public static function completion(stdClass $course): array {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($course);
        $total = (int) $completion->get_num_tracked_users();
        if (!$completion->is_enabled()) {
            return [$total, null];
        }

        $where = 'u.id IN (SELECT cc.userid
                             FROM {course_completions} cc
                            WHERE cc.course = :historycourseid
                              AND cc.timecompleted IS NOT NULL)';
        $completed = (int) $completion->get_num_tracked_users($where, ['historycourseid' => $course->id]);

        return [$total, $completed];
    }

    /**
     * Keep the final numbers of the orders behind a course about to be deleted.
     *
     * Only the first call counts. Our delete page calls this before it removes
     * the accounts, and Moodle's hook calls it again just before the course
     * goes, when nobody is left in it.
     *
     * @param stdClass $course
     */
    public static function freeze(stdClass $course): void {
        global $DB;

        $orders = $DB->get_records('local_coursesoverview_order', ['courseid' => $course->id, 'timefrozen' => 0]);
        if (!$orders) {
            return;
        }

        [$total, $completed] = self::completion($course);
        foreach ($orders as $order) {
            $DB->update_record('local_coursesoverview_order', (object) [
                'id' => $order->id,
                'timefrozen' => time(),
                'finaltotal' => $total,
                'finalcompleted' => $completed,
            ]);
        }
    }
}
