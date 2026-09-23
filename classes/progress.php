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
 * Completion figures, in one place.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursesoverview;

use completion_info;
use stdClass;

/**
 * The denominator everything else is measured against.
 *
 * A course's completion criteria are the same for everybody, which is what
 * makes the participants page, the Excel export and the course page block
 * agree on one figure. Counting visible activities instead would hand every
 * user a different denominator.
 */
class progress {
    /**
     * The criteria of a course, in the order the participants page uses.
     *
     * @param stdClass $course
     * @return array criteria objects, empty when completion is off
     */
    public static function criteria(stdClass $course): array {
        global $CFG;

        // completion_info is a legacy global class and is not autoloaded.
        // Leaving this out works under the web server, where something else
        // has usually pulled the file in already, and fails under cron.
        require_once($CFG->libdir . '/completionlib.php');

        $completion = new completion_info($course);

        if (!$completion->is_enabled()) {
            return [];
        }

        return helper::sorted_criteria($completion);
    }

    /**
     * The ids of a set of criteria.
     *
     * @param array $criteria as returned by criteria()
     * @return array of int
     */
    public static function criteria_ids(array $criteria): array {
        $ids = [];

        foreach ($criteria as $criterion) {
            $ids[] = (int) $criterion->id;
        }

        return $ids;
    }

    /**
     * How many of those criteria each participant has completed.
     *
     * @param int $courseid
     * @param array $criteriaids
     * @return array userid => number completed, users with none are absent
     */
    public static function completed_counts(int $courseid, array $criteriaids): array {
        global $DB;

        if (empty($criteriaids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($criteriaids, SQL_PARAMS_NAMED, 'crit');
        $params['courseid'] = $courseid;

        $sql = "SELECT ccc.userid, COUNT(ccc.id) AS numcompleted
                  FROM {course_completion_crit_compl} ccc
                 WHERE ccc.course = :courseid
                   AND ccc.timecompleted IS NOT NULL
                   AND ccc.criteriaid {$insql}
              GROUP BY ccc.userid";

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Which of those criteria one person has completed.
     *
     * @param int $courseid
     * @param int $userid
     * @param array $criteriaids
     * @return array criteriaid => time completed
     */
    public static function completed_for_user(int $courseid, int $userid, array $criteriaids): array {
        global $DB;

        if (empty($criteriaids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($criteriaids, SQL_PARAMS_NAMED, 'crit');
        $params['courseid'] = $courseid;
        $params['userid'] = $userid;

        $sql = "SELECT ccc.criteriaid, ccc.timecompleted
                  FROM {course_completion_crit_compl} ccc
                 WHERE ccc.course = :courseid
                   AND ccc.userid = :userid
                   AND ccc.timecompleted IS NOT NULL
                   AND ccc.criteriaid {$insql}";

        return $DB->get_records_sql_menu($sql, $params);
    }
}
