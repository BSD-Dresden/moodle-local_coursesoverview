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

use html_writer;
use local_coursesoverview\helper;
use moodle_url;
use stdClass;

/**
 * The mail with somebody's credentials, and when it goes out.
 *
 * Every mailed credential of an order goes through here, whether it leaves
 * the moment the order is made, on the morning the course starts, or when
 * somebody starts an order early by hand. Moodle's own mail for new accounts
 * cannot name the course and is shared with every other new account on the
 * site, so the accounts are created with a password nobody is told and this
 * class replaces it when its own mail is sent.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /** @var int hour the mails of a later start go out, unless the settings say otherwise */
    const DEFAULT_HOUR = 7;

    /**
     * When held credentials go out on a given day.
     *
     * In the morning rather than at midnight: a mail that arrives in the
     * middle of the night looks like spam.
     *
     * @param int $day any time on that day
     * @return int
     */
    public static function send_time(int $day): int {
        $config = get_config('local_coursesoverview');
        $hour = isset($config->ordersendhour) ? (int) $config->ordersendhour : self::DEFAULT_HOUR;
        $minute = isset($config->ordersendminute) ? (int) $config->ordersendminute : 0;

        $date = usergetdate($day);

        return make_timestamp($date['year'], $date['mon'], $date['mday'], $hour, $minute);
    }

    /**
     * Mail the credentials whose time has come.
     *
     * @param int $now
     * @param int $orderid limit to one order, zero for all of them
     * @return int how many went out
     */
    public static function send_due(int $now, int $orderid = 0): int {
        global $DB;

        $where = 'senddate > 0 AND senddate <= :now AND timesent = 0 AND reused = 0';
        $params = ['now' => $now];
        if ($orderid) {
            $where .= ' AND orderid = :orderid';
            $params['orderid'] = $orderid;
        }

        $sent = 0;
        $orders = [];
        // Participants per order whose organisers asked to hear of it.
        $told = [];

        foreach ($DB->get_records_select('local_coursesoverview_orderuser', $where, $params) as $row) {
            if (!array_key_exists($row->orderid, $orders)) {
                $orders[$row->orderid] = self::order_and_course((int) $row->orderid);
            }
            [$order, $course] = $orders[$row->orderid];

            $user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0, 'suspended' => 0]);
            if (!$user || !$course) {
                continue;
            }
            if (!self::send($user, $order, $course, self::enrolment_start((int) $course->id, (int) $user->id))) {
                continue;
            }

            $DB->set_field('local_coursesoverview_orderuser', 'timesent', time(), ['id' => $row->id]);
            $sent++;
            if (!empty($row->notify)) {
                $told[$row->orderid][] = (int) $user->id;
            }
        }

        foreach ($told as $id => $userids) {
            [$order, $course] = $orders[$id];
            notice::send($order, $course, $userids);
        }

        // An order is sent once nobody in it is waiting any more.
        foreach (array_keys($orders) as $id) {
            $waiting = $DB->record_exists_select(
                'local_coursesoverview_orderuser',
                'orderid = ? AND senddate > 0 AND timesent = 0 AND reused = 0',
                [$id]
            );
            if (!$waiting) {
                $DB->set_field('local_coursesoverview_order', 'timesent', time(), ['id' => $id]);
            }
        }

        return $sent;
    }

    /**
     * Give somebody a new password and mail it.
     *
     * @param stdClass $user the full user record
     * @param stdClass $order
     * @param stdClass $course
     * @param int $start when this person's enrolment begins, zero for the course start
     * @return bool whether the mail was accepted for delivery
     */
    public static function send(stdClass $user, stdClass $order, stdClass $course, int $start = 0): bool {
        $password = provision::password();
        update_internal_user_password($user, $password);

        // What Moodle's own route does after it mails a new password.
        unset_user_preference('create_password', $user);
        set_user_preference('auth_forcepasswordchange', 1, $user);

        [$subject, $html] = self::compose($user, $order, $course, $password, $start);

        return (bool) email_to_user($user, \core_user::get_support_user(), $subject, html_to_text($html), $html);
    }

    /**
     * The mail as the settings word it, with its placeholders filled in.
     *
     * @param stdClass $user
     * @param stdClass $order
     * @param stdClass $course
     * @param string $password
     * @param int $start when this person's enrolment begins, zero for the course start
     * @return string[] subject and body as html
     */
    public static function compose(stdClass $user, stdClass $order, stdClass $course, string $password, int $start = 0): array {
        $subject = (string) get_config('local_coursesoverview', 'ordermailsubject');
        if (trim($subject) === '') {
            $subject = get_string('ordermailsubject_default', 'local_coursesoverview');
        }
        $body = (string) get_config('local_coursesoverview', 'ordermailbody');
        if (trim(strip_tags($body)) === '') {
            $body = get_string('ordermailbody_default', 'local_coursesoverview');
        }

        $extra = ['{password}' => $password];
        // Somebody reported with a later day of their own starts then, not with the course.
        if ($start > $course->startdate) {
            $extra['{startdate}'] = helper::format_date($start, '');
        }

        return self::fill($subject, $body, $user, $order, $course, $extra);
    }

    /**
     * When somebody's enrolment in a course begins.
     *
     * @param int $courseid
     * @param int $userid
     * @return int zero when it is open from the start
     */
    protected static function enrolment_start(int $courseid, int $userid): int {
        global $DB;

        return (int) $DB->get_field_sql(
            "SELECT MAX(ue.timestart)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid AND e.enrol = 'manual' AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]
        );
    }

    /**
     * Fill in the placeholders of a mail about a course of an order.
     *
     * @param string $subject plain text
     * @param string $body html
     * @param stdClass $user
     * @param stdClass $order
     * @param stdClass $course
     * @param string[] $extra placeholders of this mail only, plain text
     * @return string[] subject and body as html
     */
    public static function fill(
        string $subject,
        string $body,
        stdClass $user,
        stdClass $order,
        stdClass $course,
        array $extra = []
    ): array {
        $module = module::get($order->module);
        $link = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $supportemail = \core_user::get_support_user()->email;

        $values = $extra + [
            '{firstname}' => $user->firstname,
            '{lastname}' => $user->lastname,
            '{username}' => $user->username,
            '{course}' => $course->fullname,
            '{module}' => $module ? $module->name : $order->module,
            '{title}' => $module ? $module->title : $order->module,
            '{company}' => $order->company,
            '{startdate}' => helper::format_date($course->startdate, ''),
            '{enddate}' => helper::format_date($course->enddate, ''),
            '{link}' => $link,
            '{sitename}' => format_string(get_site()->fullname),
            '{supportemail}' => $supportemail,
        ];

        // The body is html, so the values are escaped there and the links
        // become something to click. The subject stays plain text.
        $html = array_map('s', $values);
        $html['{link}'] = html_writer::link($link, s($link));
        $html['{supportemail}'] = html_writer::link('mailto:' . $supportemail, s($supportemail));

        return [strtr($subject, $values), strtr($body, $html)];
    }

    /**
     * When the credentials of an order that are still waiting go out.
     *
     * @param int $orderid
     * @return int[] send time => how many people, earliest first, empty when nobody is waiting
     */
    public static function schedule(int $orderid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT senddate, COUNT(id) AS people
               FROM {local_coursesoverview_orderuser}
              WHERE orderid = :orderid
                    AND senddate > 0
                    AND timesent = 0
                    AND reused = 0
           GROUP BY senddate
           ORDER BY senddate",
            ['orderid' => $orderid]
        );

        $schedule = [];
        foreach ($records as $record) {
            $schedule[(int) $record->senddate] = (int) $record->people;
        }

        return $schedule;
    }

    /**
     * Start an order now rather than on its day.
     *
     * Credentials for a course that is still closed would only cause calls,
     * so the enrolments of the order are opened at the same time. The order
     * itself starts today from then on, so that people reported later are
     * let in straight away as well.
     *
     * @param stdClass $order
     * @return int how many credentials went out
     */
    public static function release(stdClass $order): int {
        global $DB;

        $now = time();
        $today = usergetmidnight($now);
        if ($order->startdate > $now) {
            // Start days are midnights everywhere else, and a time of day
            // here would make a late report look like a later start.
            $DB->set_field('local_coursesoverview_order', 'startdate', $today, ['id' => $order->id]);
        }
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $order->courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);

        if ($plugin && $instance) {
            foreach ($DB->get_records('local_coursesoverview_orderuser', ['orderid' => $order->id]) as $row) {
                $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $row->userid]);
                if ($enrolment && $enrolment->timestart > $now) {
                    // Midnight rather than this second: Moodle rounds the time
                    // it checks enrolments against to a hundred seconds, which
                    // would keep people out for up to a minute after this.
                    $plugin->update_user_enrol($instance, $row->userid, null, $today);
                }
            }
        }

        $DB->execute(
            "UPDATE {local_coursesoverview_orderuser}
                SET senddate = :now
              WHERE orderid = :orderid
                    AND senddate > :later
                    AND timesent = 0",
            ['now' => $now, 'orderid' => $order->id, 'later' => $now]
        );

        return self::send_due($now, (int) $order->id);
    }

    /**
     * An order and its course, as far as they still exist.
     *
     * @param int $orderid
     * @return array the order and its course, either of them null
     */
    protected static function order_and_course(int $orderid): array {
        global $DB;

        $order = $DB->get_record('local_coursesoverview_order', ['id' => $orderid]);
        $course = ($order && $order->courseid) ? $DB->get_record('course', ['id' => $order->courseid]) : null;

        return [$order ?: null, $course ?: null];
    }
}
