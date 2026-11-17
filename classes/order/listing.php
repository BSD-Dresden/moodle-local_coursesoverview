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
 * What the overview shows about orders.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class listing {
    /**
     * Every order, with the name of whoever made it.
     *
     * @return array order id => record, newest first
     */
    public static function orders(): array {
        global $DB;

        $creatorfields = \core_user\fields::for_name()->get_sql('u', false, 'creator', '', false)->selects;

        return $DB->get_records_sql(
            "SELECT o.*, {$creatorfields}
               FROM {local_coursesoverview_order} o
          LEFT JOIN {user} u ON u.id = o.usercreated
           ORDER BY o.timecreated DESC, o.id DESC"
        );
    }

    /**
     * Who each order brought in, and how their credentials went out.
     *
     * @param array $orderids
     * @return array order id => ['participants' => int, 'organisers' => int, 'delivery' => string]
     */
    public static function people(array $orderids): array {
        global $DB;

        if (!$orderids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'o');
        $rows = $DB->get_records_sql(
            "SELECT id, orderid, role, delivery, reused
               FROM {local_coursesoverview_orderuser}
              WHERE orderid {$insql}",
            $params
        );

        $people = [];
        $sent = [];
        foreach ($rows as $row) {
            $counts = $people[$row->orderid] ?? ['participants' => 0, 'organisers' => 0];
            if ($row->role === participant::ROLE_PARTICIPANT || $row->role === participant::ROLE_BOTH) {
                $counts['participants']++;
            }
            if ($row->role === participant::ROLE_ORGANISER || $row->role === participant::ROLE_BOTH) {
                $counts['organisers']++;
            }
            $people[$row->orderid] = $counts;

            // An account that was used again got no credentials from the order.
            if (!$row->reused) {
                $sent[$row->orderid][$row->delivery] = true;
            }
        }

        foreach ($orderids as $orderid) {
            $people[$orderid] = ($people[$orderid] ?? ['participants' => 0, 'organisers' => 0])
                + ['delivery' => self::delivery(array_keys($sent[$orderid] ?? []))];
        }

        return $people;
    }

    /**
     * How the credentials of an order went out, in words.
     *
     * @param array $ways the delivery keys used, for instance [provision::DELIVERY_DIRECT]
     * @return string empty when nothing was sent
     */
    public static function delivery(array $ways): string {
        $keys = [
            provision::DELIVERY_DIRECT => 'ordersdeliverymail',
            provision::DELIVERY_LIST => 'ordersdeliverylist',
        ];

        if (count($ways) > 1) {
            return get_string('ordersdeliveryboth', 'local_coursesoverview');
        }
        if ($ways && isset($keys[$ways[0]])) {
            return get_string($keys[$ways[0]], 'local_coursesoverview');
        }

        return '';
    }

    /**
     * The name of whoever made the order, empty once that account is gone.
     *
     * @param stdClass $order as returned by orders()
     * @return string
     */
    public static function creator(stdClass $order): string {
        if ($order->creatorlastname === null) {
            return '';
        }

        $name = new stdClass();
        foreach (\core_user\fields::get_name_fields() as $field) {
            $name->$field = $order->{'creator' . $field};
        }

        return fullname($name);
    }

    /**
     * The module of an order by its short name, as the settings list it.
     *
     * @param stdClass $order
     * @return string
     */
    public static function module_name(stdClass $order): string {
        $module = module::get($order->module);

        return $module ? $module->name : $order->module;
    }
}
