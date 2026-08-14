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
 * Completion status of all participants of a single course.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/group/lib.php');

use local_coursesoverview\export;
use local_coursesoverview\helper;
use local_coursesoverview\output;

$courseid = required_param('courseid', PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$sort = optional_param('sort', 'lastname', PARAM_ALPHA);
$dir = optional_param('dir', 'asc', PARAM_ALPHA);

$sortcolumns = ['firstname', 'lastname', 'email', 'progress', 'date'];
$sort = helper::validate_sort($sort, $sortcolumns, 'lastname');
$descending = ($dir === 'desc');

require_login();

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/coursesoverview:view', $context);

$baseurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]);
$sorturl = new moodle_url($baseurl, ['sort' => $sort, 'dir' => $descending ? 'desc' : 'asc']);

$pagetitle = get_string('completionstatus', 'local_coursesoverview');

$PAGE->set_course($course);
$PAGE->set_url($sorturl);
$PAGE->set_pagelayout('report');
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

$completion = new completion_info($course);
$criteria = helper::sorted_criteria($completion);
$numcriteria = count($criteria);

// Gather all completion data up front: a handful of queries for the whole page
// instead of one query per user and criterion.
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$participants = get_enrolled_users($context, '', 0, 'u.id, u.email, ' . ltrim($userfields, ' ,'));

// Number of completed criteria per user.
$numcompletedbyuser = [];
if ($numcriteria) {
    $criteriaids = [];
    foreach ($criteria as $criterion) {
        $criteriaids[] = $criterion->id;
    }

    [$insql, $inparams] = $DB->get_in_or_equal($criteriaids, SQL_PARAMS_NAMED, 'crit');

    $sql = "SELECT ccc.userid, COUNT(ccc.id) AS numcompleted
              FROM {course_completion_crit_compl} ccc
             WHERE ccc.course = :courseid
               AND ccc.timecompleted IS NOT NULL
               AND ccc.criteriaid {$insql}
          GROUP BY ccc.userid";
    $numcompletedbyuser = $DB->get_records_sql_menu($sql, ['courseid' => $courseid] + $inparams);
}

// Actual course completion time per user.
$sql = "SELECT cc.userid, cc.timecompleted
          FROM {course_completions} cc
         WHERE cc.course = :courseid
           AND cc.timecompleted IS NOT NULL";
$coursecompletedbyuser = $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);

// Last access to this course per user.
$sql = "SELECT ul.userid, ul.timeaccess
          FROM {user_lastaccess} ul
         WHERE ul.courseid = :courseid";
$lastaccessbyuser = $DB->get_records_sql_menu($sql, ['courseid' => $courseid]);

// Build one row per participant.
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
            'lastname' => $user->lastname,
            'email' => $user->email,
            'progress' => $numcompleted,
            'date' => $date,
        ],
        'state' => $state,
        'cells' => [
            s($user->firstname),
            s($user->lastname),
            s($user->email),
            $progress,
            helper::format_date($date),
        ],
        // The export mirrors the compact on-screen columns: one name, one
        // progress figure, one date.
        'export' => [
            fullname($user),
            $user->email,
            $progress,
            helper::format_date($date, ''),
        ],
    ];
}

// Split into groups, if the course uses any.
$groups = groups_get_all_groups($courseid);
$sections = [];

if (!empty($groups)) {
    // Group memberships for the whole course in one query.
    $sql = "SELECT gm.id, gm.groupid, gm.userid
              FROM {groups_members} gm
              JOIN {groups} g ON g.id = gm.groupid
             WHERE g.courseid = :courseid";
    $memberships = $DB->get_records_sql($sql, ['courseid' => $courseid]);

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
    $sections[$index]['rows'] = helper::sort_rows($section['rows'], $sort, $descending);
}

// Export. Must run before any output is sent.
if ($download) {
    // The group column only earns its place when the course actually uses
    // groups, otherwise it would be a column of blanks.
    $withgroups = !empty($groups);

    $columns = [];
    if ($withgroups) {
        $columns[] = get_string('group');
    }
    $columns[] = get_string('name');
    $columns[] = get_string('email');
    $columns[] = get_string('progressheader', 'local_coursesoverview');
    $columns[] = get_string('completed_lastseen', 'local_coursesoverview');

    $colors = helper::colors();
    $exportrows = [];
    foreach ($sections as $section) {
        foreach ($section['rows'] as $row) {
            $values = $row['export'];
            if ($withgroups) {
                array_unshift($values, $section['name']);
            }
            $exportrows[] = [
                'values' => $values,
                'bgcolor' => $row['state'] ? ($colors[$row['state']] ?? null) : null,
            ];
        }
    }

    // Spreadsheet cells take raw text, not the HTML-escaped output of
    // format_string(), which would turn an ampersand into an entity.
    $preamble = [
        [get_string('course'), $course->fullname],
        [get_string('enddate'), helper::format_date($course->enddate, '')],
    ];

    export::download(
        $download,
        clean_filename($pagetitle . '_' . $course->shortname),
        $course->shortname,
        $columns,
        $exportrows,
        $preamble
    );
}

echo $OUTPUT->header();

// The overview is gated on the system context. Someone who only holds the
// capability on this one course would follow the link into an access denied
// page, so it is only offered to those who can actually get there.
if (has_capability('local/coursesoverview:view', context_system::instance())) {
    $backurl = new moodle_url('/local/coursesoverview/index.php');
    $backlabel = '← ' . get_string('backtooverview', 'local_coursesoverview');
    echo html_writer::div(html_writer::link($backurl, $backlabel), 'coursesoverview-backlink mb-3');
}

$coursename = format_string($course->fullname, true, ['context' => $context]);
echo html_writer::tag('p', html_writer::tag('strong', get_string('course') . ': ') . $coursename);

$enddate = helper::format_date($course->enddate);
echo html_writer::tag('p', html_writer::tag('strong', get_string('enddate') . ': ') . $enddate);

if (!$completion->is_enabled()) {
    $notice = get_string('completiondisabled', 'local_coursesoverview');
    echo $OUTPUT->notification($notice, 'warning');
} else if (!$numcriteria) {
    $notice = get_string('nocriteria', 'local_coursesoverview');
    echo $OUTPUT->notification($notice, 'warning');
}

if (empty($participants)) {
    $notice = get_string('noparticipants', 'local_coursesoverview');
    echo html_writer::tag('p', html_writer::tag('strong', $notice));
    echo $OUTPUT->footer();
    exit;
}

echo output::export_button($sorturl);
echo output::legend(['notstarted', 'inprogress', 'completed']);
echo output::start_tables();

foreach ($sections as $section) {
    if ($section['name'] !== '') {
        echo html_writer::tag('h3', $section['name']);
    }
    $caption = ($section['name'] !== '') ? $section['name'] : $pagetitle;
    echo output::participants_table($section['rows'], $sort, $descending, $baseurl, $caption);
}

echo output::end_tables();

echo $OUTPUT->footer();
