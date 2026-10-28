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

use core_text;
use local_coursesoverview\helper;

/**
 * Hold the read list against the database before anything is created.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /** @var string the address doubles as the user name */
    const SCHEME_EMAIL = 'email';

    /** @var string first name and surname make the user name */
    const SCHEME_NAME = 'name';

    /**
     * Work out the user names and everything that speaks against them.
     *
     * @param participant[] $people as read from the list
     * @param string $scheme one of the SCHEME_ constants
     * @param array $roles role per index, as the form posted it
     * @param array $reuse indexes whose existing account may be used again
     * @return check[]
     */
    public static function run(array $people, string $scheme, array $roles = [], array $reuse = []): array {
        $checks = [];
        $usernames = [];
        $emails = [];

        foreach (array_values($people) as $index => $person) {
            if (isset($roles[$index]) && array_key_exists($roles[$index], participant::roles())) {
                $person->role = $roles[$index];
            }

            $check = new check($person, $index);
            $check->username = self::username($person, $scheme);

            foreach ($person->problems() as $problem) {
                $check->errors[] = $problem;
            }

            if ($check->username === '') {
                $check->errors[] = get_string('ordernousername', 'local_coursesoverview');
            } else {
                $usernames[$check->username][] = $index;
            }
            if ($person->email !== '') {
                $emails[$person->email][] = $index;
            }

            $checks[$index] = $check;
        }

        self::flag_duplicates($checks, $usernames, $emails);
        self::flag_existing($checks, array_keys($usernames), array_keys($emails), $reuse);

        return $checks;
    }

    /**
     * The user name a person would get.
     *
     * @param participant $person
     * @param string $scheme
     * @return string empty when there is nothing to build it from
     */
    public static function username(participant $person, string $scheme): string {
        if ($scheme === self::SCHEME_EMAIL) {
            $username = $person->email;
        } else {
            $username = module::clean($person->firstname) . '.' . module::clean($person->lastname);
        }

        $username = core_text::strtolower(trim($username, '.'));

        // Moodle only accepts this set unless extended user names are on, and
        // turning that on for our accounts would be a trap rather than a help.
        $username = preg_replace('/[^a-z0-9_.@-]/', '', $username);

        return $username === '.' ? '' : $username;
    }

    /**
     * Mark the lines that collide with each other.
     *
     * @param check[] $checks keyed by index, modified in place
     * @param array $usernames user name => list of indexes
     * @param array $emails address => list of indexes
     */
    protected static function flag_duplicates(array $checks, array $usernames, array $emails): void {
        foreach ($usernames as $username => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $index) {
                $checks[$index]->errors[] = get_string('orderduplicateusername', 'local_coursesoverview', s($username));
            }
        }

        // Several people behind one mailbox is the normal case here, so this
        // is said out loud but does not stop anything.
        foreach ($emails as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            foreach ($indexes as $index) {
                $checks[$index]->notes[] = get_string('orderduplicateemail', 'local_coursesoverview', count($indexes));
            }
        }
    }

    /**
     * Mark the lines whose account or address is already in Moodle.
     *
     * @param check[] $checks keyed by index, modified in place
     * @param string[] $usernames
     * @param string[] $emails
     * @param array $reuse indexes the form allows to be used again
     */
    protected static function flag_existing(array $checks, array $usernames, array $emails, array $reuse): void {
        $accounts = self::accounts($usernames);
        $mailcounts = self::mailcounts($emails);
        // The accounts are keyed by user name, the enrolments are found by id.
        $courses = $accounts ? self::courses(array_column($accounts, 'id')) : [];

        foreach ($checks as $index => $check) {
            if ($check->username !== '' && isset($accounts[$check->username])) {
                $account = $accounts[$check->username];
                $check->existing = $account;

                $a = (object) [
                    'username' => s($check->username),
                    'name' => s(fullname($account)),
                    'created' => helper::format_date($account->timecreated, '?'),
                ];
                $message = get_string('orderexists', 'local_coursesoverview', $a);

                if (!empty($courses[$account->id])) {
                    $shortnames = array_slice($courses[$account->id], 0, 3);
                    $message .= ' ' . get_string(
                        'orderexistscourses',
                        'local_coursesoverview',
                        s(implode(', ', $shortnames))
                    );
                }

                if (!$check->may_reuse()) {
                    $check->errors[] = $message . ' ' . get_string('orderreusehint', 'local_coursesoverview');
                } else if (!empty($reuse[$index])) {
                    $check->reuse = true;
                    $check->notes[] = $message . ' ' . get_string('orderreused', 'local_coursesoverview');
                } else {
                    $check->errors[] = $message;
                }
            }

            $email = $check->person->email;
            if ($email !== '' && !empty($mailcounts[$email])) {
                $check->notes[] = get_string('ordermailtaken', 'local_coursesoverview', $mailcounts[$email]);
            }
        }
    }

    /**
     * The accounts that already carry one of these user names.
     *
     * @param string[] $usernames
     * @return array user name => user record
     */
    public static function accounts(array $usernames): array {
        global $DB, $CFG;

        if (!$usernames) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED, 'u');
        $params['mnet'] = $CFG->mnet_localhost_id;

        $fields = 'id, username, firstname, lastname, email, timecreated, suspended';
        $sql = "SELECT $fields
                  FROM {user}
                 WHERE deleted = 0
                       AND mnethostid = :mnet
                       AND username $insql";

        $accounts = [];
        foreach ($DB->get_records_sql($sql, $params) as $account) {
            $accounts[$account->username] = $account;
        }

        return $accounts;
    }

    /**
     * How many accounts each of these addresses already belongs to.
     *
     * @param string[] $emails
     * @return array address => count
     */
    protected static function mailcounts(array $emails): array {
        global $DB, $CFG;

        if (!$emails) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'e');
        $params['mnet'] = $CFG->mnet_localhost_id;

        $sql = "SELECT email, COUNT(id) AS accounts
                  FROM {user}
                 WHERE deleted = 0
                       AND mnethostid = :mnet
                       AND email $insql
              GROUP BY email";

        $counts = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $counts[$record->email] = (int) $record->accounts;
        }

        return $counts;
    }

    /**
     * Which courses these accounts are enrolled in.
     *
     * @param int[] $userids
     * @return array user id => list of course short names
     */
    protected static function courses(array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'id');

        $sql = "SELECT ue.id, ue.userid, c.shortname
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ue.userid $insql
              ORDER BY c.startdate DESC";

        $courses = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $courses[$record->userid][] = $record->shortname;
        }

        return $courses;
    }
}
