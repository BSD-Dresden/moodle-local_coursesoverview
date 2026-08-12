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
            'hidden' => '#e0e0e0',
            // Ended, but the completion rate is unknown: stated, not coloured.
            'past' => null,
            'active' => null,
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
     * Determine the highlight state of a course row.
     *
     * Hidden wins over everything: hiding a course is the manual marker for
     * "settled, may eventually be deleted", so it must never be nagged about.
     * Everybody being done comes next, whether or not the course has ended:
     * that is what makes a course ready to be settled. Courses without
     * completion tracking have no known rate and are never flagged.
     *
     * The counts are compared directly rather than via a rounded percentage,
     * so that 199 of 200 participants does not round up to "everybody done".
     *
     * @param stdClass $course needs visible and enddate
     * @param int|null $completed participants that completed, null if unknown
     * @param int $total tracked participants
     * @param int $now
     * @return string state key
     */
    public static function course_state(
        stdClass $course,
        ?int $completed,
        int $total,
        int $now
    ): string {
        if ($course->visible == 0) {
            return 'hidden';
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
        $states = ['todo', 'overdue', 'endingsoon', 'tosettle', 'active', 'past', 'hidden'];

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
}
