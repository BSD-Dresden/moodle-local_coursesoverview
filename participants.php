<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php'); // Include the completion library

$courseid = required_param('courseid', PARAM_INT); // Get the course ID from the URL
$context = context_course::instance($courseid);

require_login();
require_capability('local/coursesoverview:view', $context);

$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('participants', 'local_coursesoverview'));
$PAGE->set_heading(get_string('participants', 'local_coursesoverview'));

echo $OUTPUT->header();

global $DB;

/**
 * Calculate the completion progress of a participant in the course.
 *
 * @param int $userid The user ID.
 * @param int $courseid The course ID.
 * @return int The completion progress as a percentage.
 */
function get_completion_progress($userid, $courseid) {
    global $DB;

    // Fetch activities with completion criteria linked to the course
    $sql = "
        SELECT cm.id AS moduleid, cmc.completionstate, cm.instance, m.name AS modulename
        FROM {course_modules} cm
        LEFT JOIN {course_modules_completion} cmc 
        ON cm.id = cmc.coursemoduleid AND cmc.userid = :userid
        JOIN {modules} m ON cm.module = m.id
        JOIN {course_completion_criteria} ccc 
        ON ccc.moduleinstance = cm.id AND ccc.criteriatype = 4 AND ccc.course = :criteria_courseid
        WHERE cm.course = :modules_courseid AND cm.completion IN (1, 2)
    ";
    $activities = $DB->get_records_sql($sql, [
        'userid' => $userid,
        'modules_courseid' => $courseid,
        'criteria_courseid' => $courseid,
    ]);

    $completed = 0;
    $total = count($activities);

    foreach ($activities as $activity) {
        // Check if the activity is completed
        if (isset($activity->completionstate) && in_array($activity->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            $completed++;
        }
    }

    // Return 0% progress if no activities exist
    if ($total === 0) {
        return 0;
    }

    // Calculate progress as a percentage
    return (int) round(($completed / $total) * 100);
}

// Fetch course details
$course = $DB->get_record('course', ['id' => $courseid], 'fullname, enddate');

// Display course name and end date
echo "<p><strong>" . get_string('courseinfo') . ":</strong> {$course->fullname}</p>";
if (!empty($course->enddate)) {
    echo "<p><strong>" . get_string('enddate') . "</strong> " . date('d.m.Y', $course->enddate) . "</p>";
} else {
    echo "<p><strong>Course end date:</strong> -</p>";
}

// Fetch participants
$sql = "
    SELECT u.id, u.firstname, u.lastname, u.email, cc.timecompleted
    FROM {user} u
    JOIN {user_enrolments} ue ON u.id = ue.userid
    JOIN {enrol} e ON ue.enrolid = e.id
    LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
    WHERE e.courseid = :courseid
";
$participants = $DB->get_records_sql($sql, ['courseid' => $courseid]);

// Build the table with inline styles
$thstyle = '<th style="border: 1px solid #ddd; padding: 8px;">';
$html_table = '<table style="border-collapse: collapse; width: 100%; border: 1px solid #ddd;">';
$html_table .= '<thead>';
$html_table .= '<tr>';
$html_table .= $thstyle . get_string('firstname') . '</th>';
$html_table .= $thstyle . get_string('lastname') . '</th>';
$html_table .= $thstyle . get_string('email') . '</th>';
$html_table .= $thstyle . get_string('progress') . '</th>';
$html_table .= $thstyle . get_string('completed') . '</th>';
$html_table .= '</tr>';
$html_table .= '</thead>';
$html_table .= '<tbody>';

$tdstyle = '<td style="border: 1px solid #ddd; padding: 8px;">';
foreach ($participants as $participant) {
    $progress = get_completion_progress($participant->id, $courseid);
    $completiondate = ($progress === 100 && $participant->timecompleted)
        ? date('d.m.Y', $participant->timecompleted)
        : '-';

    // Set row background color based on progress
    $rowstyle = '';
    if ($progress == 0) {
        $rowstyle = 'background-color: #f8d7da;'; // Red
    } elseif ($progress < 100) {
        $rowstyle = 'background-color: #fff3cd;'; // Yellow
    } else {
        $rowstyle = 'background-color: #d4edda;'; // Green
    }

    $html_table .= '<tr style="' . $rowstyle . '">';
    $html_table .= $tdstyle . $participant->firstname . '</td>';
    $html_table .= $tdstyle . $participant->lastname . '</td>';
    $html_table .= $tdstyle . $participant->email . '</td>';
    $html_table .= $tdstyle . $progress . '%</td>';
    $html_table .= $tdstyle . $completiondate . '</td>';
    $html_table .= '</tr>';
}

$html_table .= '</tbody>';
$html_table .= '</table>';

// Display the table
echo $html_table;

echo $OUTPUT->footer();