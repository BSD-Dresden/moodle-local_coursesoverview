<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/coursesoverview:view', $context);

$PAGE->set_url(new moodle_url('/local/coursesoverview/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_coursesoverview'));
$PAGE->set_heading(get_string('pluginname', 'local_coursesoverview'));

echo $OUTPUT->header();

global $DB;

// Get the current timestamp
$time = time();

// Query all courses (excluding the site course with id 1)
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.startdate, c.enddate
    FROM {course} c
    WHERE c.id != 1
    ORDER BY enddate DESC
");

if (!$courses) {
    // Display a message if no courses are found
    echo html_writer::tag('p', get_string('nocourses', 'local_coursesoverview'));
} else {
    // Create a table for course data
    $table = new html_table();
    $table->head = [
        get_string('course'),
        get_string('startdate'),
        get_string('enddate'),
        get_string('completedparticipants', 'local_coursesoverview') . ' / ' . get_string('totalparticipants', 'local_coursesoverview'),
        get_string('course'),
        get_string('coursecompletion'),
    ];

    foreach ($courses as $course) {
        // Get total participants enrolled in the course
        $totalparticipants = $DB->count_records_sql("
            SELECT COUNT(*) 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            WHERE e.courseid = :courseid
        ", ['courseid' => $course->id]);
        
        // Skip courses with no participants
        if ($totalparticipants < 1) {
            continue;
        }

        // Get the count of participants who have completed the course
        $completedparticipants = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {course_completions} cc
            JOIN {user_enrolments} ue ON cc.userid = ue.userid
            JOIN {enrol} e ON ue.enrolid = e.id
            WHERE cc.course = :completioncourseid
              AND cc.timecompleted IS NOT NULL
              AND e.courseid = :enrolcourseid
        ", [
            'completioncourseid' => $course->id,
            'enrolcourseid' => $course->id,
        ]);

        // Create links for course view and participants
        $courseviewlink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('view')
        );

        $participantslink = html_writer::link(
            new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
            get_string('coursecompletion')
        );

        // Determine if the course is past or ongoing
        $rowclass = ($course->enddate < $time) ? 'course-past' : 'course-active';

        // Add a row to the table
        $row = new html_table_row([
            $course->fullname,
            userdate($course->startdate),
            userdate($course->enddate),
            "{$completedparticipants} / {$totalparticipants}",
            $courseviewlink,
            $participantslink,
        ]);

        // Apply CSS class based on the course status
        $row->attributes['class'] = $rowclass;

        // Add the row to the table
        $table->data[] = $row;
    }

    // Output the table
    echo html_writer::table($table);

    // Add inline CSS for styling the table rows
    echo html_writer::tag('style', "
        .course-past td { background-color: #f0f0f0; color: #888; } /* Past courses: greyed out */
        .course-active td { background-color: #ffffff; color: #000; } /* Active courses: normal */
    ");
}

echo $OUTPUT->footer();
