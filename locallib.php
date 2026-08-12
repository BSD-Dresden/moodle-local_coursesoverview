<?php
/**
 * Shared helpers for local_coursesoverview.
 *
 * @package local_coursesoverview
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Format a timestamp as a plain date in the user's timezone/locale.
 *
 * Returns a dash for empty timestamps so that "no date set" is not rendered
 * as 01.01.1970.
 *
 * @param int|null $timestamp
 * @return string
 */
function local_coursesoverview_format_date($timestamp): string {
    if (empty($timestamp)) {
        return '—';
    }
    return userdate($timestamp, get_string('strftimedaydate', 'langconfig'));
}

/**
 * Return the course completion criteria, course criteria first, then activity
 * criteria, then everything else.
 *
 * @param completion_info $completion
 * @return array criteria objects, keyed as returned by completion_info
 */
function local_coursesoverview_get_sorted_criteria(completion_info $completion): array {
    $weights = [
        COMPLETION_CRITERIA_TYPE_COURSE   => 0,
        COMPLETION_CRITERIA_TYPE_ACTIVITY => 1,
    ];

    $criteria = $completion->get_criteria();

    uasort($criteria, function($a, $b) use ($weights) {
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
 * Sort user records by their formatted full name, locale aware.
 *
 * @param array $users
 * @return array re-indexed, sorted list
 */
function local_coursesoverview_sort_users_by_fullname(array $users): array {
    $users = array_values($users);
    usort($users, function($a, $b) {
        return strcoll(fullname($a), fullname($b));
    });
    return $users;
}
