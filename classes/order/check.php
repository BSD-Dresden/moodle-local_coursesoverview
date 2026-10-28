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
 * One line of the list together with the verdict on it.
 *
 * Errors block the whole order, notes are there to be read. The difference
 * matters: a shared mailbox is worth saying out loud but is perfectly normal,
 * while an account that already exists means somebody has to look.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check {
    /** @var participant the person this is about */
    public $person;

    /** @var int position in the list, and the key the form uses */
    public $index;

    /** @var string the user name that would be created */
    public $username = '';

    /** @var stdClass|null the account that already carries that user name */
    public $existing = null;

    /** @var bool the existing account is deliberately used again */
    public $reuse = false;

    /** @var int the account this line ended up with, once the order went through */
    public $userid = 0;

    /** @var string[] what stops the order */
    public $errors = [];

    /** @var string[] what is merely worth knowing */
    public $notes = [];

    /**
     * Start the verdict on one line, with nothing against it yet.
     *
     * @param participant $person
     * @param int $index
     */
    public function __construct(participant $person, int $index) {
        $this->person = $person;
        $this->index = $index;
    }

    /**
     * Whether this line stops the order from going through.
     *
     * @return bool
     */
    public function blocked(): bool {
        return (bool) $this->errors;
    }

    /**
     * Whether the person may keep the account that already exists.
     *
     * Participants never may. Two people of the same name are far more likely
     * than one person ordering the same module twice, and attaching a course
     * to a stranger's account is the one mistake that cannot be seen later.
     * A supervisor watching a second module is the case that does happen, and
     * the one this answers yes to.
     *
     * @return bool
     */
    public function may_reuse(): bool {
        return $this->existing && $this->person->is_organiser();
    }
}
