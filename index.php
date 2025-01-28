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

// Laufende Kurse abrufen
$time = time();
$courses = $DB->get_records_sql("
    SELECT c.id, c.fullname, c.startdate, c.enddate
    FROM {course} c
    WHERE c.startdate <= :now AND c.enddate >= :now AND c.id != 1
", ['now' => $time]);

if (!$courses) {
    echo html_writer::tag('p', get_string('nocourses', 'local_coursesoverview'));
} else {
    // Tabelle erstellen
    $table = new html_table();
    $table->head = [
        get_string('course', 'local_coursesoverview'),
        get_string('startdate', 'local_coursesoverview'),
        get_string('enddate', 'local_coursesoverview'),
        get_string('completedparticipants', 'local_coursesoverview') . ' / ' . get_string('totalparticipants', 'local_coursesoverview'),
        get_string('courseoverviewlink', 'local_coursesoverview'),
        get_string('participants', 'local_coursesoverview'), // Neue Spalte für den Teilnehmer-Link
    ];

    foreach ($courses as $course) {
        // Teilnehmerdaten abrufen
        $totalparticipants = $DB->count_records_sql("
            SELECT COUNT(*) 
            FROM {user_enrolments} ue
            JOIN {enrol} e ON ue.enrolid = e.id
            WHERE e.courseid = :courseid
        ", ['courseid' => $course->id]);

        $completedparticipants = $DB->count_records('course_completions', [
            'course' => $course->id,
            'timecompleted' => NULL,
        ]);

        // Links erstellen
        $courseviewlink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('courseoverviewlink', 'local_coursesoverview')
        );

        $participantslink = html_writer::link(
            new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]),
            get_string('participants', 'local_coursesoverview')
        );

        // Zeilen zur Tabelle hinzufügen
        $table->data[] = [
            $course->fullname,
            userdate($course->startdate),
            userdate($course->enddate),
            "{$completedparticipants} / {$totalparticipants}",
            $courseviewlink,
            $participantslink,
        ];
    }

    // Tabelle ausgeben
    echo html_writer::table($table);
}

echo $OUTPUT->footer();