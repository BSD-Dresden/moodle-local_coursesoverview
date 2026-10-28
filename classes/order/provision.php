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

namespace local_coursesoverview\order;

use stdClass;

/**
 * Turn a checked order into a course with its participants.
 *
 * Nothing here reimplements what Moodle can already do. The course comes from
 * the course import with the module's course as its template, the accounts
 * come from the user import, and both are fed a file that is built here and
 * never touches the disk. What this class owns is the order around them: that
 * the welcome message is silenced before anybody is enrolled, that a
 * deliberately reused account is enrolled rather than created, and that the
 * order is written down afterwards.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {
    /** @var string role of somebody who takes the course */
    const ROLE_PARTICIPANT = 'student';

    /** @var string role of somebody who only watches it */
    const ROLE_ORGANISER = 'teacher';

    /** @var string profile field the order number goes into, skipped when it does not exist */
    const PROFILE_FIELD = 'abnr';

    /** @var string credentials go to the participants themselves */
    const DELIVERY_DIRECT = 'direct';

    /** @var string credentials come back as a list */
    const DELIVERY_LIST = 'list';

    /**
     * Create the course, the accounts and the enrolments.
     *
     * @param stdClass $order company, abnr, module, shortname, fullname, startdate, enddate, scheme, delivery
     * @param module $module
     * @param check[] $checks the lines, already checked and free of errors
     * @return stdClass courseid, created, reused, errors, credentials, orderid
     */
    public static function run(stdClass $order, module $module, array $checks): stdClass {
        global $CFG, $DB;

        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');

        $result = (object) [
            'courseid' => 0,
            'created' => 0,
            'reused' => 0,
            'errors' => [],
            'credentials' => [],
            'orderid' => 0,
        ];

        if ($DB->record_exists('course', ['shortname' => $order->shortname])) {
            $result->errors[] = get_string('ordercourseexists', 'local_coursesoverview', s($order->shortname));
            return $result;
        }

        $course = self::create_course($order, $module, $result);
        if (!$course) {
            return $result;
        }
        $result->courseid = (int) $course->id;

        $instance = self::silence_welcome((int) $course->id);
        if (!$instance) {
            $result->errors[] = get_string('ordernomanualenrol', 'local_coursesoverview');
            return $result;
        }

        self::import_users($order, $course, $checks, $result);

        foreach ($checks as $check) {
            if ($check->reuse && $check->existing) {
                self::enrol_existing($instance, $order, $check, $result);
            }
        }

        self::record($order, $course, $checks, $result);

        return $result;
    }

    /**
     * Create the course from the module's template.
     *
     * @param stdClass $order
     * @param module $module
     * @param stdClass $result collects the errors
     * @return stdClass|null the new course
     */
    protected static function create_course(stdClass $order, module $module, stdClass $result): ?stdClass {
        global $DB;

        $columns = ['shortname', 'fullname', 'category', 'startdate', 'enddate', 'idnumber'];
        $values = [
            $order->shortname,
            $order->fullname,
            (string) $module->category(),
            date('Y-m-d', $order->startdate),
            date('Y-m-d', $order->enddate),
            $order->abnr,
        ];

        $content = self::csv_line($columns) . self::csv_line($values);

        $iid = \csv_import_reader::get_new_iid('uploadcourse');
        $reader = new \csv_import_reader($iid, 'uploadcourse');
        if (!$reader->load_csv_content($content, 'UTF-8', 'semicolon')) {
            $result->errors[] = $reader->get_error();
            $reader->cleanup();
            return null;
        }

        $options = [
            'mode' => \tool_uploadcourse_processor::MODE_CREATE_NEW,
            'updatemode' => \tool_uploadcourse_processor::UPDATE_NOTHING,
            'templatecourse' => $module->template,
        ];

        // The import prints its report as it goes. It is only of interest when
        // the course did not appear, so it is caught and held until then.
        ob_start();
        try {
            $processor = new \tool_uploadcourse_processor($reader, $options, []);
            $processor->execute(new \tool_uploadcourse_tracker(\tool_uploadcourse_tracker::OUTPUT_PLAIN));
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        } finally {
            $log = ob_get_clean();
        }
        $reader->cleanup();

        $course = $DB->get_record('course', ['shortname' => $order->shortname]);
        if (!$course) {
            $result->errors[] = get_string('ordercoursefailed', 'local_coursesoverview');
            if (trim($log) !== '') {
                $result->errors[] = trim($log);
            }
            return null;
        }

        return $course;
    }

    /**
     * Take the welcome message off the course before anybody is enrolled.
     *
     * Since Moodle 4.4 every manual enrolment fires a hook that mails the
     * course welcome message, and the site default has it switched on. The
     * value travels from the template course into the copy, so it is turned
     * off here rather than relied upon. Our own mail is the one that should
     * arrive, and when the credentials are held back for a later start no
     * mail may go out at all.
     *
     * @param int $courseid
     * @return stdClass|null the manual enrolment instance, null when there is none
     */
    protected static function silence_welcome(int $courseid): ?stdClass {
        global $DB;

        $instance = null;
        foreach (enrol_get_instances($courseid, false) as $candidate) {
            if ($candidate->enrol === 'manual') {
                $instance = $candidate;
                break;
            }
        }

        if (!$instance || !enrol_is_enabled('manual')) {
            return null;
        }

        if ((int) $instance->customint1 !== ENROL_DO_NOT_SEND_EMAIL) {
            $DB->set_field('enrol', 'customint1', ENROL_DO_NOT_SEND_EMAIL, ['id' => $instance->id]);
            $instance->customint1 = ENROL_DO_NOT_SEND_EMAIL;
        }

        return $instance;
    }

    /**
     * Hand the new accounts to the user import.
     *
     * @param stdClass $order
     * @param stdClass $course
     * @param check[] $checks
     * @param stdClass $result
     */
    protected static function import_users(stdClass $order, stdClass $course, array $checks, stdClass $result): void {
        global $DB;

        $withpassword = ($order->delivery === self::DELIVERY_LIST);
        $withabnr = $DB->record_exists('user_info_field', ['shortname' => self::PROFILE_FIELD]);
        $start = date('Y-m-d', $order->startdate);

        $columns = ['username', 'firstname', 'lastname', 'email'];
        if ($withpassword) {
            $columns[] = 'password';
        }
        $columns = array_merge($columns, [
            'course1', 'role1', 'enroltimestart1',
            'course2', 'role2', 'enroltimestart2',
        ]);
        if ($withabnr) {
            $columns[] = 'profile_field_' . self::PROFILE_FIELD;
        }

        $content = self::csv_line($columns);
        $passwords = [];
        $wanted = [];

        foreach ($checks as $check) {
            if ($check->reuse) {
                continue;
            }

            $person = $check->person;
            $values = [$check->username, $person->firstname, $person->lastname, $person->email];

            if ($withpassword) {
                $passwords[$check->username] = generate_password(10);
                $values[] = $passwords[$check->username];
            }

            // The participant side and the organiser side are two enrolments
            // in the same course, which is how somebody can be both.
            $values[] = $person->is_participant() ? $course->shortname : '';
            $values[] = $person->is_participant() ? self::ROLE_PARTICIPANT : '';
            $values[] = $person->is_participant() ? $start : '';
            $values[] = $person->is_organiser() ? $course->shortname : '';
            $values[] = $person->is_organiser() ? self::ROLE_ORGANISER : '';
            $values[] = $person->is_organiser() ? $start : '';

            if ($withabnr) {
                $values[] = $order->abnr;
            }

            $content .= self::csv_line($values);
            $wanted[] = $check->username;
        }

        if (!$wanted) {
            return;
        }

        $iid = \csv_import_reader::get_new_iid('uploaduser');
        $reader = new \csv_import_reader($iid, 'uploaduser');
        if (!$reader->load_csv_content($content, 'UTF-8', 'semicolon')) {
            $result->errors[] = $reader->get_error();
            $reader->cleanup();
            return;
        }

        // A column the import does not know ends in an exception rather than
        // a reported line, and that must not take the page down with it.
        capture_tracker::reset();
        try {
            $process = new \tool_uploaduser\process($reader, capture_tracker::class);
            $process->set_form_data(self::form_data($order));
            $process->process();
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        }
        $reader->cleanup();

        foreach (capture_tracker::errors() as $error) {
            $result->errors[] = $error;
        }

        // What counts as created is what is in the database afterwards.
        $accounts = validator::accounts($wanted);
        $result->created = count($accounts);

        foreach ($checks as $check) {
            if ($check->reuse || !isset($accounts[$check->username])) {
                continue;
            }
            $check->userid = (int) $accounts[$check->username]->id;

            $result->credentials[] = (object) [
                'name' => $check->person->name(),
                'firstname' => $check->person->firstname,
                'lastname' => $check->person->lastname,
                'username' => $check->username,
                'password' => $passwords[$check->username] ?? '',
                'email' => $check->person->email,
                'role' => $check->person->role,
            ];
        }

        foreach ($checks as $check) {
            if (!$check->reuse && !isset($accounts[$check->username])) {
                $result->errors[] = get_string('ordernotcreated', 'local_coursesoverview', s($check->username));
            }
        }
    }

    /**
     * The settings the user import would otherwise take from its form.
     *
     * @param stdClass $order
     * @return stdClass
     */
    protected static function form_data(stdClass $order): stdClass {
        $direct = ($order->delivery === self::DELIVERY_DIRECT);

        return (object) [
            // Existing accounts have been ruled out before this point, so
            // skipping them here is a second net rather than a decision.
            'uutype' => UU_USER_ADDNEW,
            'uuupdatetype' => UU_UPDATE_NOCHANGES,
            // Without a password in the file the import hands the account to
            // the cron task that mails the credentials. With one it stores it
            // quietly, which is what the list route needs.
            'uupasswordnew' => $direct ? 1 : 0,
            'uupasswordold' => 0,
            // The mailed route forces a new password at the first login by
            // itself. The list route deliberately does not, because the
            // password on that list is what people are given to work with.
            'uuforcepasswordchange' => UU_PWRESET_NONE,
            'uuallowrenames' => 0,
            'uuallowdeletes' => 0,
            'uuallowsuspends' => 0,
            'uunoemailduplicates' => 0,
            'uustandardusernames' => 1,
            'uumatchemail' => 0,
            'uubulk' => 0,
            'uulegacy1' => 0,
            'uulegacy2' => 0,
            'uulegacy3' => 0,
            'auth' => 'manual',
        ];
    }

    /**
     * Enrol somebody who already has an account.
     *
     * @param stdClass $instance the manual enrolment instance
     * @param stdClass $order
     * @param check $check
     * @param stdClass $result
     */
    protected static function enrol_existing(stdClass $instance, stdClass $order, check $check, stdClass $result): void {
        $plugin = enrol_get_plugin('manual');
        $roles = self::role_ids();
        $check->userid = (int) $check->existing->id;

        if ($check->person->is_participant() && $roles[self::ROLE_PARTICIPANT]) {
            $plugin->enrol_user($instance, $check->existing->id, $roles[self::ROLE_PARTICIPANT], $order->startdate);
        }
        if ($check->person->is_organiser() && $roles[self::ROLE_ORGANISER]) {
            $plugin->enrol_user($instance, $check->existing->id, $roles[self::ROLE_ORGANISER], $order->startdate);
        }

        $result->reused++;
    }

    /**
     * The two roles an order knows, by id.
     *
     * @return array role short name => id, zero when the role is gone
     */
    protected static function role_ids(): array {
        global $DB;

        $ids = [];
        foreach ([self::ROLE_PARTICIPANT, self::ROLE_ORGANISER] as $shortname) {
            $ids[$shortname] = (int) $DB->get_field('role', 'id', ['shortname' => $shortname]);
        }

        return $ids;
    }

    /**
     * Write the order down.
     *
     * @param stdClass $order
     * @param stdClass $course
     * @param check[] $checks
     * @param stdClass $result
     */
    protected static function record(stdClass $order, stdClass $course, array $checks, stdClass $result): void {
        global $DB, $USER;

        $record = (object) [
            'courseid' => $course->id,
            'company' => $order->company,
            'abnr' => $order->abnr,
            'module' => $order->module,
            'shortname' => $order->shortname,
            'startdate' => $order->startdate,
            'enddate' => $order->enddate,
            'usernamescheme' => $order->scheme,
            'delivery' => $order->delivery,
            // Nothing is held back yet, so the credentials are on their way
            // as soon as the order is made.
            'senddate' => 0,
            'timesent' => ($order->delivery === self::DELIVERY_DIRECT) ? time() : 0,
            'usercreated' => $USER->id,
            'timecreated' => time(),
        ];
        $result->orderid = $DB->insert_record('local_coursesoverview_order', $record);

        foreach ($checks as $check) {
            if (!$check->userid) {
                continue;
            }

            $DB->insert_record('local_coursesoverview_orderuser', (object) [
                'orderid' => $result->orderid,
                'userid' => $check->userid,
                'role' => $check->person->role,
                'reused' => $check->reuse ? 1 : 0,
                'timesent' => $record->timesent,
            ]);
        }
    }

    /**
     * One line of a semicolon separated file.
     *
     * Everything is quoted, so that a company with a semicolon in its name
     * cannot shift the columns.
     *
     * @param array $values
     * @return string
     */
    protected static function csv_line(array $values): string {
        $quoted = [];
        foreach ($values as $value) {
            $quoted[] = '"' . str_replace('"', '""', (string) $value) . '"';
        }

        return implode(';', $quoted) . "\n";
    }
}
