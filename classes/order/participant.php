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

/**
 * One line of a customer's participant list, as it was read.
 *
 * Only what the text itself can answer lives here. Whether the account can be
 * created is a question for the database and is asked later.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class participant {
    /** @var string takes part, nothing more */
    const ROLE_PARTICIPANT = 'participant';

    /** @var string watches the progress of the others */
    const ROLE_ORGANISER = 'organiser';

    /** @var string both at once, which is the common case for a supervisor */
    const ROLE_BOTH = 'both';

    /** @var int line of the pasted list this came from, counted from one */
    public $rownumber;

    /** @var string */
    public $firstname = '';

    /** @var string */
    public $lastname = '';

    /** @var string */
    public $email = '';

    /** @var string one of the ROLE_ constants */
    public $role = self::ROLE_PARTICIPANT;

    /** @var string the course group this person goes into, empty for none */
    public $group = '';

    /**
     * Start an empty person for one line of the list.
     *
     * @param int $rownumber
     */
    public function __construct(int $rownumber) {
        $this->rownumber = $rownumber;
    }

    /**
     * The three roles as a select menu.
     *
     * @return array role => name
     */
    public static function roles(): array {
        return [
            self::ROLE_PARTICIPANT => get_string('roleparticipant', 'local_coursesoverview'),
            self::ROLE_ORGANISER => get_string('roleorganiser', 'local_coursesoverview'),
            self::ROLE_BOTH => get_string('roleboth', 'local_coursesoverview'),
        ];
    }

    /**
     * Read a role out of whatever the customer wrote in that column.
     *
     * @param string $raw
     * @return string one of the ROLE_ constants, participant when nothing matches
     */
    public static function read_role(string $raw): string {
        $value = core_text::strtolower(trim($raw));

        if ($value === '') {
            return self::ROLE_PARTICIPANT;
        }

        $organiser = (bool) preg_match('/organisator|organiser|vorgesetz|leiter|teacher|trainer/u', $value);
        $participant = (bool) preg_match('/teilnehmer|participant|student|\btn\b/u', $value);

        if ($organiser && $participant) {
            return self::ROLE_BOTH;
        }
        if ($organiser) {
            return self::ROLE_ORGANISER;
        }

        return self::ROLE_PARTICIPANT;
    }

    /**
     * Whether this person takes the course themselves.
     *
     * @return bool
     */
    public function is_participant(): bool {
        return $this->role === self::ROLE_PARTICIPANT || $this->role === self::ROLE_BOTH;
    }

    /**
     * Whether this person watches the course.
     *
     * @return bool
     */
    public function is_organiser(): bool {
        return $this->role === self::ROLE_ORGANISER || $this->role === self::ROLE_BOTH;
    }

    /**
     * Name as it will be shown.
     *
     * @return string
     */
    public function name(): string {
        return trim($this->firstname . ' ' . $this->lastname);
    }

    /**
     * What is wrong with this line on its own terms.
     *
     * @return string[] ready to show, empty when the line is sound
     */
    public function problems(): array {
        $problems = [];

        if ($this->firstname === '' || $this->lastname === '') {
            $problems[] = get_string('ordermissingname', 'local_coursesoverview');
        }

        if ($this->email === '') {
            $problems[] = get_string('ordermissingemail', 'local_coursesoverview');
        } else if (!validate_email($this->email)) {
            $problems[] = get_string('orderinvalidemail', 'local_coursesoverview', s($this->email));
        }

        return $problems;
    }
}
