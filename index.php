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
 * Overview of all courses, with their orders and how many completed them.
 *
 * One row per course. A course made by an order shows how the credentials went
 * out, the export adds company, order number and module; a course without an
 * order leaves those cells empty. Search also finds company and order number. Orders whose course is gone show up under the
 * state "deleted", with the numbers kept from just before the deletion.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/completionlib.php');

use local_coursesoverview\done;
use local_coursesoverview\export;
use local_coursesoverview\helper;
use local_coursesoverview\order\listing;
use local_coursesoverview\output;

$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'enddate', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);
$markdone = optional_param('done', 0, PARAM_INT);
$release = optional_param('release', 0, PARAM_INT);

// Filters.
$search = trim(optional_param('search', '', PARAM_TEXT));
$category = optional_param('category', 0, PARAM_INT);
$state = optional_param('state', '', PARAM_ALPHA);
$origin = optional_param('origin', '', PARAM_ALPHA);
$withoutparticipants = optional_param('withoutparticipants', 0, PARAM_BOOL);

// Orders are shown to those who may make them. Someone who only may look at
// the courses gets the overview as it was before orders existed.
$canorders = has_capability('local/coursesoverview:createorder', context_system::instance());

$sortcolumns = ['fullname', 'startdate', 'enddate', 'completed', 'total'];
$sort = helper::validate_sort($sort, $sortcolumns, 'enddate');
$descending = ($dir !== 'asc');
$dir = $descending ? 'desc' : 'asc';

if (!array_key_exists($state, helper::filter_states()) || ($state === 'deleted' && !$canorders)) {
    $state = '';
}
if (!$canorders || !in_array($origin, ['order', 'none'], true)) {
    $origin = '';
}

// Every filter travels in the url, so that sorting, exporting and the actions
// all keep the current view.
$filterparams = ['sort' => $sort, 'dir' => $dir];
if ($search !== '') {
    $filterparams['search'] = $search;
}
if ($category) {
    $filterparams['category'] = $category;
}
if ($state !== '') {
    $filterparams['state'] = $state;
}
if ($origin !== '') {
    $filterparams['origin'] = $origin;
}
if ($withoutparticipants) {
    $filterparams['withoutparticipants'] = 1;
}

admin_externalpage_setup('localcoursesoverview', '', $filterparams);

$pageurl = new moodle_url('/local/coursesoverview/index.php');
// Base for the sort links: everything except the sorting itself.
$baseurl = new moodle_url($pageurl, array_diff_key($filterparams, ['sort' => 1, 'dir' => 1]));
// Base for actions that should return to exactly this view.
$sorturl = new moodle_url($pageurl, $filterparams);

