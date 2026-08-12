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
 * Overview of all courses with the number of participants that completed them.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/completionlib.php');

use local_coursesoverview\export;
use local_coursesoverview\helper;
use local_coursesoverview\output;

$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'enddate', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);

// Filters.
$search = trim(optional_param('search', '', PARAM_TEXT));
$category = optional_param('category', 0, PARAM_INT);
$state = optional_param('state', '', PARAM_ALPHA);
$withoutparticipants = optional_param('withoutparticipants', 0, PARAM_BOOL);

$sort = helper::validate_sort($sort,
    ['fullname', 'startdate', 'enddate', 'completed', 'total'], 'enddate');
$descending = ($dir !== 'asc');
$dir = $descending ? 'desc' : 'asc';

if (!array_key_exists($state, helper::filter_states())) {
    $state = '';
}

// Every filter travels in the url, so that sorting, exporting and hiding all
// keep the current view.
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
if ($withoutparticipants) {
    $filterparams['withoutparticipants'] = 1;
}

admin_externalpage_setup('localcoursesoverview', '', $filterparams);

$pageurl = new moodle_url('/local/coursesoverview/index.php');
// Base for the sort links: everything except the sorting itself.
$baseurl = new moodle_url($pageurl, array_diff_key($filterparams, ['sort' => 1, 'dir' => 1]));
// Base for actions that should return to exactly this view.
$sorturl = new moodle_url($pageurl, $filterparams);

