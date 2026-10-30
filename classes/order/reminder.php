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

use local_coursesoverview\helper;
use stdClass;

/**
 * Reminders for participants of an order who have not completed its course.
 *
 * A reminder is due a number of days after somebody's enrolment, which suits
 * a course that runs all year and takes people in as they come, or a number
 * of days before the course ends, which suits a course everybody starts
 * together. The settings hold the days every order starts with, an order can
 * have its own.
 *
 * Every reminder sent is kept with the date it was counted from. A course
 * that is extended therefore starts its reminders before the end afresh,
 * and so does somebody who is enrolled again.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminder {
    /** @var string days after somebody's enrolment */
    const AFTER = 'after';
    /** @var string days before the course ends */
    const BEFORE = 'before';
    /** @var string sent by hand */
    const MANUAL = 'manual';

    /** @var string days after the enrolment, unless the settings say otherwise */
    const DEFAULT_AFTER = '';
    /** @var string days before the end, unless the settings say otherwise */
    const DEFAULT_BEFORE = '14, 3';

    /** @var int the longest stretch a reminder may be set to */
    const MAXDAYS = 999;
    /** @var int how long after its time a reminder still goes out, so that a day without cron sends nothing stale */
    const WINDOW = DAYSECS;
    /** @var int how long after any reminder nobody gets another, so that two on one day make one mail */
    const GAP = 2 * DAYSECS;

    /** @var string the step goes out with the next run */
    const STATUS_DUE = 'due';
    /** @var string the step comes later */
    const STATUS_PLANNED = 'planned';
    /** @var string the step has gone out */
    const STATUS_SENT = 'sent';
    /** @var string the step passed without a mail */
    const STATUS_MISSED = 'missed';

    /**
     * Read a list of days as people type it.
     *
     * @param string $text numbers separated by commas, semicolons or spaces
     * @return int[]|null the days in ascending order, null when something is not a day
     */
    public static function parse(string $text): ?array {
        $days = [];
        foreach (preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (!ctype_digit($part) || (int) $part < 1 || (int) $part > self::MAXDAYS) {
                return null;
            }
            $days[(int) $part] = (int) $part;
        }
        ksort($days);

        return array_values($days);
    }

    /**
     * A list of days the way it is shown.
     *
     * @param int[] $days
     * @param string $kind self::AFTER or self::BEFORE, which decides the order
     * @return string
     */
    public static function text(array $days, string $kind): string {
        if ($kind === self::BEFORE) {
            rsort($days);
        } else {
            sort($days);
        }

        return implode(', ', $days);
    }

    /**
     * The days the settings give every order of one kind.
     *
     * @param string $kind self::AFTER or self::BEFORE
     * @return int[]
     */
    public static function default_days(string $kind): array {
        $value = get_config('local_coursesoverview', 'reminder' . $kind);
        if ($value === false) {
            $value = $kind === self::BEFORE ? self::DEFAULT_BEFORE : self::DEFAULT_AFTER;
        }

        return self::parse((string) $value) ?? [];
    }

    /**
     * Whether an order follows the settings for one kind.
     *
     * @param stdClass $order
     * @param string $kind self::AFTER or self::BEFORE
     * @return bool
     */
    public static function uses_default(stdClass $order, string $kind): bool {
        return ($order->{'reminder' . $kind} ?? null) === null;
    }

    /**
     * The days an order reminds on, of one kind.
     *
     * @param stdClass $order
     * @param string $kind self::AFTER or self::BEFORE
     * @return int[]
     */
    public static function days(stdClass $order, string $kind): array {
        if (self::uses_default($order, $kind)) {
            return self::default_days($kind);
        }

        return self::parse((string) $order->{'reminder' . $kind}) ?? [];
    }

    /**
     * When a reminder a number of days away from a date goes out.
     *
     * Counted in calendar days, so that a change of daylight saving time
     * does not move it to the day before.
     *
     * @param int $base any time on the day counted from
     * @param int $days negative for days before it
     * @return int
     */
    public static function due_on(int $base, int $days): int {
        $date = usergetdate($base);

        return credentials::send_time(make_timestamp($date['year'], $date['mon'], $date['mday'] + $days, 12));
    }

    /**
     * The people of a course a reminder can concern.
     *
     * Those the course tracks for completion, as the overview counts them,
     * with when they completed it and when their enrolment began. A course
     * without completion has nobody to remind, it cannot tell who is done.
     *
     * @param stdClass $course
     * @return stdClass[] by user id: user, completed (time or zero) and joined
     */
    public static function people(stdClass $course): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/completionlib.php');

        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return [];
        }

        $tracked = $completion->get_tracked_users('', [], 0, 'u.lastname, u.firstname');
        if (!$tracked) {
            return [];
        }
        $users = $DB->get_records_list('user', 'id', array_keys($tracked));

        $completed = $DB->get_records_select_menu(
            'course_completions',
            'course = ? AND timecompleted IS NOT NULL',
            [$course->id],
            '',
            'userid, timecompleted'
        );
        $joined = self::joined((int) $course->id);

        $people = [];
        foreach (array_keys($tracked) as $userid) {
            if (empty($users[$userid])) {
                continue;
            }
            $people[$userid] = (object) [
                'user' => $users[$userid],
                'completed' => (int) ($completed[$userid] ?? 0),
                'joined' => $joined[$userid] ?? (int) $course->startdate,
            ];
        }

        return $people;
    }

    /**
     * When the enrolments in a course began.
     *
     * An enrolment begins when it was made or on its start day, whichever is
     * later. Somebody enrolled more than once counts from the earliest.
     *
     * @param int $courseid
     * @return int[] by user id
     */
    public static function joined(int $courseid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT ue.id, ue.userid, ue.timestart, ue.timecreated
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                    AND e.status = :instance
                    AND ue.status = :active",
            ['courseid' => $courseid, 'instance' => ENROL_INSTANCE_ENABLED, 'active' => ENROL_USER_ACTIVE]
        );

        $joined = [];
        foreach ($records as $record) {
            $time = max((int) $record->timestart, (int) $record->timecreated);
            $joined[$record->userid] = min($joined[$record->userid] ?? $time, $time);
        }

        return $joined;
    }

    /**
     * The reminders an order has sent, by person.
     *
     * @param int $orderid
     * @return array by user id, lists of records, the latest last
     */
    public static function sent(int $orderid): array {
        global $DB;

        $sent = [];
        $records = $DB->get_records('local_coursesoverview_reminder', ['orderid' => $orderid], 'timesent ASC, id ASC');
        foreach ($records as $record) {
            $sent[$record->userid][] = $record;
        }

        return $sent;
    }

    /**
     * Somebody's reminders in an order, sent and to come.
     *
     * A reminder before the end that would have fallen before somebody was
     * enrolled is no part of it, nor is one after the course has ended.
     *
     * @param stdClass $order
     * @param stdClass $course
     * @param int $joined when the enrolment began
     * @param stdClass[] $sent the reminders the person has had
     * @param int $now
     * @return stdClass[] kind, days, base, due, status and timesent, ordered by when they are due
     */
    public static function plan(stdClass $order, stdClass $course, int $joined, array $sent, int $now): array {
        $steps = [];
        foreach (self::days($order, self::AFTER) as $days) {
            $steps[] = (object) ['kind' => self::AFTER, 'days' => $days, 'base' => $joined, 'due' => self::due_on($joined, $days)];
        }
        if ($course->enddate) {
            foreach (self::days($order, self::BEFORE) as $days) {
                $due = self::due_on((int) $course->enddate, -$days);
                if ($due >= $joined) {
                    $steps[] = (object) [
                        'kind' => self::BEFORE,
                        'days' => $days,
                        'base' => (int) $course->enddate,
                        'due' => $due,
                    ];
                }
            }
        }

        $plan = [];
        foreach ($steps as $step) {
            // The end date names the last day of the course, which still counts.
            if ($course->enddate && $step->due >= $course->enddate + DAYSECS) {
                continue;
            }

            $step->timesent = 0;
            foreach ($sent as $record) {
                if ($record->kind === $step->kind && (int) $record->days === $step->days && (int) $record->base === $step->base) {
                    $step->timesent = (int) $record->timesent;
                }
            }

            if ($step->timesent) {
                $step->status = self::STATUS_SENT;
            } else if ($now < $step->due) {
                $step->status = self::STATUS_PLANNED;
            } else if ($now < $step->due + self::WINDOW) {
                $step->status = self::STATUS_DUE;
            } else {
                $step->status = self::STATUS_MISSED;
            }
            $plan[] = $step;
        }

        usort($plan, fn($a, $b) => $a->due <=> $b->due);

        return $plan;
    }

    /**
     * The next reminder somebody is going to get.
     *
     * @param stdClass[] $plan from plan()
     * @return int its time, zero for none
     */
    public static function next(array $plan): int {
        foreach ($plan as $step) {
            if ($step->status === self::STATUS_PLANNED || $step->status === self::STATUS_DUE) {
                return $step->due;
            }
        }

        return 0;
    }

    /**
     * Mail the reminders whose time has come.
     *
     * @param int $now
     * @return int how many went out
     */
    public static function send_due(int $now): int {
        global $DB;

        $count = 0;
        foreach ($DB->get_records_select('local_coursesoverview_order', 'courseid IS NOT NULL') as $order) {
            if (!self::days($order, self::AFTER) && !self::days($order, self::BEFORE)) {
                continue;
            }
            // A course that is over has nobody left to remind.
            $course = $DB->get_record('course', ['id' => $order->courseid]);
            if (!$course || !$course->visible || ($course->enddate && $now >= $course->enddate + DAYSECS)) {
                continue;
            }

            // Credentials still on their way would make a reminder pointless.
            $waiting = $DB->get_records_select_menu(
                'local_coursesoverview_orderuser',
                'orderid = ? AND senddate > 0 AND timesent = 0 AND reused = 0',
                [$order->id],
                '',
                'userid, id'
            );

            $sent = self::sent((int) $order->id);
            foreach (self::people($course) as $userid => $person) {
                if ($person->completed || isset($waiting[$userid]) || $person->user->suspended) {
                    continue;
                }

                $latest = isset($sent[$userid]) ? end($sent[$userid]) : null;
                if ($latest && $latest->timesent > $now - self::GAP) {
                    continue;
                }

                foreach (self::plan($order, $course, $person->joined, $sent[$userid] ?? [], $now) as $step) {
                    if ($step->status !== self::STATUS_DUE) {
                        continue;
                    }
                    if (self::send($person->user, $order, $course, $step->kind, $step->days, $step->base)) {
                        $count++;
                    }
                    // One mail a run, even when two reminders fall on one day.
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Mail somebody a reminder and keep it.
     *
     * @param stdClass $user the full user record
     * @param stdClass $order
     * @param stdClass $course
     * @param string $kind self::AFTER, self::BEFORE or self::MANUAL
     * @param int $days the days the reminder was set to, zero when sent by hand
     * @param int $base the date the days are counted from, zero when sent by hand
     * @return bool whether the mail was accepted for delivery
     */
    public static function send(stdClass $user, stdClass $order, stdClass $course, string $kind, int $days, int $base): bool {
        global $DB, $USER;

        [$subject, $html] = self::compose($user, $order, $course);
        if (!email_to_user($user, \core_user::get_support_user(), $subject, html_to_text($html), $html)) {
            return false;
        }

        $DB->insert_record('local_coursesoverview_reminder', (object) [
            'orderid' => $order->id,
            'userid' => $user->id,
            'kind' => $kind,
            'days' => $days,
            'base' => $base,
            'timesent' => time(),
            'usersent' => $kind === self::MANUAL ? (int) $USER->id : 0,
        ]);

        return true;
    }

    /**
     * The reminder as the settings word it, with its placeholders filled in.
     *
     * @param stdClass $user
     * @param stdClass $order
     * @param stdClass $course
     * @return string[] subject and body as html
     */
    public static function compose(stdClass $user, stdClass $order, stdClass $course): array {
        $subject = (string) get_config('local_coursesoverview', 'remindersubject');
        if (trim($subject) === '') {
            $subject = get_string('remindersubject_default', 'local_coursesoverview');
        }
        $body = (string) get_config('local_coursesoverview', 'reminderbody');
        if (trim(strip_tags($body)) === '') {
            $body = get_string('reminderbody_default', 'local_coursesoverview');
        }

        $daysleft = '';
        if ($course->enddate) {
            $daysleft = (string) max(0, (int) round((usergetmidnight($course->enddate) - usergetmidnight(time())) / DAYSECS));
        }

        return credentials::fill($subject, $body, $user, $order, $course, ['{daysleft}' => $daysleft]);
    }

    /**
     * A line on a reminder that has gone out, for the people's table.
     *
     * @param stdClass $record
     * @return string
     */
    public static function describe(stdClass $record): string {
        $a = (object) ['date' => helper::format_date($record->timesent), 'days' => (int) $record->days];
        if ($record->kind === self::MANUAL) {
            return get_string('reminderkindmanual', 'local_coursesoverview', $a);
        }

        return helper::counted('reminderkind' . $record->kind, (int) $record->days, $a);
    }
}
