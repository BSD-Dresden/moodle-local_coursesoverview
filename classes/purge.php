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
 * Works out what deleting a course would take with it.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursesoverview;

use context_course;
use stdClass;

/**
 * Decides which enrolled users a course deletion may take with it.
 *
 * Nothing here deletes anything. It answers the question the confirmation
 * page has to show before anybody presses a button.
 */
class purge {
    /**
     * Split the enrolled users into those that would be deleted and those kept.
     *
     * Everybody enrolled is considered, organisers included. What protects an
     * account is being needed elsewhere, not the role it holds here.
     *
     * @param int $courseid
     * @param context_course $context the course context
     * @return array ['delete' => stdClass[], 'keep' => array of ['user' => stdClass, 'reason' => string]]
     */
    public static function plan(int $courseid, context_course $context): array {
        global $USER;

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $enrolled = get_enrolled_users($context, '', 0, 'u.id, u.username, u.email, ' . ltrim($namefields, ' ,'));

        if (empty($enrolled)) {
            return ['delete' => [], 'keep' => []];
        }

        $userids = array_keys($enrolled);
        $othercourses = self::other_enrolments($courseid, $userids);
        $outsideroles = self::roles_outside($context, $userids);

        $delete = [];
        $keep = [];

        foreach ($enrolled as $user) {
            $reason = null;

            if (is_siteadmin($user)) {
                $reason = get_string('keepadmin', 'local_coursesoverview');
            } else if ($user->id == $USER->id) {
                $reason = get_string('keepself', 'local_coursesoverview');
            } else if (isguestuser($user)) {
                $reason = get_string('keepguest', 'local_coursesoverview');
            } else if (!empty($outsideroles[$user->id])) {
                $reason = get_string('keeprole', 'local_coursesoverview');
            } else if (!empty($othercourses[$user->id])) {
                $reason = get_string('keepcourses', 'local_coursesoverview', $othercourses[$user->id]);
            }

            if ($reason === null) {
                $delete[] = $user;
            } else {
                $keep[] = ['user' => $user, 'reason' => $reason];
            }
        }

        return ['delete' => $delete, 'keep' => $keep];
    }

    /**
     * Number of other courses each user is enrolled in.
     *
     * Enrolment status is not considered. A suspended enrolment in a course
     * that has not been settled yet still means the account is in use, and
     * keeping one account too many is the cheaper mistake.
     *
     * @param int $courseid the course being deleted
     * @param array $userids
     * @return array userid => number of other courses
     */
    protected static function other_enrolments(int $courseid, array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['courseid'] = $courseid;

        $sql = "SELECT ue.userid, COUNT(DISTINCT e.courseid) AS othercourses
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid <> :courseid
                   AND ue.userid {$insql}
              GROUP BY ue.userid";

        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * Users holding a role outside this course.
     *
     * A role in the system or a course category marks a staff account, which
     * has no business being removed along with a training course.
     *
     * @param context_course $context
     * @param array $userids
     * @return array userid => number of role assignments outside the course
     */
    protected static function roles_outside(context_course $context, array $userids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['ctxid'] = $context->id;
        $params['ctxpath'] = $context->path . '/%';

        $sql = "SELECT ra.userid, COUNT(ra.id) AS outsideroles
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid {$insql}
                   AND ctx.id <> :ctxid
                   AND " . $DB->sql_like('ctx.path', ':ctxpath', true, true, true) . "
              GROUP BY ra.userid";

        return $DB->get_records_sql_menu($sql, $params);
    }
}
