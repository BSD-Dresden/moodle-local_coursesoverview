<?php
/**
 * Completion status of all participants of a single course.
 *
 * @package local_coursesoverview
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once(__DIR__ . '/locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'lastname', PARAM_ALPHA);
$dir = optional_param('dir', 'asc', PARAM_ALPHA);

$sort = local_coursesoverview_validate_sort($sort,
    ['firstname', 'lastname', 'email', 'progress', 'date'], 'lastname');
$descending = ($dir === 'desc');

require_login();

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/coursesoverview:view', $context);

$baseurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]);
$sorturl = new moodle_url($baseurl, ['sort' => $sort, 'dir' => $descending ? 'desc' : 'asc']);

$PAGE->set_course($course);
$PAGE->set_url($sorturl);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('completionstatus', 'local_coursesoverview'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$completion = new completion_info($course);
$criteria = local_coursesoverview_get_sorted_criteria($completion);
$numcriteria = count($criteria);

// ---------------------------------------------------------------------------
// Gather all completion data up front: a handful of queries for the whole page
// instead of one query per user and criterion.
// ---------------------------------------------------------------------------

$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$participants = get_enrolled_users($context, '', 0, 'u.id, u.email, ' . ltrim($userfields, ' ,'));

// Number of completed criteria per user.
$numcompletedbyuser = [];
if ($numcriteria) {
    $criteriaids = [];
    foreach ($criteria as $criterion) {
        $criteriaids[] = $criterion->id;
    }
    list($insql, $inparams) = $DB->get_in_or_equal($criteriaids, SQL_PARAMS_NAMED, 'crit');
    $numcompletedbyuser = $DB->get_records_sql_menu("
            SELECT ccc.userid, COUNT(ccc.id) AS numcompleted
              FROM {course_completion_crit_compl} ccc
             WHERE ccc.course = :courseid
               AND ccc.timecompleted IS NOT NULL
               AND ccc.criteriaid {$insql}
          GROUP BY ccc.userid",
        ['courseid' => $courseid] + $inparams);
}

// Actual course completion time per user.
$coursecompletedbyuser = $DB->get_records_sql_menu("
        SELECT cc.userid, cc.timecompleted
          FROM {course_completions} cc
         WHERE cc.course = :courseid
           AND cc.timecompleted IS NOT NULL",
    ['courseid' => $courseid]);

// Last access to this course per user.
$lastaccessbyuser = $DB->get_records_sql_menu("
        SELECT ul.userid, ul.timeaccess
          FROM {user_lastaccess} ul
         WHERE ul.courseid = :courseid",
    ['courseid' => $courseid]);

// ---------------------------------------------------------------------------
// Build one row per participant.
// ---------------------------------------------------------------------------

$rowsbyuser = [];

foreach ($participants as $user) {
    $numcompleted = (int) ($numcompletedbyuser[$user->id] ?? 0);

    if ($numcriteria) {
        $percentage = (int) round(($numcompleted / $numcriteria) * 100);
        $progress = "{$numcompleted} / {$numcriteria} ({$percentage}%)";
        if ($percentage == 0) {
            $state = 'notstarted';
        } else if ($percentage < 100) {
            $state = 'inprogress';
        } else {
            $state = 'completed';
        }
    } else {
        // No criteria defined: colouring everybody red would be misleading.
        $percentage = null;
        $progress = '—';
        $state = null;
    }

    // One date column: the completion date once the course is completed,
    // otherwise the last access. A visit after completion does not override
    // the completion date.
    $completedtime = (int) ($coursecompletedbyuser[$user->id] ?? 0);
    $date = $completedtime ?: (int) ($lastaccessbyuser[$user->id] ?? 0);

    $rowsbyuser[$user->id] = [
        'sort' => [
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'email'     => $user->email,
            'progress'  => $numcompleted,
            'date'      => $date,
        ],
        'state' => $state,
        'cells' => [
            s($user->firstname),
            s($user->lastname),
            s($user->email),
            $progress,
            local_coursesoverview_format_date($date),
        ],
        'export' => [
            $user->firstname,
            $user->lastname,
            $user->email,
            $numcompleted,
            $numcriteria,
            $percentage,
            local_coursesoverview_format_date($date),
        ],
    ];
}

// ---------------------------------------------------------------------------
// Split into groups, if the course uses any.
// ---------------------------------------------------------------------------

$groups = groups_get_all_groups($courseid);
$sections = [];

if (!empty($groups)) {
    // Group memberships for the whole course in one query.
    $memberships = $DB->get_records_sql("
            SELECT gm.id, gm.groupid, gm.userid
              FROM {groups_members} gm
              JOIN {groups} g ON g.id = gm.groupid
             WHERE g.courseid = :courseid",
        ['courseid' => $courseid]);

    $membersbygroup = [];
    $hasgroup = [];
    foreach ($memberships as $membership) {
        // Only consider users that are currently enrolled.
        if (!isset($rowsbyuser[$membership->userid])) {
            continue;
        }
        $membersbygroup[$membership->groupid][] = $rowsbyuser[$membership->userid];
        $hasgroup[$membership->userid] = true;
    }

    foreach ($groups as $group) {
        $sections[] = [
            'name' => format_string($group->name, true, ['context' => $context]),
            'rows' => $membersbygroup[$group->id] ?? [],
        ];
    }

    $nogroup = [];
    foreach ($rowsbyuser as $userid => $row) {
        if (empty($hasgroup[$userid])) {
            $nogroup[] = $row;
        }
    }

    if (!empty($nogroup)) {
        $sections[] = [
            'name' => get_string('nogroup', 'local_coursesoverview'),
            'rows' => $nogroup,
        ];
    }
} else {
    $sections[] = ['name' => '', 'rows' => array_values($rowsbyuser)];
}

foreach ($sections as $index => $section) {
    $sections[$index]['rows'] = local_coursesoverview_sort_rows($section['rows'], $sort, $descending);
}

// ---------------------------------------------------------------------------
// Export. Must run before any output is sent.
// ---------------------------------------------------------------------------

if ($download) {
    $columns = [
        get_string('group'),
        get_string('firstname'),
        get_string('lastname'),
        get_string('email'),
        get_string('criteriacompleted', 'local_coursesoverview'),
        get_string('criteriatotal', 'local_coursesoverview'),
        get_string('progresspercent', 'local_coursesoverview'),
        get_string('completed_lastseen', 'local_coursesoverview'),
    ];

    $colors = local_coursesoverview_colors();
    $exportrows = [];
    foreach ($sections as $section) {
        foreach ($section['rows'] as $row) {
            $exportrows[] = [
                'values'  => array_merge([$section['name']], $row['export']),
                'bgcolor' => $row['state'] ? ($colors[$row['state']] ?? null) : null,
            ];
        }
    }

    local_coursesoverview_download(
        $download,
        clean_filename(get_string('completionstatus', 'local_coursesoverview') . '_' . $course->shortname),
        format_string($course->shortname, true, ['context' => $context]),
        $columns,
        $exportrows
    );
}

// ---------------------------------------------------------------------------
// Output.
// ---------------------------------------------------------------------------

/**
 * Render one participants table.
 *
 * @param array $rows already sorted rows as built above
 * @param string $sort active sort column
 * @param bool $descending active sort direction
 * @param moodle_url $baseurl page url without sort parameters
 */
