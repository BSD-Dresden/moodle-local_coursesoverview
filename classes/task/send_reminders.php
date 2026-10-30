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

namespace local_coursesoverview\task;

use local_coursesoverview\order\reminder;

/**
 * Remind the participants of orders who have not completed their course.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_reminders extends \core\task\scheduled_task {
    /**
     * Name shown in the list of scheduled tasks.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendreminders', 'local_coursesoverview');
    }

    /**
     * Send what is due.
     */
    public function execute(): void {
        $sent = reminder::send_due(time());
        mtrace('Reminders sent: ' . $sent);
    }
}
