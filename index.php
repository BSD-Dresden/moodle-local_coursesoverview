<?php
/**
 * Overview of all courses with the number of participants that completed them.
 *
 * @package local_coursesoverview
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/locallib.php');

require_login();

$context = context_system::instance();
require_capability('local/coursesoverview:view', $context);

$PAGE->set_url(new moodle_url('/local/coursesoverview/index.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_coursesoverview'));
$PAGE->set_heading(get_string('pluginname', 'local_coursesoverview'));

$now = time();

// Fetch all courses (excluding the site course) and preload their contexts so
// that context_course::instance() below does not hit the database per course.
$ctxfields = context_helper::get_preload_record_columns_sql('ctx');
$courses = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.visible, c.startdate, c.enddate, c.enablecompletion, {$ctxfields}
          FROM {course} c
          JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextcourse
         WHERE c.id <> :siteid
      ORDER BY c.enddate DESC, c.fullname ASC",
    ['contextcourse' => CONTEXT_COURSE, 'siteid' => SITEID]);

echo $OUTPUT->header();

if (!$courses) {
    echo html_writer::tag('p', get_string('nocourses', 'local_coursesoverview'));
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('style', '
    .coursesoverview-past td { background-color: #f0f0f0; color: #888; }
    .coursesoverview-active td { background-color: #ffffff; color: #000; }
    .coursesoverview-hidden td { background-color: #e0e0e0; color: #aaa; }
');

$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('startdate'),
    get_string('enddate'),
    get_string('completedparticipants', 'local_coursesoverview') . ' / ' .
        get_string('totalparticipants', 'local_coursesoverview'),
    get_string('actions'),
    get_string('completionstatus', 'local_coursesoverview'),
];

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
    } else {
        $completioncell = get_string('completiondisabled', 'local_coursesoverview');
    }

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

    $participantslink = html_writer::link(
        new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
        get_string('completionstatus', 'local_coursesoverview')
    );

    // Hidden / past / active. A course without an end date is never "past".
    if ($course->visible == 0) {
        $rowclass = 'coursesoverview-hidden';
    } else if (!empty($course->enddate) && $course->enddate < $now) {
        $rowclass = 'coursesoverview-past';
    } else {
        $rowclass = 'coursesoverview-active';
    }

    $row = new html_table_row([
        format_string($course->fullname, true, ['context' => $coursecontext]),
        local_coursesoverview_format_date($course->startdate),
        local_coursesoverview_format_date($course->enddate),
        $completioncell,
        $courseactions,
        $participantslink,
    ]);
    $row->attributes['class'] = $rowclass;

    $table->data[] = $row;
}

if (empty($table->data)) {
    echo html_writer::tag('p', get_string('nocourses', 'local_coursesoverview'));
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