// Marking a course done hides it from its people, releasing it shows it again.
if ($markdone || $release) {
    require_sesskey();

    $targetid = $markdone ?: $release;
    if ($targetid == SITEID) {
        throw new moodle_exception('invalidcourseid', 'error');
    }

    $targetcourse = get_course($targetid);
    $targetcontext = context_course::instance($targetid);
    require_capability('moodle/course:visibility', $targetcontext);

    if ($markdone) {
        done::mark($targetid);
    } else {
        done::release($targetid);
    }

    $message = get_string(
        $markdone ? 'coursedone' : 'coursereleased',
        'local_coursesoverview',
        format_string($targetcourse->fullname, true, ['context' => $targetcontext])
    );
    // Done is when the certificates go out, so they are offered right there.
    if ($markdone) {
        $certificates = \local_coursesoverview\certificate::link($targetcourse);
        if ($certificates !== '') {
            $message .= ' ' . $certificates;
        }
    }

    redirect($sorturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$now = time();

// Fetch the courses. Name, category and origin are filtered in SQL; the state
// depends on completion counts and can only be filtered once those are known.
$params = ['contextcourse' => CONTEXT_COURSE, 'siteid' => SITEID];
$where = ['c.id <> :siteid'];

if ($category) {
    $where[] = 'c.category = :category';
    $params['category'] = $category;
}

$ordercourses = "SELECT o.courseid FROM {local_coursesoverview_order} o WHERE o.courseid IS NOT NULL";

if ($search !== '') {
    $like = '%' . $DB->sql_like_escape($search) . '%';
    $matches = [
        $DB->sql_like('c.fullname', ':searchfull', false),
        $DB->sql_like('c.shortname', ':searchshort', false),
    ];
    $params['searchfull'] = $like;
    $params['searchshort'] = $like;
    if ($canorders) {
        $matches[] = "c.id IN ({$ordercourses} AND (" . $DB->sql_like('o.company', ':searchcompany', false)
            . ' OR ' . $DB->sql_like('o.abnr', ':searchabnr', false) . '))';
        $params['searchcompany'] = $like;
        $params['searchabnr'] = $like;
    }
    $where[] = '(' . implode(' OR ', $matches) . ')';
}

if ($origin === 'order') {
    $where[] = "c.id IN ({$ordercourses})";
} else if ($origin === 'none') {
    $where[] = "c.id NOT IN ({$ordercourses})";
}

// Preload the course contexts so that context_course::instance() below does
// not hit the database per course.
$ctxfields = context_helper::get_preload_record_columns_sql('ctx');
$sql = "SELECT c.id, c.fullname, c.shortname, c.visible, c.startdate, c.enddate,
               c.enablecompletion, {$ctxfields}
          FROM {course} c
          JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
         WHERE " . implode(' AND ', $where) . "
      ORDER BY c.fullname ASC";
$courses = $DB->get_records_sql($sql, $params);

// The orders, split into those whose course is there and those whose is gone.
$coursesorders = [];
$goneorders = [];
$people = [];
if ($canorders) {
    $orders = listing::orders();
    $existing = [];
    $courseids = array_filter(array_column($orders, 'courseid'));
    if ($courseids) {
        $existing = $DB->get_records_list('course', 'id', array_unique($courseids), '', 'id');
    }
    foreach ($orders as $order) {
        if ($order->courseid && isset($existing[$order->courseid])) {
            // Orders come newest first, a course keeps the newest of its orders.
            $coursesorders[$order->courseid] = $coursesorders[$order->courseid] ?? $order;
        } else {
            $goneorders[] = $order;
        }
    }
    $people = listing::people(array_keys($orders));
}

$donecourses = done::all();

$completedwhere = 'u.id IN (SELECT cc.userid
                              FROM {course_completions} cc
                             WHERE cc.course = :covcourseid
                               AND cc.timecompleted IS NOT NULL)';

$completionstatuslabel = get_string('completionstatus', 'local_coursesoverview');

// Deleting accounts is a site-wide power, so it is checked once rather than
// per course. Without it the delete link is not offered at all.
$candeletecourses = has_capability('moodle/user:delete', context_system::instance());

$rows = [];

foreach ($courses as $course) {
    context_helper::preload_from_record($course);
    $coursecontext = context_course::instance($course->id);
    $order = $coursesorders[$course->id] ?? null;
    $donerecord = $donecourses[$course->id] ?? null;

    $completion = new completion_info($course);

    // Number of users that show up in completion reports for this course.
    $totalparticipants = $completion->get_num_tracked_users();

    // A course that starts later has nobody in it yet, because enrolments
    // only begin on its start day. It is shown all the same, so that an
    // order made for a later start does not vanish until then.
    $upcoming = $course->visible && $course->startdate > $now;
    if ($totalparticipants < 1 && !$withoutparticipants && !$upcoming) {
        continue;
    }

    if ($completion->is_enabled()) {
        // Count completed users with a single aggregate query instead of
        // loading every user's activity completion data.
        $completedparticipants = $completion->get_num_tracked_users(
            $completedwhere,
            ['covcourseid' => $course->id]
        );
        $completioncell = "{$completedparticipants} / {$totalparticipants}";
        $rate = $totalparticipants
            ? (int) round(($completedparticipants / $totalparticipants) * 100)
            : null;
    } else {
        $completedparticipants = null;
        $completioncell = get_string('completiondisabled', 'local_coursesoverview');
        $rate = null;
    }

    // Before its start a course has nobody in it, and "0 / 0" would read as
    // a course without people.
    if ($upcoming && $totalparticipants < 1) {
        $completioncell = get_string('ordersstarts', 'local_coursesoverview', helper::format_date($course->startdate));
    }

    $coursestate = helper::course_state($course, $completedparticipants, $totalparticipants, $now, (bool) $donerecord);

    if (!helper::state_matches($coursestate, $state)) {
        continue;
    }

    // Courses taken over from the time when hiding meant done have no date of their own.
    $status = ($donerecord && $donerecord->userdone)
        ? get_string('statusdoneon', 'local_coursesoverview', helper::format_date($donerecord->timedone))
        : get_string('status' . $coursestate, 'local_coursesoverview');
    $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

    // Course actions: view, plus edit, done or release, and delete where permitted.
    $viewurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $courseactions = html_writer::link($viewurl, get_string('view'));

    if (has_capability('moodle/course:update', $coursecontext)) {
        $editurl = new moodle_url('/course/edit.php', ['id' => $course->id]);
        $courseactions .= ' / ' . html_writer::link($editurl, get_string('edit'));
    }

    if (has_capability('moodle/course:visibility', $coursecontext)) {
        $action = $donerecord ? 'release' : 'done';
        $actionurl = new moodle_url($sorturl, [$action => $course->id, 'sesskey' => sesskey()]);
        $courseactions .= ' / ' . html_writer::link($actionurl, get_string('action' . $action, 'local_coursesoverview'));
    }

    // Deleting is offered for done courses only, so nothing can be removed
    // that was not deliberately marked as finished. The link leads to a
    // confirmation page, it does not delete anything.
    if ($donerecord && $candeletecourses && can_delete_course($course->id)) {
        $deleteurl = new moodle_url('/local/coursesoverview/delete.php', ['courseid' => $course->id]);
        $deletelink = html_writer::link($deleteurl, get_string('delete'), ['class' => 'text-danger']);
        $courseactions .= ' / ' . $deletelink;
    }

    $participantsurl = new moodle_url('/local/coursesoverview/participants.php', [
        'courseid' => $course->id,
    ]);
    $participantslink = html_writer::link($participantsurl, $completionstatuslabel);

    $counts = $order ? $people[$order->id] : null;
    $company = $order ? $order->company : '';
    $abnr = $order ? $order->abnr : '';
    $module = $order ? listing::module_name($order) : '';
    $delivery = $counts ? $counts['delivery'] : '';

    $rows[] = [
        'sort' => [
            'fullname' => $coursename,
            'startdate' => (int) $course->startdate,
            'enddate' => (int) $course->enddate,
            'completed' => (int) $completedparticipants,
            'total' => (int) $totalparticipants,
        ],
        'state' => $coursestate,
        'cells' => [
            'course' => $coursename,
            'startdate' => helper::format_date($course->startdate),
            'enddate' => helper::format_date($course->enddate),
            'completion' => $completioncell,
            'status' => $status,
            'delivery' => s($delivery),
            'actions' => $courseactions,
            'completionstatus' => $participantslink,
        ],
        'export' => [
            'course' => $course->fullname,
            'shortname' => $course->shortname,
            'company' => $company,
            'abnr' => $abnr,
            'module' => $module,
            'startdate' => helper::format_date($course->startdate, ''),
            'enddate' => helper::format_date($course->enddate, ''),
            'status' => $status,
            'completed' => $completedparticipants,
            'total' => $totalparticipants,
            'rate' => $rate,
            'organisers' => $counts ? $counts['organisers'] : null,
            'delivery' => $delivery,
            'ordercreated' => $order ? helper::format_date($order->timecreated, '') : '',
            'creator' => $order ? listing::creator($order) : '',
            'donedate' => ($donerecord && $donerecord->userdone) ? helper::format_date($donerecord->timedone, '') : '',
        ],
    ];
}

// Orders whose course is gone, only when asked for. Category and origin do not
// apply to them: they have no category any more, and an order they all are.
if ($state === 'deleted' && !$category && $origin !== 'none') {
    $needle = core_text::strtolower($search);
    foreach ($goneorders as $order) {
        $haystack = core_text::strtolower($order->company . ' ' . $order->abnr . ' ' . $order->shortname);
        if ($needle !== '' && core_text::strpos($haystack, $needle) === false) {
            continue;
        }

        $a = (object) ['shortname' => $order->shortname, 'date' => helper::format_date($order->timefrozen)];
        $coursetext = get_string($order->timefrozen ? 'ordersdeleted' : 'ordersdeletedundated', 'local_coursesoverview', $a);

        // The numbers kept from just before the deletion.
        $total = $order->timefrozen ? (int) $order->finaltotal : null;
        $completed = ($order->timefrozen && $order->finalcompleted !== null) ? (int) $order->finalcompleted : null;
        $completioncell = ($total !== null && $completed !== null) ? $completed . ' / ' . $total : '';

        // Test orders are what removing is for, a real one is worth keeping.
        $removeurl = new moodle_url('/local/coursesoverview/orders.php', ['remove' => $order->id, 'sesskey' => sesskey()]);
        $removelink = html_writer::link($removeurl, get_string('ordersremove', 'local_coursesoverview'), [
            'class' => 'text-danger',
        ]);

        $counts = $people[$order->id];
        $module = listing::module_name($order);
        $status = get_string('statusdeleted', 'local_coursesoverview');

        $rows[] = [
            'sort' => [
                'fullname' => $coursetext,
                'startdate' => (int) $order->startdate,
                'enddate' => (int) $order->enddate,
                'completed' => (int) $completed,
                'total' => (int) $total,
            ],
            'state' => 'deleted',
            'cells' => [
                'course' => html_writer::span(s($coursetext), 'text-muted'),
                'startdate' => helper::format_date($order->startdate),
                'enddate' => helper::format_date($order->enddate),
                'completion' => $completioncell,
                'status' => $status,
                'delivery' => s($counts['delivery']),
                'actions' => $removelink,
                'completionstatus' => '',
            ],
            'export' => [
                'course' => $coursetext,
                'shortname' => $order->shortname,
                'company' => $order->company,
                'abnr' => $order->abnr,
                'module' => $module,
                'startdate' => helper::format_date($order->startdate, ''),
                'enddate' => helper::format_date($order->enddate, ''),
                'status' => $status,
                'completed' => $completed,
                'total' => $total,
                'rate' => ($total && $completed !== null) ? (int) round($completed / $total * 100) : null,
                'organisers' => $counts['organisers'],
                'delivery' => $counts['delivery'],
                'ordercreated' => helper::format_date($order->timecreated, ''),
                'creator' => listing::creator($order),
                'donedate' => '',
            ],
        ];
    }
}

$rows = helper::sort_rows($rows, $sort, $descending);

// The order columns only for those who may see orders.
$ordercolumns = ['company', 'abnr', 'module', 'delivery', 'organisers', 'ordercreated', 'creator'];
$keep = function (array $columns) use ($canorders, $ordercolumns): array {
    return $canorders ? $columns : array_diff_key($columns, array_flip($ordercolumns));
};

$completedlabel = get_string('completedparticipants', 'local_coursesoverview');
$totallabel = get_string('totalparticipants', 'local_coursesoverview');

// Export. Must run before any output is sent.
if ($download) {
    $columns = $keep([
        'course' => get_string('course'),
        'shortname' => get_string('shortnamecourse'),
        'company' => get_string('ordercompany', 'local_coursesoverview'),
        'abnr' => get_string('orderabnr', 'local_coursesoverview'),
        'module' => get_string('ordermodule', 'local_coursesoverview'),
        'startdate' => get_string('startdate'),
        'enddate' => get_string('enddate'),
        'status' => get_string('status'),
        'completed' => $completedlabel,
        'total' => $totallabel,
        'rate' => get_string('completionrate', 'local_coursesoverview'),
        'organisers' => get_string('ordersorganisers', 'local_coursesoverview'),
        'delivery' => get_string('ordersdelivery', 'local_coursesoverview'),
        'ordercreated' => get_string('orderscreated', 'local_coursesoverview'),
        'creator' => get_string('orderscreator', 'local_coursesoverview'),
        'donedate' => get_string('donedate', 'local_coursesoverview'),
    ]);

    $colors = helper::colors();
    $exportrows = [];
    foreach ($rows as $row) {
        $values = [];
        foreach (array_keys($columns) as $key) {
            $values[] = $row['export'][$key];
        }
        $exportrows[] = [
            'values' => $values,
            'bgcolor' => $colors[$row['state']] ?? null,
        ];
    }

    $exportname = get_string('overview', 'local_coursesoverview');

    export::download(
        $download,
        clean_filename($exportname),
        $exportname,
        array_values($columns),
        $exportrows
    );
}

echo $OUTPUT->header();

$filtervalues = [
    'sort' => $sort,
    'dir' => $dir,
    'search' => $search,
    'category' => $category,
    'state' => $state,
    'origin' => $origin,
    'withoutparticipants' => $withoutparticipants,
];
$origins = $canorders ? [
    'order' => get_string('originorder', 'local_coursesoverview'),
    'none' => get_string('originnone', 'local_coursesoverview'),
] : [];
echo output::filters($pageurl, $filtervalues, core_course_category::make_categories_list(), $origins);

if (empty($rows)) {
    $filtered = ($search !== '' || $category || $state !== '' || $origin !== '');
    $emptykey = $filtered ? 'nocoursesmatch' : 'nocourses';
    echo html_writer::tag('p', get_string($emptykey, 'local_coursesoverview'));
    echo $OUTPUT->footer();
    exit;
}

echo output::export_button($sorturl);
echo output::legend(['overdue', 'endingsoon', 'tosettle', 'done']);

$completedheader = output::sort_header($completedlabel, 'completed', $sort, $descending, $baseurl);
$totalheader = output::sort_header($totallabel, 'total', $sort, $descending, $baseurl);

// Company, order number and module only go to the export; on screen the
// course name tells them and the search finds them, the table stays narrow.
$head = $keep([
    'course' => output::sort_header(get_string('course'), 'fullname', $sort, $descending, $baseurl),
    'startdate' => output::sort_header(get_string('startdate'), 'startdate', $sort, $descending, $baseurl),
    'enddate' => output::sort_header(get_string('enddate'), 'enddate', $sort, $descending, $baseurl),
    'completion' => $completedheader . ' / ' . $totalheader,
    'status' => get_string('status'),
    'delivery' => get_string('ordersdelivery', 'local_coursesoverview'),
    'actions' => get_string('actions'),
    'completionstatus' => $completionstatuslabel,
]);

$table = new html_table();
$table->attributes['class'] = 'generaltable ' . output::TABLE_CLASS;
$table->caption = get_string('overview', 'local_coursesoverview');
$table->captionhide = true;
$table->head = array_values($head);

foreach ($rows as $row) {
    $cells = [];
    foreach (array_keys($head) as $key) {
        $cells[] = $row['cells'][$key];
    }
    $tablerow = new html_table_row($cells);
    $tablerow->attributes['class'] = 'coursesoverview-' . $row['state'];
    $table->data[] = $tablerow;
}

echo output::start_tables();
echo html_writer::table($table);
echo output::end_tables();

echo $OUTPUT->footer();
