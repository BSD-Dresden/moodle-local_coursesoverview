<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT); // Kurs-ID aus der URL abrufen.
$context = context_course::instance($courseid);

require_login();
require_capability('local/coursesoverview:view', $context);

$PAGE->set_url(new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('participants', 'local_coursesoverview'));
$PAGE->set_heading(get_string('participants', 'local_coursesoverview'));

echo $OUTPUT->header();

global $DB;

// Teilnehmerdaten abfragen
$sql = "
    SELECT u.id, u.firstname, u.lastname, u.email, cc.timecompleted,
           (CASE WHEN cc.timecompleted IS NOT NULL THEN 100
                 WHEN ccmc.progress IS NOT NULL THEN ccmc.progress
                 ELSE 0 END) AS progress
    FROM {user} u
    JOIN {user_enrolments} ue ON u.id = ue.userid
    JOIN {enrol} e ON ue.enrolid = e.id
    LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
    LEFT JOIN (
        SELECT cmc.userid, ROUND(AVG(cmc.completionstate) * 100) AS progress
        FROM {course_modules_completion} cmc
        JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
        WHERE cm.course = :courseid
        GROUP BY cmc.userid
    ) ccmc ON ccmc.userid = u.id
    WHERE e.courseid = :courseid
";

$participants = $DB->get_records_sql($sql, ['courseid' => $courseid]);

// Tabelle erstellen
$table = new html_table();
$table->head = [
    get_string('firstname', 'local_coursesoverview'),
    get_string('lastname', 'local_coursesoverview'),
    get_string('email', 'local_coursesoverview'),
    get_string('progress', 'local_coursesoverview'),
    get_string('completiondate', 'local_coursesoverview'),
];
$table->data = [];

foreach ($participants as $participant) {
    $progress = $participant->progress;
    $completiondate = $participant->timecompleted ? userdate($participant->timecompleted) : '-';

    // Farbe basierend auf Fortschritt setzen
    $rowclass = '';
    if ($progress == 0) {
        $rowclass = 'progress-none'; // Rot
    } elseif ($progress < 100) {
        $rowclass = 'progress-incomplete'; // Gelb
    } else {
        $rowclass = 'progress-complete'; // Grün
    }

    $table->data[] = [
        'class' => $rowclass,
        $participant->firstname,
        $participant->lastname,
        $participant->email,
        "{$progress}%",
        $completiondate,
    ];
}

// Tabelle ausgeben
echo html_writer::table($table);

// CSS für die Farben einfügen
echo html_writer::tag('style', "
    .progress-none td { background-color: #f8d7da; } /* Rot */
    .progress-incomplete td { background-color: #fff3cd; } /* Gelb */
    .progress-complete td { background-color: #d4edda; } /* Grün */
");

echo $OUTPUT->footer();