// Toggle course visibility straight from the overview.
if ($hide || $show) {
    require_sesskey();

    $targetid = $hide ?: $show;
    if ($targetid == SITEID) {
        throw new moodle_exception('invalidcourseid', 'error');
    }

    $targetcourse = get_course($targetid);
    require_capability('moodle/course:visibility', context_course::instance($targetid));

    course_change_visibility($targetid, (bool) $show);

    redirect(
        $sorturl,
        get_string($hide ? 'coursehidden' : 'courseshown', 'local_coursesoverview',
            format_string($targetcourse->fullname, true,
                ['context' => context_course::instance($targetid)])),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$now = time();

// ---------------------------------------------------------------------------
// Fetch the courses. Name and category are filtered in SQL; the state depends
// on completion counts and can only be filtered once those are known.
// ---------------------------------------------------------------------------

$params = ['contextcourse' => CONTEXT_COURSE, 'siteid' => SITEID];
$where = ['c.id <> :siteid'];

if ($category) {
    $where[] = 'c.category = :category';
    $params['category'] = $category;
}

if ($search !== '') {
    $like = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' . $DB->sql_like('c.fullname', ':searchfull', false) . ' OR ' .
        $DB->sql_like('c.shortname', ':searchshort', false) . ')';
    $params['searchfull'] = $like;
    $params['searchshort'] = $like;
}

// Preload the course contexts so that context_course::instance() below does
// not hit the database per course.
$ctxfields = context_helper::get_preload_record_columns_sql('ctx');
$courses = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.visible, c.startdate, c.enddate, c.enablecompletion, {$ctxfields}
          FROM {course} c
          JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
         WHERE " . implode(' AND ', $where) . "
      ORDER BY c.fullname ASC", $params);

$rows = [];

foreach ($courses as $course) {
    context_helper::preload_from_record($course);
    $coursecontext = context_course::instance($course->id);

    $completion = new completion_info($course);

    // Number of users that show up in completion reports for this course.
    $totalparticipants = $completion->get_num_tracked_users();

    if ($totalparticipants < 1 && !$withoutparticipants) {
        continue;
    }

    if ($completion->is_enabled()) {
        // Count completed users with a single aggregate query instead of
        // loading every user's activity completion data.
        $completedparticipants = $completion->get_num_tracked_users(
            'u.id IN (SELECT cc.userid
                        FROM {course_completions} cc
                       WHERE cc.course = :covcourseid
                         AND cc.timecompleted IS NOT NULL)',
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

    $coursestate = helper::course_state($course, $completedparticipants,
        $totalparticipants, $now);

    if (!helper::state_matches($coursestate, $state)) {
        continue;
    }

    $status = get_string('status' . $coursestate, 'local_coursesoverview');
    $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

    // Course actions: view, plus edit and hide/show where permitted.
    $courseactions = html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('view')
    );
    if (has_capability('moodle/course:update', $coursecontext)) {
        $courseactions .= ' / ' . html_writer::link(
            new moodle_url('/course/edit.php', ['id' => $course->id]),
            get_string('edit')
        );
    }
    if (has_capability('moodle/course:visibility', $coursecontext)) {
        $action = $course->visible ? 'hide' : 'show';
        $courseactions .= ' / ' . html_writer::link(
            new moodle_url($sorturl, [$action => $course->id, 'sesskey' => sesskey()]),
            get_string($action)
        );
    }

    $participantslink = html_writer::link(
        new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
        get_string('completionstatus', 'local_coursesoverview')
    );

    $rows[] = [
        'sort' => [
            'fullname'  => $coursename,
            'startdate' => (int) $course->startdate,
            'enddate'   => (int) $course->enddate,
            'completed' => (int) $completedparticipants,
            'total'     => (int) $totalparticipants,
        ],
        'state' => $coursestate,
        'cells' => [
            $coursename,
            helper::format_date($course->startdate),
            helper::format_date($course->enddate),
            $completioncell,
            $status,
            $courseactions,
            $participantslink,
        ],
        'export' => [
            $course->fullname,
            $course->shortname,
            helper::format_date($course->startdate, ''),
            helper::format_date($course->enddate, ''),
            $status,
            $completedparticipants,
            $totalparticipants,
            $rate,
        ],
    ];
}

$rows = helper::sort_rows($rows, $sort, $descending);

// ---------------------------------------------------------------------------
// Export. Must run before any output is sent.
// ---------------------------------------------------------------------------

if ($download) {
    $columns = [
        get_string('course'),
        get_string('shortnamecourse'),
        get_string('startdate'),
        get_string('enddate'),
        get_string('status'),
        get_string('completedparticipants', 'local_coursesoverview'),
        get_string('totalparticipants', 'local_coursesoverview'),
        get_string('completionrate', 'local_coursesoverview'),
    ];

    $colors = helper::colors();
    $exportrows = [];
    foreach ($rows as $row) {
        $exportrows[] = [
            'values'  => $row['export'],
            'bgcolor' => $colors[$row['state']] ?? null,
        ];
    }

    export::download(
        $download,
        clean_filename(get_string('pluginname', 'local_coursesoverview')),
        get_string('pluginname', 'local_coursesoverview'),
        $columns,
        $exportrows
    );
}

// ---------------------------------------------------------------------------
// Output.
// ---------------------------------------------------------------------------

echo $OUTPUT->header();

echo output::filters($pageurl, [
    'sort'                => $sort,
    'dir'                 => $dir,
    'search'              => $search,
    'category'            => $category,
    'state'               => $state,
    'withoutparticipants' => $withoutparticipants,
], core_course_category::make_categories_list());

if (empty($rows)) {
    $filtered = ($search !== '' || $category || $state !== '');
    echo html_writer::tag('p', get_string(
        $filtered ? 'nocoursesmatch' : 'nocourses', 'local_coursesoverview'));
    echo $OUTPUT->footer();
    exit;
}

echo output::export_button($sorturl);
echo output::legend(['overdue', 'endingsoon', 'tosettle', 'hidden']);

$table = new html_table();
$table->attributes['class'] = 'generaltable ' . output::TABLE_CLASS;
$table->caption = get_string('pluginname', 'local_coursesoverview');
$table->captionhide = true;
$table->head = [
    output::sort_header(get_string('course'), 'fullname', $sort, $descending, $baseurl),
    output::sort_header(get_string('startdate'), 'startdate', $sort, $descending, $baseurl),
    output::sort_header(get_string('enddate'), 'enddate', $sort, $descending, $baseurl),
    output::sort_header(get_string('completedparticipants', 'local_coursesoverview'),
        'completed', $sort, $descending, $baseurl) . ' / ' .
    output::sort_header(get_string('totalparticipants', 'local_coursesoverview'),
        'total', $sort, $descending, $baseurl),
    get_string('status'),
    get_string('actions'),
    get_string('completionstatus', 'local_coursesoverview'),
];

foreach ($rows as $row) {
    $tablerow = new html_table_row($row['cells']);
    $tablerow->attributes['class'] = 'coursesoverview-' . $row['state'];
    $table->data[] = $tablerow;
}

echo output::start_tables();
echo html_writer::table($table);
echo output::end_tables();

echo $OUTPUT->footer();
