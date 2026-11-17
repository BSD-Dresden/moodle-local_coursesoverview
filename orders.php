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
 * Taking an order of a deleted course off the list.
 *
 * The list of orders itself is part of the course overview since November
 * 2026. This page is what is left of it: removing an entry, and sending old
 * bookmarks to the overview filtered by orders.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$remove = optional_param('remove', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if (!$remove) {
    redirect(new moodle_url('/local/coursesoverview/index.php', ['origin' => 'order']));
}

admin_externalpage_setup('localcoursesoverview', '', ['remove' => $remove], '/local/coursesoverview/orders.php');
require_capability('local/coursesoverview:createorder', context_system::instance());
require_sesskey();

$returnurl = new moodle_url('/local/coursesoverview/index.php', ['state' => 'deleted']);

// Only once its course is gone. Test orders are what this is for, a real one
// is worth keeping.
$order = $DB->get_record('local_coursesoverview_order', ['id' => $remove], '*', MUST_EXIST);
if ($order->courseid && $DB->record_exists('course', ['id' => $order->courseid])) {
    throw new moodle_exception('ordersremovecourse', 'local_coursesoverview', $returnurl->out(false));
}

if ($confirm) {
    $DB->delete_records('local_coursesoverview_reminder', ['orderid' => $order->id]);
    $DB->delete_records('local_coursesoverview_orderuser', ['orderid' => $order->id]);
    $DB->delete_records('local_coursesoverview_order', ['id' => $order->id]);
    redirect(
        $returnurl,
        get_string('ordersremoved', 'local_coursesoverview', s($order->shortname)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$a = (object) ['shortname' => s($order->shortname), 'company' => s($order->company), 'abnr' => s($order->abnr)];
echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('ordersremoveconfirm', 'local_coursesoverview', $a),
    new moodle_url('/local/coursesoverview/orders.php', ['remove' => $order->id, 'confirm' => 1, 'sesskey' => sesskey()]),
    $returnurl
);
echo $OUTPUT->footer();
