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
    SELECT c.id, c.fullname, c.startdate, c.enddate, c.visible
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
        // Get criteria for course
        $completion = new completion_info($course);
        // Get total participants enrolled in the course
        $totalparticipants = $completion->get_num_tracked_users('', array(), $group);
        
        // Skip courses with no participants
        if ($totalparticipants < 1) {
            continue;
        }
        
        // Get progress data for users
        $progress = $completion->get_progress_all();
        $completedparticipants = 0;
        // Count the number of completed users
        foreach ($progress as $user) {
            $cinfo = new completion_info($course);
            $iscomplete = $cinfo->is_course_complete($user->id);
            if ($iscomplete) {
                $completedparticipants++;
            }
        }

        // Create links for course view and (optionally) edit
        $courseviewlink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('view')
        );
        
        // Start with the view link.
        $courseactions = $courseviewlink;
        
        // Add " / Edit" if the user can edit this course.
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $editlink = html_writer::link(
                new moodle_url('/course/edit.php', ['id' => $course->id]),
                get_string('edit')
            );
            $courseactions .= ' / ' . $editlink;
        }


        $participantslink = html_writer::link(
            new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
            get_string('coursecompletion')
        );

        // Determine if the course is past, ongoing, or hidden
        if ($course->visible == 0) {
            $rowclass = 'course-hidden';
        } elseif ($course->enddate < $time) {
            $rowclass = 'course-past';
        } else {
            $rowclass = 'course-active';
        }

        // Add a row to the table
        $row = new html_table_row([
            $course->fullname,
            userdate($course->startdate),
            userdate($course->enddate),
            "{$completedparticipants} / {$totalparticipants}",
            $courseactions,   // View / Edit
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
        .course-hidden td { background-color: #e0e0e0; color: #aaa; } /* Hidden courses: greyed out */
    ");
}

echo $OUTPUT->footer();
