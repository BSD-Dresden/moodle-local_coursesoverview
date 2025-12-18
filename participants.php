<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/group/lib.php');

global $DB;

$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);

require_login();
require_capability('local/coursesoverview:view', $context);

$course = get_course($courseid);

$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('participants'));
$PAGE->set_heading(get_string('participants'));

echo $OUTPUT->header();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/coursesoverview/index.php'),
        '← ' . get_string('back')
    ),
    'coursesoverview-backlink',
    ['style' => 'margin-bottom: 1em;']
);


// Course info
$courseviewlink = html_writer::link(
    new moodle_url('/course/view.php', ['id' => $course->id]),
    $course->fullname
);
echo "<p><strong>" . get_string('courseinfo') . ":</strong> {$courseviewlink}</p>";
echo "<p><strong>" . get_string('enddate') . ":</strong> " .
    (!empty($course->enddate) ? date('d.m.Y', $course->enddate) : '-') . "</p>";

$completion = new completion_info($course);

// Styles
$cellstyle = 'border: 1px solid #ddd; padding: 8px;';
$color_none = '#f8d7da';
$color_incomplete = '#fff3cd';
$color_complete = '#d4edda';

// Completion criteria (unchanged)
$criteria = [];

foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $c) {
    $criteria[] = $c;
}
foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $c) {
    $criteria[] = $c;
}
foreach ($completion->get_criteria() as $c) {
    if (!in_array($c->criteriatype, [
        COMPLETION_CRITERIA_TYPE_COURSE,
        COMPLETION_CRITERIA_TYPE_ACTIVITY
    ])) {
        $criteria[] = $c;
    }
}

$numcriteria = count($criteria);

/**
 * Sort users by fullname (locale-aware)
 */
function sort_by_fullname(array &$users): void {
    usort($users, function($a, $b) {
        return strcoll(fullname($a), fullname($b));
    });
}

/**
 * Render participants table
 */
function render_participants_table(
    array $users,
    completion_info $completion,
    array $criteria,
    int $numcriteria,
    string $cellstyle,
    string $color_none,
    string $color_incomplete,
    string $color_complete
) {
    if (empty($users)) {
        return; // caller decides whether section is shown
    }

    sort_by_fullname($users);

    echo '<table style="border-collapse: collapse; border: 1px solid #ddd;">';
    echo '<thead><tr>';
    echo "<th style=\"$cellstyle\">" . get_string('name') . '</th>';
    echo "<th style=\"$cellstyle\">" . get_string('email') . '</th>';
    echo "<th style=\"$cellstyle\">" . get_string('progressheader', 'local_coursesoverview') . '</th>';
    echo "<th style=\"$cellstyle\">" . get_string('completed_lastseen', 'local_coursesoverview') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($users as $user) {
        $numcompleted = 0;
        $completedtime = null;

        foreach ($criteria as $criterion) {
            $cc = $completion->get_user_completion($user->id, $criterion);
            if ($cc->is_complete()) {
                $numcompleted++;
                $completedtime = $cc->timecompleted;
            }
        }

        $percentage = $numcriteria
            ? (int) round(($numcompleted / $numcriteria) * 100)
            : 0;

        $date = ($percentage > 0 && $completedtime)
            ? date('d.m.Y', $completedtime)
            : '-';

        $rowstyle = $percentage == 0
            ? "background-color: $color_none;"
            : ($percentage < 100
                ? "background-color: $color_incomplete;"
                : "background-color: $color_complete;");

        echo "<tr style=\"$rowstyle\">";
        echo "<td style=\"$cellstyle\">" . fullname($user) . "</td>";
        echo "<td style=\"$cellstyle\">" . s($user->email) . "</td>";
        echo "<td style=\"$cellstyle\">{$numcompleted} / {$numcriteria} ({$percentage}%)</td>";
        echo "<td style=\"$cellstyle\">{$date}</td>";
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// ---------- GROUP-AWARE OUTPUT ----------
$groups = groups_get_all_groups($courseid);

if (!empty($groups)) {

    $enrolled = get_enrolled_users(
        $context,
        '',
        0,
        'u.id, u.firstname, u.lastname, u.email',
        'u.lastname, u.firstname'
    );
    $enrolled = $enrolled ? array_values($enrolled) : [];

    $ingroup = [];

    foreach ($groups as $group) {
        echo html_writer::tag('h3', format_string($group->name));

        $members = groups_get_members(
            $group->id,
            'u.id, u.firstname, u.lastname, u.email',
            'u.lastname, u.firstname'
        );
        $members = $members ? array_values($members) : [];

        foreach ($members as $m) {
            $ingroup[$m->id] = true;
        }

        render_participants_table(
            $members, $completion, $criteria, $numcriteria,
            $cellstyle, $color_none, $color_incomplete, $color_complete
        );

        echo html_writer::empty_tag('hr');
    }

    // ---- NO GROUP (only if non-empty) ----
    $nogroup = [];
    foreach ($enrolled as $u) {
        if (empty($ingroup[$u->id])) {
            $nogroup[] = $u;
        }
    }

    if (!empty($nogroup)) {
        echo html_writer::tag('h3', get_string('nogroup', 'group'));
        render_participants_table(
            $nogroup, $completion, $criteria, $numcriteria,
            $cellstyle, $color_none, $color_incomplete, $color_complete
        );
    }

} else {
    // No groups → original single table
    $participants = $completion->get_progress_all();

    if (empty($participants)) {
        echo '<p><strong>' . get_string('noparticipants', 'local_coursesoverview') . '</strong></p>';
        echo $OUTPUT->footer();
        exit;
    }

    render_participants_table(
        $participants, $completion, $criteria, $numcriteria,
        $cellstyle, $color_none, $color_incomplete, $color_complete
    );
}

echo $OUTPUT->footer();
