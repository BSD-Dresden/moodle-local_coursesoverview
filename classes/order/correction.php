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
use stdClass;

/**
 * Put right a name or an address the customer sent wrongly.
 *
 * "Mirko" for "Mirco" happens, and the mail with the credentials then comes
 * back undelivered. Deleting the account and ordering it again loses the
 * enrolment, the groups and whatever the person has done so far. Here the
 * account stays and only its name, its address and the user name built from
 * them change, after which new credentials can go to the right address.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class correction {
    /** @var string new credentials go to the corrected address */
    const RESEND_DIRECT = 'direct';

    /** @var string new credentials are shown once, to be passed on by hand */
    const RESEND_LIST = 'list';

    /** @var string the credentials stay as they are */
    const RESEND_NONE = 'none';

    /** @var stdClass the order */
    public $order;

    /** @var stdClass the person's line of the order */
    public $row;

    /** @var stdClass the account as it is */
    public $user;

    /** @var participant the person as corrected */
    public $person;

    /** @var string the user name the account ends up with */
    public $username = '';

    /** @var string[] what stops the correction */
    public $errors = [];

    /** @var string[] what is worth knowing before saving */
    public $notes = [];

    /**
     * Hold a correction against the account and the rest of the site.
     *
     * @param stdClass $order
     * @param stdClass $row the person's record in local_coursesoverview_orderuser
     * @param stdClass $user the full user record
     * @param string $firstname
     * @param string $lastname
     * @param string $email
     */
    public function __construct(
        stdClass $order,
        stdClass $row,
        stdClass $user,
        string $firstname,
        string $lastname,
        string $email
    ) {
        $this->order = $order;
        $this->row = $row;
        $this->user = $user;

        $this->person = new participant(0);
        $this->person->firstname = trim($firstname);
        $this->person->lastname = trim($lastname);
        $this->person->email = core_text::strtolower(trim($email));

        $this->check();
    }

    /**
     * Whether anything would change at all.
     *
     * @return bool
     */
    public function changes(): bool {
        return $this->person->firstname !== $this->user->firstname
            || $this->person->lastname !== $this->user->lastname
            || $this->person->email !== core_text::strtolower($this->user->email)
            || $this->username !== $this->user->username;
    }

    /**
     * Whether the credentials of this person are still waiting for the start day.
     *
     * @return bool
     */
    public function held(): bool {
        return $this->row->delivery === provision::DELIVERY_DIRECT
            && !$this->row->reused
            && !$this->row->timesent
            && $this->row->senddate > time();
    }

    /**
     * Whether new credentials can be handed out at all.
     *
     * An account that was used again belongs to the person beyond this
     * order, and credentials that are still waiting go to the corrected
     * address on their own.
     *
     * @return bool
     */
    public function can_resend(): bool {
        return !$this->row->reused && !$this->held();
    }

    /**
     * Work out the user name and everything that speaks against the correction.
     */
    protected function check(): void {
        foreach ($this->person->problems() as $problem) {
            $this->errors[] = $problem;
        }
        if ($this->errors) {
            $this->username = $this->user->username;
            return;
        }

        // An account used again is known by its user name elsewhere, so only
        // the accounts this order created follow their corrected name.
        if ($this->row->reused) {
            $this->username = $this->user->username;
        } else {
            $this->username = validator::username($this->person, $this->order->usernamescheme);
        }

        if ($this->username === '') {
            $this->errors[] = get_string('ordernousername', 'local_coursesoverview');
            return;
        }

        if ($this->username !== $this->user->username) {
            $taken = validator::accounts([$this->username]);
            if (isset($taken[$this->username]) && (int) $taken[$this->username]->id !== (int) $this->user->id) {
                $this->errors[] = get_string('correcttaken', 'local_coursesoverview', s($this->username));
            } else if ($this->user->lastlogin || $this->user->currentlogin) {
                $this->notes[] = get_string('correctloggedin', 'local_coursesoverview', s($this->username));
            }
        }
    }

    /**
     * Save the correction and hand out new credentials if asked to.
     *
     * @param string $resend one of the RESEND_ constants
     * @return stdClass saved, mailed, the password to show for a list, and the errors
     */
    public function save(string $resend): stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/editlib.php');

        $result = (object) ['saved' => false, 'mailed' => false, 'password' => '', 'errors' => []];
        if ($this->errors) {
            return $result;
        }

        if ($this->changes()) {
            $updated = (object) [
                'id' => $this->user->id,
                'firstname' => $this->person->firstname,
                'lastname' => $this->person->lastname,
                'email' => $this->person->email,
                'username' => $this->username,
            ];
            user_update_user($updated, false);

            // A bounced address counts against the account until it changes.
            useredit_update_bounces($this->user, $updated);
            $result->saved = true;
        }

        if (!$this->can_resend() || $resend === self::RESEND_NONE) {
            return $result;
        }

        $user = $DB->get_record('user', ['id' => $this->user->id], '*', MUST_EXIST);
        $course = $this->order->courseid ? $DB->get_record('course', ['id' => $this->order->courseid]) : null;

        if ($resend === self::RESEND_DIRECT && $course) {
            // Due now. Should the mail fail, the hourly task tries again.
            $now = time();
            $this->update_row(provision::DELIVERY_DIRECT, $now, 0);
            if (credentials::send($user, $this->order, $course)) {
                $this->update_row(provision::DELIVERY_DIRECT, $now, time());
                $result->mailed = true;
            } else {
                $result->errors[] = get_string('correctmailfailed', 'local_coursesoverview');
            }
        } else if ($resend === self::RESEND_LIST) {
            $result->password = provision::password();
            update_internal_user_password($user, $result->password);
            $this->update_row(provision::DELIVERY_LIST, 0, 0);
        }

        return $result;
    }

    /**
     * Note down how this person's credentials went out.
     *
     * @param string $delivery
     * @param int $senddate
     * @param int $timesent
     */
    protected function update_row(string $delivery, int $senddate, int $timesent): void {
        global $DB;

        $this->row->delivery = $delivery;
        $this->row->senddate = $senddate;
        $this->row->timesent = $timesent;
        $DB->update_record('local_coursesoverview_orderuser', (object) [
            'id' => $this->row->id,
            'delivery' => $delivery,
            'senddate' => $senddate,
            'timesent' => $timesent,
        ]);
    }
}
