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
 * Credentials that were held back until a course starts.
 *
 * An order that starts later creates its accounts at once, with a password
 * nobody is told, so that nothing is mailed on the day the order is made. On
 * the start day the scheduled task replaces that password and mails the new
 * one, the way local_sendcredentials does it by hand.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credentials {
    /**
     * Mail the credentials whose day has come.
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

        foreach ($DB->get_records_select('local_coursesoverview_orderuser', $where, $params) as $row) {
            $user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0, 'suspended' => 0]);
            if (!$user || !self::send($user)) {
                continue;
            }

            $DB->set_field('local_coursesoverview_orderuser', 'timesent', time(), ['id' => $row->id]);
            $orders[$row->orderid] = true;
            $sent++;
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
     * Replace the password nobody knows and mail the new one.
     *
     * The same three steps as local_sendcredentials, which in turn does what
     * the cron task does after a bulk upload. Kept here rather than borrowed,
     * so that neither plugin depends on the other.
     *
     * @param stdClass $user
     * @return bool whether the mail was accepted for delivery
     */
    public static function send(stdClass $user): bool {
        $sent = setnew_password_and_mail($user);
        unset_user_preference('create_password', $user);
        set_user_preference('auth_forcepasswordchange', 1, $user);

        return (bool) $sent;
    }

    /**
     * The people of an order whose credentials are still waiting.
     *
     * @param int $orderid
     * @return array count and the earliest day, zero when nobody is waiting
     */
    public static function waiting(int $orderid): array {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT COUNT(id) AS people, MIN(senddate) AS senddate
               FROM {local_coursesoverview_orderuser}
              WHERE orderid = :orderid
                    AND senddate > 0
                    AND timesent = 0
                    AND reused = 0",
            ['orderid' => $orderid]
        );

        return [(int) $record->people, (int) $record->senddate];
    }

    /**
     * Start an order now rather than on its day.
     *
     * Credentials for a course that is still closed would only cause calls,
     * so the enrolments of the order are opened at the same time.
     *
     * @param stdClass $order
     * @return int how many credentials went out
     */
    public static function release(stdClass $order): int {
        global $DB;

        $now = time();
        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $order->courseid, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);

        if ($plugin && $instance) {
            foreach ($DB->get_records('local_coursesoverview_orderuser', ['orderid' => $order->id]) as $row) {
                $enrolment = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $row->userid]);
                if ($enrolment && $enrolment->timestart > $now) {
                    $plugin->update_user_enrol($instance, $row->userid, null, $now);
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
}
