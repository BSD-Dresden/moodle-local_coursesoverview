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

/**
 * Scheduled task class.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursesoverview\task;

use completion_info;
use local_coursesoverview\helper;
use moodle_url;
use stdClass;

/**
 * Reports courses in which every tracked participant has completed.
 *
 * The overview page already paints these courses green and calls the state
 * "tosettle". This task turns the same state into a mail, so that certificates
 * can be prepared without watching the page.
 */
class notify_completed_courses extends \core\task\scheduled_task {
    /** @var string Config entry holding the course ids reported last time. */
    const REPORTED = 'reportedcourses';

    /**
     * Name shown in the scheduled task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasknotifycompleted', 'local_coursesoverview');
    }

    /**
     * Compare the set of fully completed courses against the previous run.
     */
    public function execute(): void {
        $recipients = $this->recipients();
        if (!$recipients) {
            mtrace('No recipients configured, nothing to do.');
            return;
        }

        $complete = $this->complete_courses();
        $current = array_keys($complete);
        $stored = get_config('local_coursesoverview', self::REPORTED);

        if ($stored === false) {
            // First run. Record what is already complete instead of sending one
            // mail for every course that finished before this task existed.
            $this->remember($current);
            mtrace('First run: recorded ' . count($current) . ' completed course(s) without notifying.');
            return;
        }

        $reported = array_map('intval', (array) json_decode($stored));
        $new = array_diff($current, $reported);

        foreach ($new as $courseid) {
            $this->notify($complete[$courseid], $recipients);
        }

        // The current set is stored, not the union with the previous one. A
        // course that drops out of the state because somebody was enrolled
        // late is therefore reported again once it is complete a second time.
        $this->remember($current);
        mtrace(count($new) . ' course(s) newly complete, ' . count($current) . ' complete in total.');
    }

    /**
     * Every visible course in which all tracked participants have completed.
     *
     * @return array course records by id, with completedcount and totalcount added
     */
    protected function complete_courses(): array {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.shortname, c.enddate, c.enablecompletion, c.visible
                  FROM {course} c
                 WHERE c.id <> :siteid
                   AND c.visible = 1
                   AND c.enablecompletion = 1
              ORDER BY c.fullname ASC";
        $courses = $DB->get_records_sql($sql, ['siteid' => SITEID]);

        $completedwhere = 'u.id IN (SELECT cc.userid
                                      FROM {course_completions} cc
                                     WHERE cc.course = :covcourseid
                                       AND cc.timecompleted IS NOT NULL)';

        $complete = [];

        foreach ($courses as $course) {
            $completion = new completion_info($course);
            if (!$completion->is_enabled()) {
                continue;
            }

            $total = $completion->get_num_tracked_users();
            if ($total < 1) {
                continue;
            }

            $completed = $completion->get_num_tracked_users($completedwhere, ['covcourseid' => $course->id]);
            if ($completed < $total) {
                continue;
            }

            $course->completedcount = $completed;
            $course->totalcount = $total;
            $complete[(int) $course->id] = $course;
        }

        return $complete;
    }

    /**
     * Store the set of course ids that are complete right now.
     *
     * @param array $courseids
     */
    protected function remember(array $courseids): void {
        set_config(self::REPORTED, json_encode(array_values($courseids)), 'local_coursesoverview');
    }

    /**
     * Recipients built from the configured addresses.
     *
     * They need not be Moodle accounts: email_to_user() only insists on a
     * non-empty id and an address, so a shared mailbox works too.
     *
     * @return array of user-like objects
     */
    protected function recipients(): array {
        $setting = trim((string) get_config('local_coursesoverview', 'notifyemails'));
        if ($setting === '') {
            return [];
        }

        $recipients = [];

        foreach (preg_split('/[\s,;]+/', $setting, -1, PREG_SPLIT_NO_EMPTY) as $address) {
            if (!validate_email($address)) {
                mtrace("Skipping invalid address: {$address}");
                continue;
            }

            $user = new stdClass();
            $user->id = -99;
            $user->email = $address;
            $user->firstname = $address;
            $user->lastname = '';
            $user->username = $address;
            $user->maildisplay = 1;
            $user->mailformat = 1;
            $user->emailstop = 0;
            $user->deleted = 0;
            $user->suspended = 0;
            $user->auth = 'manual';
            $user->timezone = '99';
            $user->lang = '';
            $user->firstnamephonetic = '';
            $user->lastnamephonetic = '';
            $user->middlename = '';
            $user->alternatename = '';

            $recipients[] = $user;
        }

        return $recipients;
    }

    /**
     * Send one mail about one course.
     *
     * @param stdClass $course course record with completedcount and totalcount
     * @param array $recipients
     */
    protected function notify(stdClass $course, array $recipients): void {
        $url = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);

        $a = new stdClass();
        $a->fullname = format_string($course->fullname);
        $a->shortname = format_string($course->shortname);
        $a->total = $course->totalcount;
        $a->enddate = helper::format_date($course->enddate);
        $a->url = $url->out(false);

        $subject = get_string('notifysubject', 'local_coursesoverview', $a->fullname);
        $text = get_string('notifybody', 'local_coursesoverview', $a);
        $html = text_to_html($text, false, false, true);

        $from = \core_user::get_noreply_user();

        foreach ($recipients as $recipient) {
            if (!email_to_user($recipient, $from, $subject, $text, $html)) {
                mtrace("Could not mail {$recipient->email} about course {$course->id}.");
            }
        }

        mtrace("Reported course {$course->id}: {$course->fullname}");
    }
}
