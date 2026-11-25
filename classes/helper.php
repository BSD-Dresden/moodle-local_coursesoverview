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

use completion_info;
use context_course;
use stdClass;

/**
 * Data helpers: dates, course states, sorting.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** @var int Courses ending within this window count as "ending soon". */
    const ENDING_SOON = 14 * DAYSECS;

    /**
     * Row highlight colours, shared by the Excel export and styles.css.
     *
     * Keep these in step with styles.css, which carries the same values for
     * the on-screen tables.
     *
     * @return array state key => hex colour, or null for "no highlight"
     */
    public static function colors(): array {
        return [
            // Participants.
            'notstarted' => '#f8d7da',
            'inprogress' => '#fff3cd',
            'completed' => '#d4edda',
            // Courses.
            'overdue' => '#f5c6cb',
            'endingsoon' => '#fff3cd',
            'tosettle' => '#d4edda',
            'done' => '#e0e0e0',
            'hidden' => null,
            'deleted' => null,
            // Ended, but the completion rate is unknown: stated, not coloured.
            'past' => null,
            'active' => null,
            'upcoming' => null,
        ];
    }

    /**
     * Format a timestamp as a short numeric date in the user's timezone.
     *
     * Empty timestamps yield the placeholder rather than 01.01.1970. On screen
     * that is a dash; exports pass an empty string so the cell stays blank.
     *
     * @param int|null $timestamp
     * @param string $empty what to return when no date is set
     * @return string
     */
    public static function format_date($timestamp, string $empty = '—'): string {
        if (empty($timestamp)) {
            return $empty;
        }
        return userdate($timestamp, '%d.%m.%Y');
    }

    /**
     * A string of this plugin that states a number, in the singular for one.
     *
     * Moodle has no plural forms of its own, so a string that counts has a
     * twin with the suffix _one where "1 Personen" would otherwise appear.
     *
     * @param string $identifier the plural string
     * @param int $count the number that decides
     * @param mixed $a what the string is given, the number itself when null
     * @param string $suffix the twin to use for one
     * @return string
     */
    public static function counted(string $identifier, int $count, $a = null, string $suffix = '_one'): string {
        $component = 'local_coursesoverview';
        if ($count === 1 && get_string_manager()->string_exists($identifier . $suffix, $component)) {
            $identifier .= $suffix;
        }

        return get_string($identifier, $component, $a ?? $count);
    }

    /**
     * Read a date out of a date field.
     *
     * @param string $value as a browser sends it, "2026-10-28"
     * @return int midnight of that day, zero when the value is not a date
     */
    public static function parse_date(string $value): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $matches)) {
            return 0;
        }

        [, $year, $month, $day] = array_map('intval', $matches);
        if (!checkdate($month, $day, $year)) {
            return 0;
        }

        return make_timestamp($year, $month, $day);
    }

    /**
     * Determine the highlight state of a course row.
     *
     * Done wins over everything: marking a course done says its completions
     * have been handed on, so it must never be nagged about again. A hidden
     * course that is not done, a template for instance, is merely hidden.
     * A course that starts later and has nobody in it yet is waiting for its
     * people, whose enrolments only begin on its start day. Everybody being
     * done comes next, whether or not the course has ended:
     * that is what makes a course ready to be settled. Courses without
     * completion tracking have no known rate and are never flagged.
     *
     * The counts are compared directly rather than via a rounded percentage,
     * so that 199 of 200 participants does not round up to "everybody done".
     *
     * @param stdClass $course needs visible, startdate and enddate
     * @param int|null $completed participants that completed, null if unknown
     * @param int $total tracked participants, those who start later included
     * @param int $now
     * @param bool $done whether the course is marked as done
     * @param int $later of the total, those whose enrolment has not begun yet
     * @return string state key
     */
    public static function course_state(
        stdClass $course,
        ?int $completed,
        int $total,
        int $now,
        bool $done = false,
        int $later = 0
    ): string {
        if ($done) {
            return 'done';
        }

        if ($course->visible == 0) {
            return 'hidden';
        }

        // Nobody can be in it yet, however many are waiting for their day.
        if ($total - $later < 1 && $course->startdate > $now) {
            return 'upcoming';
        }

        if ($completed !== null && $total > 0 && $completed >= $total) {
            return 'tosettle';
        }

        $incomplete = ($completed !== null);
        $hasenddate = !empty($course->enddate);

        if ($hasenddate && $course->enddate < $now) {
            return $incomplete ? 'overdue' : 'past';
        }

        $endingsoon = $hasenddate && $course->enddate <= $now + self::ENDING_SOON;

        if ($incomplete && $endingsoon) {
            return 'endingsoon';
        }

        return 'active';
    }

    /**
     * State keys the overview can be filtered by, in dropdown order.
     *
     * "todo" is not a state a course can be in, it groups the two that mean
     * somebody has to do something.
     *
     * @return array filter key => label
     */
    public static function filter_states(): array {
        $states = ['todo', 'overdue', 'endingsoon', 'tosettle', 'upcoming', 'active', 'past', 'done', 'hidden', 'deleted'];

        $options = [];
        foreach ($states as $state) {
            $options[$state] = ($state === 'todo')
                ? get_string('statustodo', 'local_coursesoverview')
                : get_string('status' . $state, 'local_coursesoverview');
        }

        return $options;
    }

    /**
     * Does a course state pass the selected state filter?
     *
     * @param string $state state of the course
     * @param string $filter selected filter, empty for no filtering
     * @return bool
     */
    public static function state_matches(string $state, string $filter): bool {
        if ($filter === '') {
            return true;
        }
        if ($filter === 'todo') {
            return in_array($state, ['overdue', 'endingsoon'], true);
        }
        return $state === $filter;
    }

    /**
     * Return the course completion criteria, course criteria first, then
     * activity criteria, then everything else.
     *
     * @param completion_info $completion
     * @return array criteria objects, keyed as returned by completion_info
     */
    public static function sorted_criteria(completion_info $completion): array {
        $weights = [
            COMPLETION_CRITERIA_TYPE_COURSE => 0,
            COMPLETION_CRITERIA_TYPE_ACTIVITY => 1,
        ];

        $criteria = $completion->get_criteria();

        uasort($criteria, function ($a, $b) use ($weights) {
            $wa = $weights[$a->criteriatype] ?? 2;
            $wb = $weights[$b->criteriatype] ?? 2;
            if ($wa !== $wb) {
                return $wa <=> $wb;
            }
            return $a->id <=> $b->id;
        });

        return $criteria;
    }

    /**
     * Validate a requested sort column against the columns a page offers.
     *
     * @param string $sort requested column
     * @param array $allowed allowed column names
     * @param string $default fallback when the request is not allowed
     * @return string
     */
    public static function validate_sort(string $sort, array $allowed, string $default): string {
        return in_array($sort, $allowed, true) ? $sort : $default;
    }

    /**
     * Sort table rows by one of the keys in their 'sort' array.
     *
     * Strings are compared locale aware, everything else numerically.
     *
     * @param array $rows rows as built by the pages, each with a 'sort' array
     * @param string $key sort column
     * @param bool $descending
     * @return array re-indexed, sorted rows
     */
    public static function sort_rows(array $rows, string $key, bool $descending): array {
        $rows = array_values($rows);

        usort($rows, function ($a, $b) use ($key, $descending) {
            $va = $a['sort'][$key] ?? null;
            $vb = $b['sort'][$key] ?? null;

            if (is_string($va) || is_string($vb)) {
                $cmp = strcoll((string) $va, (string) $vb);
            } else {
                $cmp = $va <=> $vb;
            }

            return $descending ? -$cmp : $cmp;
        });

        return $rows;
    }

    /**
     * Participants whose enrolment begins on a later day.
     *
     * Moodle counts only enrolments that have begun, in its completion reports
     * as in completion_info::get_num_tracked_users(). People reported with a
     * start day of their own would then be missing until that day, and a
     * course could look completed while some of them have not even started.
     * These are the people with the same capability whose enrolment is
     * active apart from not having begun yet, and who have no begun one.
     *
     * @param context_course $context
     * @param int|array $groupids only these groups, 0 for everybody
     * @return int[] user id => the day their enrolment begins
     */
    public static function later_starts(context_course $context, $groupids = 0): array {
        global $DB;

        $now = time();
        $starts = $DB->get_records_sql_menu(
            "SELECT ue.userid, MIN(ue.timestart)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                    AND e.status = :enabled
                    AND ue.status = :active
                    AND ue.timestart > :now1
                    AND (ue.timeend = 0 OR ue.timeend > :now2)
                    AND NOT EXISTS (
                        SELECT 1
                          FROM {user_enrolments} ue2
                          JOIN {enrol} e2 ON e2.id = ue2.enrolid
                         WHERE e2.courseid = e.courseid
                               AND ue2.userid = ue.userid
                               AND e2.status = :enabled2
                               AND ue2.status = :active2
                               AND ue2.timestart <= :now3
                               AND (ue2.timeend = 0 OR ue2.timeend > :now4)
                    )
           GROUP BY ue.userid",
            [
                'courseid' => $context->instanceid,
                'enabled' => ENROL_INSTANCE_ENABLED,
                'active' => ENROL_USER_ACTIVE,
                'enabled2' => ENROL_INSTANCE_ENABLED,
                'active2' => ENROL_USER_ACTIVE,
                'now1' => $now,
                'now2' => $now,
                'now3' => $now,
                'now4' => $now,
            ]
        );
        if (!$starts) {
            return [];
        }

        // Only those who are tracked for completion, in the groups asked for.
        [$enrolled, $params] = get_enrolled_sql($context, 'moodle/course:isincompletionreports', $groupids, false);
        $tracked = $DB->get_fieldset_sql("SELECT je.id FROM ($enrolled) je", $params);

        return array_map('intval', array_intersect_key($starts, array_flip($tracked)));
    }

    /**
     * The groups somebody may look at in a course.
     *
     * Holding accessallgroups means the whole course. Being in groups means
     * those groups, which is what keeps one customer's departments apart
     * inside a shared course. Being in no group at all means looking after the
     * course as a whole, so everybody.
     *
     * @param context_course $context
     * @return array [group ids for get_enrolled_users, group names to display]
     */
    public static function visible_groups(context_course $context): array {
        global $USER;

        if (has_capability('moodle/site:accessallgroups', $context)) {
            return [0, []];
        }

        $groups = groups_get_all_groups($context->instanceid, $USER->id);

        if (empty($groups)) {
            return [0, []];
        }

        $ids = [];
        $names = [];

        foreach ($groups as $group) {
            $ids[] = (int) $group->id;
            $names[] = format_string($group->name, true, ['context' => $context]);
        }

        return [$ids, $names];
    }
}