function local_coursesoverview_render_participants_table(array $rows, string $sort,
        bool $descending, moodle_url $baseurl): void {
    if (empty($rows)) {
        echo html_writer::tag('p', get_string('noparticipants', 'local_coursesoverview'));
        return;
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable coursesoverview-participants '
        . LOCAL_COURSESOVERVIEW_TABLE_CLASS;
    $table->head = [
        local_coursesoverview_sort_header(get_string('firstname'), 'firstname', $sort, $descending, $baseurl),
        local_coursesoverview_sort_header(get_string('lastname'), 'lastname', $sort, $descending, $baseurl),
        local_coursesoverview_sort_header(get_string('email'), 'email', $sort, $descending, $baseurl),
        local_coursesoverview_sort_header(get_string('progressheader', 'local_coursesoverview'),
            'progress', $sort, $descending, $baseurl),
        local_coursesoverview_sort_header(get_string('completed_lastseen', 'local_coursesoverview'),
            'date', $sort, $descending, $baseurl),
    ];

    foreach ($rows as $row) {
        $tablerow = new html_table_row($row['cells']);

        // html_writer::table() rebuilds the <tr> attributes, so an inline
        // style set here would be dropped. Colour via a class instead.
        $tablerow->attributes['class'] = $row['state'] ? 'coursesoverview-' . $row['state'] : '';

        $table->data[] = $tablerow;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->header();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/coursesoverview/index.php'),
        '← ' . get_string('backtooverview', 'local_coursesoverview')
    ),
    'coursesoverview-backlink mb-3'
);

echo html_writer::tag('p',
    html_writer::tag('strong', get_string('course') . ': ') .
    html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        format_string($course->fullname, true, ['context' => $context])
    )
);
echo html_writer::tag('p',
    html_writer::tag('strong', get_string('enddate') . ': ') .
    local_coursesoverview_format_date($course->enddate)
);

if (!$completion->is_enabled()) {
    echo $OUTPUT->notification(get_string('completiondisabled', 'local_coursesoverview'), 'warning');
} else if (!$numcriteria) {
    echo $OUTPUT->notification(get_string('nocriteria', 'local_coursesoverview'), 'warning');
}

if (empty($participants)) {
    echo html_writer::tag('p', html_writer::tag('strong',
        get_string('noparticipants', 'local_coursesoverview')));
    echo $OUTPUT->footer();
    exit;
}

echo local_coursesoverview_export_button($sorturl);

echo local_coursesoverview_row_styles(['notstarted', 'inprogress', 'completed']);

echo local_coursesoverview_start_tables();

foreach ($sections as $section) {
    if ($section['name'] !== '') {
        echo html_writer::tag('h3', $section['name']);
    }
    local_coursesoverview_render_participants_table(
        $section['rows'], $sort, $descending, $baseurl);
}

echo local_coursesoverview_end_tables();

echo $OUTPUT->footer();
