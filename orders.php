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
 * Every order made here, including those whose course has been deleted.
 *
 * The page shows numbers only. Names and addresses go with the accounts when
 * a course is deleted, which is the point of deleting them.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursesoverview\export;
use local_coursesoverview\helper;
use local_coursesoverview\order\history;
use local_coursesoverview\order\module;
use local_coursesoverview\order\participant;
use local_coursesoverview\order\provision;
use local_coursesoverview\output;

$search = trim(optional_param('search', '', PARAM_TEXT));
$download = optional_param('download', '', PARAM_ALPHA);
$remove = optional_param('remove', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$filterparams = $search !== '' ? ['search' => $search] : [];
admin_externalpage_setup('localcoursesoverviewhistory', '', $filterparams);

$pageurl = new moodle_url('/local/coursesoverview/orders.php', $filterparams);

// Taking an order off the list, which only works once its course is gone.
// Test orders are what this is for, a real one is worth keeping.
if ($remove) {
    require_sesskey();
    $order = $DB->get_record('local_coursesoverview_order', ['id' => $remove], '*', MUST_EXIST);
    if ($order->courseid && $DB->record_exists('course', ['id' => $order->courseid])) {
        throw new moodle_exception('ordersremovecourse', 'local_coursesoverview', $pageurl->out(false));
    }

    if ($confirm) {
        $DB->delete_records('local_coursesoverview_reminder', ['orderid' => $order->id]);
        $DB->delete_records('local_coursesoverview_orderuser', ['orderid' => $order->id]);
        $DB->delete_records('local_coursesoverview_order', ['id' => $order->id]);
        redirect(
            $pageurl,
            get_string('ordersremoved', 'local_coursesoverview', s($order->shortname)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    $a = (object) ['shortname' => s($order->shortname), 'company' => s($order->company), 'abnr' => s($order->abnr)];
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('ordersremoveconfirm', 'local_coursesoverview', $a),
        new moodle_url($pageurl, ['remove' => $order->id, 'confirm' => 1, 'sesskey' => sesskey()]),
        $pageurl
    );
    echo $OUTPUT->footer();
    die();
}

$params = [];
$where = '';
if ($search !== '') {
    $like = '%' . $DB->sql_like_escape($search) . '%';
    $where = 'WHERE ' . $DB->sql_like('o.company', ':company', false)
        . ' OR ' . $DB->sql_like('o.abnr', ':abnr', false)
        . ' OR ' . $DB->sql_like('o.shortname', ':shortname', false);
    $params = ['company' => $like, 'abnr' => $like, 'shortname' => $like];
}

$creatorfields = \core_user\fields::for_name()->get_sql('u', false, 'creator', '', false)->selects;
$orders = $DB->get_records_sql(
    "SELECT o.*, {$creatorfields}
       FROM {local_coursesoverview_order} o
  LEFT JOIN {user} u ON u.id = o.usercreated
     {$where}
   ORDER BY o.timecreated DESC, o.id DESC",
    $params
);

// Who each order brought in, and how their credentials went out.
$people = [];
$deliveries = [];
if ($orders) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($orders), SQL_PARAMS_NAMED, 'o');
    $rows = $DB->get_records_sql(
        "SELECT id, orderid, role, delivery, reused
           FROM {local_coursesoverview_orderuser}
          WHERE orderid {$insql}",
        $inparams
    );
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
            $deliveries[$row->orderid][$row->delivery] = true;
        }
    }
}

$courses = [];
$courseids = array_filter(array_column($orders, 'courseid'));
if ($courseids) {
    $courses = $DB->get_records_list('course', 'id', $courseids);
}

