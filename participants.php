<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php'); // Include the completion library

global $DB;

// Ensure the user is logged in and has the correct capability
$courseid = required_param('courseid', PARAM_INT);
$context = context_course::instance($courseid);

require_login();
require_capability('local/coursesoverview:view', $context);

/**
 * Represents the completion progress of a participant in a course.
 */
class CompletionProgress {
    public int $completed;
    public int $total;
    public int $percentage;

    /**
     * Constructor for CompletionProgress.
     *
     * @param int $completed Number of completed activities.
     * @param int $total Total number of activities.
     */
    public function __construct(int $completed, int $total) {
        $this->completed = $completed;
        $this->total = $total;
        $this->percentage = ($total === 0) ? 0 : (int) round(($completed / $total) * 100);
    }

    /**
     * Returns a formatted string representation of the progress.
     * Example: "4 / 16 (25%)"
     *
     * @return string
     */
    public function getFormattedProgress(): string {
        return "{$this->completed} / {$this->total} ({$this->percentage}%)";
    }
}

/**
 * Calculates the completion progress of a participant in a course.
 *
 * @param int $userid The user ID.
 * @param int $courseid The course ID.
 * @return CompletionProgress An object representing the participant's progress.
 */
function get_completion_progress(int $userid, int $courseid): CompletionProgress {
    global $DB;

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
        if (isset($activity->completionstate) && in_array($activity->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])) {
            $completed++;
        }
    }

    return new CompletionProgress($completed, $total);
}

// Moodle page setup
$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('participants'));
$PAGE->set_heading(get_string('participants'));

echo $OUTPUT->header();

// Fetch course details
$course = $DB->get_record('course', ['id' => $courseid], 'fullname, enddate');

// Display course name and end date
echo "<p><strong>" . get_string('courseinfo') . ":</strong> {$course->fullname}</p>";
echo "<p><strong>" . get_string('enddate') . ":</strong> " . 
    (!empty($course->enddate) ? date('d.m.Y', $course->enddate) : '-') . "</p>";

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
$html_table = '<table style="border-collapse: collapse; width: 100%; border: 1px solid #ddd;">';
$html_table .= '<thead>';
$html_table .= '<tr>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('firstname') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('lastname') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('email') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('progressheader', 'local_coursesoverview') . '</th>';
$html_table .= "<th style=\"$cellstyle\">" . get_string('completed') . '</th>';
$html_table .= '</tr>';
$html_table .= '</thead>';
$html_table .= '<tbody>';

foreach ($participants as $participant) {
    // Get progress object
    $progress = get_completion_progress($participant->id, $courseid);

    // Format progress display using the class method
    $progressDisplay = $progress->getFormattedProgress();

    // Show completion date only if progress is 100%
    $completiondate = ($progress->percentage >= 100 && $participant->timecompleted)
        ? date('d.m.Y', $participant->timecompleted)
        : '-';

    // Set row background color based on progress
    $rowstyle = $progress->percentage == 0 ? "background-color: $color_none;"
        : ($progress->percentage < 100 ? "background-color: $color_incomplete;" : "background-color: $color_complete;");

    // Add table row
    $html_table .= "<tr style=\"$rowstyle\">";
    $html_table .= "<td style=\"$cellstyle\">{$participant->firstname}</td>";
    $html_table .= "<td style=\"$cellstyle\">{$participant->lastname}</td>";
    $html_table .= "<td style=\"$cellstyle\">{$participant->email}</td>";
    $html_table .= "<td style=\"$cellstyle\">{$progressDisplay}</td>"; // Uses the formatted string
    $html_table .= "<td style=\"$cellstyle\">$completiondate</td>";
    $html_table .= '</tr>';
}

$html_table .= '</tbody>';
$html_table .= '</table>';

// Display the table
echo $html_table;

echo $OUTPUT->footer();