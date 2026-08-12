<?php
/**
 * Overview of all courses with the number of participants that completed them.
 *
 * @package local_coursesoverview
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/locallib.php');

$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'enddate', PARAM_ALPHA);
$dir = optional_param('dir', 'desc', PARAM_ALPHA);
$hide = optional_param('hide', 0, PARAM_INT);
$show = optional_param('show', 0, PARAM_INT);

$sort = local_coursesoverview_validate_sort($sort,
    ['fullname', 'startdate', 'enddate', 'completed', 'total'], 'enddate');
$descending = ($dir !== 'asc');
$dir = $descending ? 'desc' : 'asc';

admin_externalpage_setup('localcoursesoverview', '', ['sort' => $sort, 'dir' => $dir]);

$baseurl = new moodle_url('/local/coursesoverview/index.php');
$sorturl = new moodle_url($baseurl, ['sort' => $sort, 'dir' => $dir]);

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

// Fetch all courses (excluding the site course) and preload their contexts so
// that context_course::instance() below does not hit the database per course.
$ctxfields = context_helper::get_preload_record_columns_sql('ctx');
$courses = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.visible, c.startdate, c.enddate, c.enablecompletion, {$ctxfields}
          FROM {course} c
          JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
         WHERE c.id <> :siteid
      ORDER BY c.fullname ASC",
    ['contextcourse' => CONTEXT_COURSE, 'siteid' => SITEID]);

$rows = [];

foreach ($courses as $course) {
    context_helper::preload_from_record($course);
    $coursecontext = context_course::instance($course->id);

    $completion = new completion_info($course);

    // Number of users that show up in completion reports for this course.
    $totalparticipants = $completion->get_num_tracked_users();

    // Skip courses without participants.
    if ($totalparticipants < 1) {
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
        $rate = (int) round(($completedparticipants / $totalparticipants) * 100);
    } else {
        $completedparticipants = null;
        $completioncell = get_string('completiondisabled', 'local_coursesoverview');
        $rate = null;
    }

    $state = local_coursesoverview_course_state($course, $completedparticipants,
        $totalparticipants, $now);
    $status = get_string('status' . $state, 'local_coursesoverview');

    $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

    // Course actions: view, plus edit if the user may edit this course.
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
        'state' => $state,
        'cells' => [
            $coursename,
            local_coursesoverview_format_date($course->startdate),
            local_coursesoverview_format_date($course->enddate),
            $completioncell,
            $courseactions,
            $participantslink,
        ],
        'export' => [
            $course->fullname,
            $course->shortname,
            local_coursesoverview_format_date($course->startdate, ''),
            local_coursesoverview_format_date($course->enddate, ''),
            $status,
            $completedparticipants,
            $totalparticipants,
            $rate,
        ],
    ];
}

$rows = local_coursesoverview_sort_rows($rows, $sort, $descending);

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

    $colors = local_coursesoverview_colors();
    $exportrows = [];
    foreach ($rows as $row) {
        $exportrows[] = [
            'values'  => $row['export'],
            'bgcolor' => $colors[$row['state']] ?? null,
        ];
    }

    local_coursesoverview_download(
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

if (empty($rows)) {
    echo html_writer::tag('p', get_string('nocourses', 'local_coursesoverview'));
    echo $OUTPUT->footer();
    exit;
}

echo local_coursesoverview_export_button($sorturl);

$states = ['overdue', 'endingsoon', 'tosettle', 'hidden'];

echo local_coursesoverview_row_styles($states, ['hidden' => 'color: #888']);

echo local_coursesoverview_legend($states);

$table = new html_table();
$table->attributes['class'] = 'generaltable ' . LOCAL_COURSESOVERVIEW_TABLE_CLASS;
$table->head = [
    local_coursesoverview_sort_header(get_string('course'), 'fullname', $sort, $descending, $baseurl),
    local_coursesoverview_sort_header(get_string('startdate'), 'startdate', $sort, $descending, $baseurl),
    local_coursesoverview_sort_header(get_string('enddate'), 'enddate', $sort, $descending, $baseurl),
    local_coursesoverview_sort_header(get_string('completedparticipants', 'local_coursesoverview'),
        'completed', $sort, $descending, $baseurl) . ' / ' .
    local_coursesoverview_sort_header(get_string('totalparticipants', 'local_coursesoverview'),
        'total', $sort, $descending, $baseurl),
    get_string('actions'),
    get_string('completionstatus', 'local_coursesoverview'),
];

foreach ($rows as $row) {
    $tablerow = new html_table_row($row['cells']);
    $tablerow->attributes['class'] = 'coursesoverview-' . $row['state'];
    $table->data[] = $tablerow;
}

echo local_coursesoverview_start_tables();
echo html_writer::table($table);
echo local_coursesoverview_end_tables();

echo $OUTPUT->footer();