$table = [];
foreach ($orders as $order) {
    $course = $order->courseid ? ($courses[$order->courseid] ?? null) : null;

    // Live numbers while the course is there, the kept ones afterwards.
    if ($course) {
        [$total, $completed] = history::completion($course);
    } else if ($order->timefrozen) {
        $total = (int) $order->finaltotal;
        $completed = $order->finalcompleted === null ? null : (int) $order->finalcompleted;
    } else {
        $total = null;
        $completed = null;
    }
    $completion = ($total !== null && $completed !== null) ? $completed . ' / ' . $total : '';

    // Before its start a course has nobody in it, and "0 / 0" would read as
    // an order without people.
    if ($course && !$total && $course->startdate > time()) {
        $completion = get_string('ordersstarts', 'local_coursesoverview', helper::format_date($course->startdate));
    }

    if ($course) {
        $courseurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);
        $coursecell = html_writer::link($courseurl, s($course->shortname));
        $coursetext = $course->shortname;
    } else {
        $a = (object) ['shortname' => $order->shortname, 'date' => helper::format_date($order->timefrozen)];
        $coursetext = get_string($order->timefrozen ? 'ordersdeleted' : 'ordersdeletedundated', 'local_coursesoverview', $a);
        $coursecell = html_writer::span(s($coursetext), 'text-muted');
    }

    $sent = array_keys($deliveries[$order->id] ?? []);
    sort($sent);
    $deliverykey = [
        provision::DELIVERY_DIRECT => 'ordersdeliverymail',
        provision::DELIVERY_LIST => 'ordersdeliverylist',
    ];
    if (count($sent) > 1) {
        $delivery = get_string('ordersdeliveryboth', 'local_coursesoverview');
    } else if ($sent && isset($deliverykey[$sent[0]])) {
        $delivery = get_string($deliverykey[$sent[0]], 'local_coursesoverview');
    } else {
        $delivery = '';
    }

    $module = module::get($order->module);
    $counts = $people[$order->id] ?? ['participants' => 0, 'organisers' => 0];
    // A course that was extended ends later than its order said.
    $enddate = $course ? $course->enddate : $order->enddate;
    $runtime = helper::format_date($order->startdate, '') . ' - ' . helper::format_date($enddate, '');
    // The account that made the order may be gone by now.
    $creator = '';
    if ($order->creatorlastname !== null) {
        $name = new stdClass();
        foreach (\core_user\fields::get_name_fields() as $field) {
            $name->$field = $order->{'creator' . $field};
        }
        $creator = fullname($name);
    }

    $values = [
        helper::format_date($order->timecreated, ''),
        $order->company,
        $order->abnr,
        $module ? $module->name : $order->module,
        $coursetext,
        $runtime,
        $counts['participants'],
        $counts['organisers'],
        $completion,
        $delivery,
        $creator,
    ];

    $cells = array_map('s', $values);
    $cells[4] = $coursecell;

    // Screen only, the export has no use for it.
    $cells[] = '';
    if (!$course) {
        $removeurl = new moodle_url($pageurl, ['remove' => $order->id, 'sesskey' => sesskey()]);
        $cells[count($cells) - 1] = html_writer::link($removeurl, get_string('ordersremove', 'local_coursesoverview'), [
            'class' => 'text-danger',
        ]);
    }

    $table[] = ['values' => $values, 'cells' => $cells];
}

$columns = [
    get_string('orderscreated', 'local_coursesoverview'),
    get_string('ordercompany', 'local_coursesoverview'),
    get_string('orderabnr', 'local_coursesoverview'),
    get_string('ordermodule', 'local_coursesoverview'),
    get_string('course'),
    get_string('orderruntime', 'local_coursesoverview'),
    get_string('ordersparticipants', 'local_coursesoverview'),
    get_string('ordersorganisers', 'local_coursesoverview'),
    get_string('orderscompleted', 'local_coursesoverview'),
    get_string('ordersdelivery', 'local_coursesoverview'),
    get_string('orderscreator', 'local_coursesoverview'),
];

if ($download) {
    $rows = [];
    foreach ($table as $entry) {
        $rows[] = ['values' => $entry['values'], 'bgcolor' => null];
    }
    export::download(
        $download,
        get_string('ordersfile', 'local_coursesoverview'),
        get_string('orders', 'local_coursesoverview'),
        $columns,
        $rows
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('orders', 'local_coursesoverview'));
echo html_writer::tag('p', get_string('orders_help', 'local_coursesoverview'));

$form = html_writer::label(get_string('search'), 'coursesoverview-search', true, ['class' => 'mr-2 me-2']);
$form .= html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'search',
    'id' => 'coursesoverview-search',
    'value' => $search,
    'placeholder' => get_string('orderssearch', 'local_coursesoverview'),
    'size' => 40,
    'class' => 'form-control d-inline-block w-auto mr-2 me-2',
]);
$form .= html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::tag('form', $form, [
    'method' => 'get',
    'action' => $pageurl->out_omit_querystring(),
    'class' => 'mb-3',
]);

if (!$table) {
    echo html_writer::tag('p', get_string($search !== '' ? 'ordersnomatch' : 'ordersnone', 'local_coursesoverview'));
    echo $OUTPUT->footer();
    die();
}

echo output::export_button($pageurl);

$htmltable = new html_table();
$htmltable->attributes['class'] = 'generaltable';
$htmltable->head = array_merge($columns, ['']);
foreach ($table as $entry) {
    $htmltable->data[] = $entry['cells'];
}

echo output::start_tables() . html_writer::table($htmltable) . output::end_tables();
echo $OUTPUT->footer();
