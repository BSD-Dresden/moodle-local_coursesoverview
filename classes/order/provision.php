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
 * Nothing here reimplements what Moodle can already do. The course is a copy of
 * the module's template, made by the same task as "Copy course", and the
 * accounts come from the user import, fed a file that is built here and never
 * touches the disk. What this class owns is the order around them: that
 * the welcome message is silenced before anybody is enrolled, that a
 * deliberately reused account is enrolled rather than created, that mailed
 * credentials wait for a later start, and that the order is written down
 * afterwards. People reported late go through the same steps, minus the copy.
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
     * Create the course, the accounts and the enrolments of a new order.
     *
     * @param stdClass $order company, abnr, module, shortname, fullname, startdate, enddate, scheme, delivery
     * @param module $module
     * @param check[] $checks the lines, already checked and free of errors
     * @return stdClass see result()
     */
    public static function run(stdClass $order, module $module, array $checks): stdClass {
        global $DB;

        self::requires();
        $result = self::result();

        if ($DB->record_exists('course', ['shortname' => $order->shortname])) {
            $result->errors[] = get_string('ordercourseexists', 'local_coursesoverview', s($order->shortname));
            return $result;
        }

        $course = self::create_course($order, $module, $result);
        if (!$course) {
            return $result;
        }
        $result->courseid = (int) $course->id;

        $batch = self::batch($order->abnr, $order->delivery, $order->startdate);
        if (!self::fill($course, $batch, $checks, $result)) {
            return $result;
        }

        $result->orderid = self::record_order($order, $course, $batch);
        self::record_people($result->orderid, $checks, $batch);

        return $result;
    }

    /**
     * Add people reported late to an order that already has its course.
     *
     * They start on the order's start day, or today when that has passed, and
     * their credentials go out however this report asks, which need not be
     * the way the order went.
     *
     * @param stdClass $order the order as it is stored
     * @param stdClass $course its course
     * @param check[] $checks the lines, already checked and free of errors
     * @param string $delivery one of the DELIVERY_ constants
     * @return stdClass see result()
     */
    public static function add(stdClass $order, stdClass $course, array $checks, string $delivery): stdClass {
        self::requires();
        $result = self::result();
        $result->courseid = (int) $course->id;
        $result->orderid = (int) $order->id;

        $start = max((int) $order->startdate, usergetmidnight(time()));
        $batch = self::batch($order->abnr, $delivery, $start);
        if (self::fill($course, $batch, $checks, $result)) {
            self::record_people($result->orderid, $checks, $batch);
        }

        return $result;
    }

    /**
     * Load what the imports need.
     */
    protected static function requires(): void {
        global $CFG;

        require_once($CFG->libdir . '/csvlib.class.php');
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/' . $CFG->admin . '/tool/uploaduser/locallib.php');
    }

    /**
     * An empty account of what happened.
     *
     * @return stdClass courseid, orderid, created, reused, held and the day
     *      they are sent, errors, and the credentials of a list
     */
    protected static function result(): stdClass {
        return (object) [
            'courseid' => 0,
            'orderid' => 0,
            'created' => 0,
            'reused' => 0,
            'held' => 0,
            'senddate' => 0,
            'errors' => [],
            'credentials' => [],
        ];
    }

    /**
     * What the people handled in one go have in common.
     *
     * @param string $abnr
     * @param string $delivery one of the DELIVERY_ constants
     * @param int $start the day their enrolment begins
     * @return stdClass
     */
    protected static function batch(string $abnr, string $delivery, int $start): stdClass {
        return (object) [
            'abnr' => $abnr,
            'delivery' => $delivery,
            'start' => $start,
            // Mailed credentials for a later start wait for the task. Until
            // then the account carries a password nobody is told.
            'held' => $delivery === self::DELIVERY_DIRECT && $start > usergetmidnight(time()),
        ];
    }

    /**
     * Enrol a batch in a course, creating the accounts that are new.
     *
     * @param stdClass $course
     * @param stdClass $batch
     * @param check[] $checks
     * @param stdClass $result
     * @return bool false when the course cannot take anybody
     */
    protected static function fill(stdClass $course, stdClass $batch, array $checks, stdClass $result): bool {
        $instance = self::silence_welcome((int) $course->id);
        if (!$instance) {
            $result->errors[] = get_string('ordernomanualenrol', 'local_coursesoverview');
            return false;
        }

        self::import_users($course, $batch, $checks, $result);

        foreach ($checks as $check) {
            if ($check->reuse && $check->existing) {
                self::enrol_existing($instance, $batch, $check, $result);
            }
        }

        if ($batch->held) {
            $result->held = $result->created;
            $result->senddate = $batch->start;
        }

        return true;
    }

    /**
     * Copy the module's template into a new course, the way "Copy course" does.
     *
     * The course import would have been less code but is the wrong tool. It
     * creates a course with the site's defaults and only pours the template's
     * content into it, so completion tracking stays off, the course blocks are
     * lost and a news forum turns up that the template does not have. A copy
     * restores the template as a whole, which is how these courses have always
     * been made by hand.
     *
     * Moodle runs a copy as a background task. Here the same task is run
     * straight away instead, so that the accounts can follow in the same
     * request. The controllers are prepared exactly as copy_helper does it,
     * with one difference: the session is not released, because the list of
     * credentials still has to go into it afterwards.
     *
     * @param stdClass $order
     * @param module $module
     * @param stdClass $result collects the errors
     * @return stdClass|null the new course
     */
    protected static function create_course(stdClass $order, module $module, stdClass $result): ?stdClass {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $template = $module->template_course();
        $copydata = (object) [
            'courseid' => $template->id,
            'fullname' => $order->fullname,
            'shortname' => $order->shortname,
            'category' => $module->category(),
            'visible' => 1,
            'startdate' => $order->startdate,
            'enddate' => $order->enddate,
            'idnumber' => $order->abnr,
            'userdata' => 0,
            'keptroles' => [],
        ];

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);

        $backup = new \backup_controller(
            \backup::TYPE_1COURSE,
            $template->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_COPY,
            $USER->id,
            \backup::RELEASESESSION_NO
        );
        $ids = ['backupid' => $backup->get_backupid()];

        // The copy starts as a placeholder course and receives its names at the end.
        [$fullname, $shortname] = \restore_dbops::calculate_course_names(
            0,
            get_string('copyingcourse', 'backup'),
            get_string('copyingcourseshortname', 'backup')
        );
        $courseid = \restore_dbops::create_new_course($fullname, $shortname, $copydata->category);

        $restore = new \restore_controller(
            $ids['backupid'],
            $courseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_COPY,
            $USER->id,
            \backup::TARGET_NEW_COURSE,
            null,
            \backup::RELEASESESSION_NO,
            $copydata
        );
        $ids['restoreid'] = $restore->get_restoreid();

        $backup->set_status(\backup::STATUS_AWAITING);
        $backup->get_status();
        $restore->save_controller();
        $backup->destroy();

        // The task reports through mtrace(), which only matters when it fails.
        $task = new \core\task\asynchronous_copy_task();
        $task->set_custom_data($ids);
        ob_start();
        try {
            $task->execute();
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        } finally {
            $log = ob_get_clean();
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || $course->shortname !== $order->shortname) {
            // A copy that stopped halfway leaves its placeholder behind.
            if ($course) {
                delete_course($course, false);
            }
            $result->errors[] = get_string('ordercoursefailed', 'local_coursesoverview');
            if (trim(strip_tags($log)) !== '') {
                $result->errors[] = trim(strip_tags($log));
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
     * @param stdClass $course
     * @param stdClass $batch
     * @param check[] $checks
     * @param stdClass $result
     */
    protected static function import_users(stdClass $course, stdClass $batch, array $checks, stdClass $result): void {
        [$content, $passwords, $wanted] = self::user_file($course, $batch, $checks);
        if (!$wanted) {
            return;
        }

        self::run_import($content, $batch, $result);
        self::collect($checks, $wanted, $passwords, $batch, $result);
    }

    /**
     * The file the user import is fed with.
     *
     * @param stdClass $course
     * @param stdClass $batch
     * @param check[] $checks
     * @return array the content, the passwords by user name, and the user names it holds
     */
    protected static function user_file(stdClass $course, stdClass $batch, array $checks): array {
        global $DB;

        // A password goes into the file when the list hands it out, and when
        // the mail is held back: without one, the import would have the
        // credentials mailed the same night.
        $withpassword = $batch->delivery === self::DELIVERY_LIST || $batch->held;
        $withabnr = $DB->record_exists('user_info_field', ['shortname' => self::PROFILE_FIELD]);
        $start = date('Y-m-d', $batch->start);

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
                $passwords[$check->username] = self::password();
                $values[] = $passwords[$check->username];
            }

            // The participant side and the organiser side are two enrolments
            // in the same course, which is how somebody can be both.
            $participant = $person->is_participant();
            $organiser = $person->is_organiser();
            $values[] = $participant ? $course->shortname : '';
            $values[] = $participant ? self::ROLE_PARTICIPANT : '';
            $values[] = $participant ? $start : '';
            $values[] = $organiser ? $course->shortname : '';
            $values[] = $organiser ? self::ROLE_ORGANISER : '';
            $values[] = $organiser ? $start : '';

            if ($withabnr) {
                $values[] = $batch->abnr;
            }

            $content .= self::csv_line($values);
            $wanted[] = $check->username;
        }

        return [$content, $passwords, $wanted];
    }

    /**
     * Run the user import on a file.
     *
     * @param string $content
     * @param stdClass $batch
     * @param stdClass $result collects what the import calls an error
     */
    protected static function run_import(string $content, stdClass $batch, stdClass $result): void {
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
            $process->set_form_data(self::form_data($batch));
            $process->process();
        } catch (\Throwable $e) {
            $result->errors[] = $e->getMessage();
        }
        $reader->cleanup();

        foreach (capture_tracker::errors() as $error) {
            $result->errors[] = $error;
        }
    }

    /**
     * Find out what the import actually created.
     *
     * @param check[] $checks
     * @param string[] $wanted the user names that were in the file
     * @param array $passwords user name => password
     * @param stdClass $batch
     * @param stdClass $result
     */
    protected static function collect(
        array $checks,
        array $wanted,
        array $passwords,
        stdClass $batch,
        stdClass $result
    ): void {
        // What counts as created is what is in the database afterwards.
        $accounts = validator::accounts($wanted);
        $result->created = count($accounts);

        foreach ($checks as $check) {
            if ($check->reuse) {
                continue;
            }
            if (!isset($accounts[$check->username])) {
                $result->errors[] = get_string('ordernotcreated', 'local_coursesoverview', s($check->username));
                continue;
            }

            $check->userid = (int) $accounts[$check->username]->id;

            // Only the list hands passwords out. A held one is never shown.
            if ($batch->delivery === self::DELIVERY_LIST) {
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
        }
    }

    /**
     * The settings the user import would otherwise take from its form.
     *
     * @param stdClass $batch
     * @return stdClass
     */
    protected static function form_data(stdClass $batch): stdClass {
        $mailnow = $batch->delivery === self::DELIVERY_DIRECT && !$batch->held;

        return (object) [
            // Existing accounts have been ruled out before this point, so
            // skipping them here is a second net rather than a decision.
            'uutype' => UU_USER_ADDNEW,
            'uuupdatetype' => UU_UPDATE_NOCHANGES,
            // Without a password in the file the import hands the account to
            // the cron task that mails the credentials. With one it stores it
            // quietly, which is what the list and a held mail need.
            'uupasswordnew' => $mailnow ? 1 : 0,
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
     * @param stdClass $batch
     * @param check $check
     * @param stdClass $result
     */
    protected static function enrol_existing(stdClass $instance, stdClass $batch, check $check, stdClass $result): void {
        $plugin = enrol_get_plugin('manual');
        $roles = self::role_ids();
        $check->userid = (int) $check->existing->id;

        if ($check->person->is_participant() && $roles[self::ROLE_PARTICIPANT]) {
            $plugin->enrol_user($instance, $check->userid, $roles[self::ROLE_PARTICIPANT], $batch->start);
        }
        if ($check->person->is_organiser() && $roles[self::ROLE_ORGANISER]) {
            $plugin->enrol_user($instance, $check->userid, $roles[self::ROLE_ORGANISER], $batch->start);
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
     * @param stdClass $batch
     * @return int the id of the order
     */
    protected static function record_order(stdClass $order, stdClass $course, stdClass $batch): int {
        global $DB, $USER;

        $mailnow = $batch->delivery === self::DELIVERY_DIRECT && !$batch->held;

        return (int) $DB->insert_record('local_coursesoverview_order', (object) [
            'courseid' => $course->id,
            'company' => $order->company,
            'abnr' => $order->abnr,
            'module' => $order->module,
            'shortname' => $order->shortname,
            'startdate' => $order->startdate,
            'enddate' => $order->enddate,
            'usernamescheme' => $order->scheme,
            'delivery' => $order->delivery,
            'senddate' => $batch->held ? $batch->start : 0,
            'timesent' => $mailnow ? time() : 0,
            'usercreated' => $USER->id,
            'timecreated' => time(),
        ]);
    }

    /**
     * Write down who belongs to an order, and when their credentials go out.
     *
     * @param int $orderid
     * @param check[] $checks
     * @param stdClass $batch
     */
    protected static function record_people(int $orderid, array $checks, stdClass $batch): void {
        global $DB;

        foreach ($checks as $check) {
            if (!$check->userid) {
                continue;
            }

            // Somebody who keeps an existing account gets no credentials at all.
            $mailed = $batch->delivery === self::DELIVERY_DIRECT && !$check->reuse;

            $DB->insert_record('local_coursesoverview_orderuser', (object) [
                'orderid' => $orderid,
                'userid' => $check->userid,
                'role' => $check->person->role,
                'reused' => $check->reuse ? 1 : 0,
                'delivery' => $batch->delivery,
                'senddate' => ($mailed && $batch->held) ? $batch->start : 0,
                'timesent' => ($mailed && !$batch->held) ? time() : 0,
            ]);
        }
    }

    /**
     * A password that can be read off a printed list.
     *
     * Moodle's own generator mixes punctuation into the filler as well, which
     * gives passwords like "q9R,Mp;pov": a comma and a semicolon look like
     * punctuation on paper, and l, 1, I, O and 0 are easily mistaken for each
     * other. Here the alphabet leaves those out, punctuation is used only as
     * often as the password policy demands, and the randomness comes from
     * random_int() rather than str_shuffle().
     *
     * @return string
     */
    public static function password(): string {
        global $CFG;

        $sets = [
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'lower' => 'abcdefghijkmnpqrstuvwxyz',
            'digit' => '23456789',
            'other' => '!?#+-',
        ];

        $policy = !empty($CFG->passwordpolicy);
        $need = [
            'upper' => max(1, $policy ? (int) $CFG->minpasswordupper : 0),
            'lower' => max(1, $policy ? (int) $CFG->minpasswordlower : 0),
            'digit' => max(1, $policy ? (int) $CFG->minpassworddigits : 0),
            'other' => $policy ? (int) $CFG->minpasswordnonalphanum : 0,
        ];
        $length = max(10, $policy ? (int) $CFG->minpasswordlength : 0, array_sum($need));
        $filler = $sets['upper'] . $sets['lower'] . $sets['digit'];

        // The policy may know rules beyond the counts, so the result is
        // checked against it, and Moodle's generator is the last resort.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $chars = [];
            foreach ($need as $set => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $chars[] = self::pick($sets[$set]);
                }
            }
            while (count($chars) < $length) {
                $chars[] = self::pick($filler);
            }

            for ($i = count($chars) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
            }

            $password = implode('', $chars);
            $errmsg = '';
            if (check_password_policy($password, $errmsg)) {
                return $password;
            }
        }

        return generate_password($length);
    }

    /**
     * One character out of a set, picked with a secure random number.
     *
     * @param string $set
     * @return string
     */
    protected static function pick(string $set): string {
        return $set[random_int(0, strlen($set) - 1)];
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
