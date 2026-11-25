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

use context_course;
use html_writer;
use stdClass;

/**
 * Tell the organisers of a course whose credentials have just gone out.
 *
 * A supervisor wants to know when their people can start. The organisers of
 * the course are those supervisors, so they hear of it without being named
 * anywhere else, when the order or late report has the box ticked. One mail per organiser and sending, listing everybody of
 * that sending, so that a late report of twenty people is one mail, not
 * twenty. An organiser in groups hears only of the people in those groups,
 * one in no group of all of them, the same rule as the completion status.
 * The mail never carries a password.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notice {
    /**
     * Tell the organisers of an order's course about the people just sent their credentials.
     *
     * @param stdClass $order
     * @param stdClass $course
     * @param int[] $userids participants whose credentials went out
     * @return int how many organisers were told
     */
    public static function send(stdClass $order, stdClass $course, array $userids): int {
        global $DB;

        if (!$userids) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids);
        $people = $DB->get_records_select('user', "id $insql AND deleted = 0", $params, 'lastname, firstname');
        if (!$people) {
            return 0;
        }

        $groups = [];
        foreach ($people as $person) {
            $groups[$person->id] = array_keys(groups_get_all_groups($course->id, $person->id));
        }

        $told = 0;
        foreach (self::organisers($course) as $organiser) {
            $own = array_keys(groups_get_all_groups($course->id, $organiser->id));
            $list = [];
            foreach ($people as $person) {
                if ((int) $person->id === (int) $organiser->id) {
                    continue;
                }
                if ($own && !array_intersect($own, $groups[$person->id])) {
                    continue;
                }
                $list[] = $person;
            }
            if (!$list) {
                continue;
            }

            [$subject, $html] = self::compose($organiser, $order, $course, $list);
            if (email_to_user($organiser, \core_user::get_support_user(), $subject, html_to_text($html), $html)) {
                $told++;
            }
        }

        return $told;
    }

    /**
     * The mail as the settings word it, with its placeholders filled in.
     *
     * @param stdClass $organiser
     * @param stdClass $order
     * @param stdClass $course
     * @param stdClass[] $people
     * @return string[] subject and body as html
     */
    public static function compose(stdClass $organiser, stdClass $order, stdClass $course, array $people): array {
        $subject = (string) get_config('local_coursesoverview', 'noticesubject');
        if (trim($subject) === '') {
            $subject = get_string('noticesubject_default', 'local_coursesoverview');
        }
        $body = (string) get_config('local_coursesoverview', 'noticebody');
        if (trim(strip_tags($body)) === '') {
            $body = get_string('noticebody_default', 'local_coursesoverview');
        }

        $items = '';
        $names = [];
        foreach ($people as $person) {
            $names[] = fullname($person);
            $items .= html_writer::tag('li', s(fullname($person)) . ' (' . s($person->email) . ')');
        }

        // The list is html of its own, so it goes in after the escaped values.
        [$subject, $html] = credentials::fill($subject, $body, $organiser, $order, $course, [
            '{count}' => (string) count($people),
            '{participants}' => '{participants}',
        ]);

        return [
            str_replace('{participants}', implode(', ', $names), $subject),
            str_replace('{participants}', html_writer::tag('ul', $items), $html),
        ];
    }

    /**
     * The organisers of a course whose enrolment is active.
     *
     * @param stdClass $course
     * @return stdClass[] full user records
     */
    protected static function organisers(stdClass $course): array {
        global $DB;

        $role = $DB->get_record('role', ['shortname' => provision::ROLE_ORGANISER]);
        if (!$role) {
            return [];
        }

        $context = context_course::instance($course->id);
        $organisers = [];
        foreach (get_role_users($role->id, $context, false, 'u.*', 'u.lastname, u.firstname') as $user) {
            if (!$user->deleted && !$user->suspended && is_enrolled($context, $user, '', true)) {
                $organisers[$user->id] = $user;
            }
        }

        return $organisers;
    }
}
