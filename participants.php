<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php'); // Include the completion library

global $DB;

// Ensure the user is logged in and has the correct capability
$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);

require_login();
require_capability('local/coursesoverview:view', $context);

// Moodle page setup
$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('participants'));
$PAGE->set_heading(get_string('participants'));

echo $OUTPUT->header();

// Fetch course
$course = get_course($courseid);

// Display course name and end date
$courseviewlink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            $course->fullname
        );
echo "<p><strong>" . get_string('courseinfo') . ":</strong> {$courseviewlink}</p>";
echo "<p><strong>" . get_string('enddate') . ":</strong> " . 
    (!empty($course->enddate) ? date('d.m.Y', $course->enddate) : '-') . "</p>";

// Fetch participants
$completion = new completion_info($course);
$participants = $completion->get_progress_all();

if (empty($participants)) {
    echo '<p><strong>' . get_string('noparticipants', 'local_coursesoverview') . '</strong></p>';
    echo $OUTPUT->footer();
    exit;
}

// Define table styles
$cellstyle = 'border: 1px solid #ddd; padding: 8px;';
$color_none = '#f8d7da'; // Red
$color_incomplete = '#fff3cd'; // Yellow
$color_complete = '#d4edda'; // Green

// Build the table
$html_table = '<table style="border-collapse: collapse; border: 1px solid #ddd;">';
$html_table .= '<thead>';
$html_table .= '<tr>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('name') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('email') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('progressheader', 'local_coursesoverview') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('completed_lastseen', 'local_coursesoverview') . '</th>';
$html_table .= '</tr>';
$html_table .= '</thead>';
$html_table .= '<tbody>';

// Get criteria for course
$completion = new completion_info($course);

// Get criteria and put in correct order
$criteria = array();

foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_COURSE) as $criterion) {
    $criteria[] = $criterion;
}

foreach ($completion->get_criteria(COMPLETION_CRITERIA_TYPE_ACTIVITY) as $criterion) {
    $criteria[] = $criterion;
}

foreach ($completion->get_criteria() as $criterion) {
    if (!in_array($criterion->criteriatype, array(
            COMPLETION_CRITERIA_TYPE_COURSE, COMPLETION_CRITERIA_TYPE_ACTIVITY))) {
        $criteria[] = $criterion;
    }
}

$numcriteria = count($criteria);

$progress = $completion->get_progress_all();
foreach ($progress as $user) {
    $numcompleted = 0;
    $completedOrLastSeen = "";
    foreach ($criteria as $criterion) {
        $criteria_completion = $completion->get_user_completion($user->id, $criterion);
        if ($criteria_completion->is_complete()) {
            $numcompleted++;
            $completedOrLastSeen = $criteria_completion->timecompleted;
        }
    }
    $percentage = ($numcriteria === 0) ? 0 : (int) round(($numcompleted / $numcriteria) * 100);
        // Show completion date only if progress is 100%
    $completedOrLastSeenDate = ($percentage > 0 && $completedOrLastSeen)
        ? date('d.m.Y', $completedOrLastSeen)
        : '-';
    // Set row background color based on progress
    $rowstyle = $percentage == 0 ? "background-color: $color_none;"
        : ($percentage < 100 ? "background-color: $color_incomplete;" : "background-color: $color_complete;");
    $progressDisplay = "{$numcompleted} / {$numcriteria} ({$percentage}%)";
    $fullname = fullname($user);
    // Add table row
    $html_table .= "<tr style=\"$rowstyle\">";
    $html_table .= "<td style=\"$cellstyle\">{$fullname}</td>";
    $html_table .= "<td style=\"$cellstyle\">{$user->email}</td>";
    $html_table .= "<td style=\"$cellstyle\">{$progressDisplay}</td>"; // Uses the formatted string
    $html_table .= "<td style=\"$cellstyle\">$completedOrLastSeenDate</td>";
    $html_table .= '</tr>';
}

$html_table .= '</tbody>';
$html_table .= '</table>';

// Display the table
echo $html_table;

echo $OUTPUT->footer();
