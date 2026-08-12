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

require_login();

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/coursesoverview:view', $context);

$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('completionstatus', 'local_coursesoverview'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$completion = new completion_info($course);
$criteria = local_coursesoverview_get_sorted_criteria($completion);
$numcriteria = count($criteria);

// ---------------------------------------------------------------------------
// Gather all completion data up front: three queries for the whole page
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

$completiondata = [
    'numcriteria'     => $numcriteria,
    'numcompleted'    => $numcompletedbyuser,
    'coursecompleted' => $coursecompletedbyuser,
    'lastaccess'      => $lastaccessbyuser,
];

/**
 * Render one participants table.
 *
 * @param array $users user records (must carry name fields and email)
 * @param array $data precomputed completion data, see $completiondata above
 */
function local_coursesoverview_render_participants_table(array $users, array $data): void {
    if (empty($users)) {
        echo html_writer::tag('p', get_string('noparticipants', 'local_coursesoverview'));
        return;
    }

    $cellstyle = 'border: 1px solid #ddd; padding: 8px;';
    $colornone = '#f8d7da';
    $colorincomplete = '#fff3cd';
    $colorcomplete = '#d4edda';

    $numcriteria = $data['numcriteria'];
    $users = local_coursesoverview_sort_users_by_fullname($users);

    echo '<table style="border-collapse: collapse; border: 1px solid #ddd;">';
    echo '<thead><tr>';
    echo "<th style=\"{$cellstyle}\">" . get_string('name') . '</th>';
    echo "<th style=\"{$cellstyle}\">" . get_string('email') . '</th>';
    echo "<th style=\"{$cellstyle}\">" . get_string('progressheader', 'local_coursesoverview') . '</th>';
    echo "<th style=\"{$cellstyle}\">" . get_string('coursecompleted', 'local_coursesoverview') . '</th>';
    echo "<th style=\"{$cellstyle}\">" . get_string('lastaccess') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($users as $user) {
        $numcompleted = (int) ($data['numcompleted'][$user->id] ?? 0);

        if ($numcriteria) {
            $percentage = (int) round(($numcompleted / $numcriteria) * 100);
            $progress = "{$numcompleted} / {$numcriteria} ({$percentage}%)";
            if ($percentage == 0) {
                $rowstyle = "background-color: {$colornone};";
            } else if ($percentage < 100) {
                $rowstyle = "background-color: {$colorincomplete};";
            } else {
                $rowstyle = "background-color: {$colorcomplete};";
            }
        } else {
            // No criteria defined: colouring everybody red would be misleading.
            $progress = '—';
            $rowstyle = '';
        }

        $completeddate = local_coursesoverview_format_date($data['coursecompleted'][$user->id] ?? null);
        $lastaccess = local_coursesoverview_format_date($data['lastaccess'][$user->id] ?? null);

        echo "<tr style=\"{$rowstyle}\">";
        echo "<td style=\"{$cellstyle}\">" . fullname($user) . '</td>';
        echo "<td style=\"{$cellstyle}\">" . s($user->email) . '</td>';
        echo "<td style=\"{$cellstyle}\">" . $progress . '</td>';
        echo "<td style=\"{$cellstyle}\">" . $completeddate . '</td>';
        echo "<td style=\"{$cellstyle}\">" . $lastaccess . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// ---------------------------------------------------------------------------
// Output.
// ---------------------------------------------------------------------------

echo $OUTPUT->header();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/coursesoverview/index.php'),
        '← ' . get_string('backtooverview', 'local_coursesoverview')
    ),
    'coursesoverview-backlink',
    ['style' => 'margin-bottom: 1em;']
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

$groups = groups_get_all_groups($courseid);

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
        if (!isset($participants[$membership->userid])) {
            continue;
        }
        $membersbygroup[$membership->groupid][] = $participants[$membership->userid];
        $hasgroup[$membership->userid] = true;
    }

    foreach ($groups as $group) {
        echo html_writer::tag('h3', format_string($group->name, true, ['context' => $context]));
        local_coursesoverview_render_participants_table(
            $membersbygroup[$group->id] ?? [], $completiondata);
        echo html_writer::empty_tag('hr');
    }

    $nogroup = [];
    foreach ($participants as $participant) {
        if (empty($hasgroup[$participant->id])) {
            $nogroup[] = $participant;
        }
    }

    if (!empty($nogroup)) {
        echo html_writer::tag('h3', get_string('nogroup', 'local_coursesoverview'));
        local_coursesoverview_render_participants_table($nogroup, $completiondata);
    }
} else {
    local_coursesoverview_render_participants_table($participants, $completiondata);
}

echo $OUTPUT->footer();
