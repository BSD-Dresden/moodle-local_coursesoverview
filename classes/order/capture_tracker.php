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

/**
 * Collect what the user import reports instead of printing it.
 *
 * The tracker the import brings along writes its table straight to the
 * output, which would land in the middle of our own page. This one keeps the
 * lines so that the result page can say what happened in its own words.
 *
 * The lines are held statically because the import is handed a class name and
 * builds the tracker itself: there is no instance to ask afterwards. Call
 * reset() right before starting an import and read the lines after it.
 *
 * Requires admin/tool/uploaduser/locallib.php to have been loaded, which is
 * what defines the parent.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class capture_tracker extends \uu_progress_tracker {

    /** @var array one entry per line of the last import */
    protected static $lines = [];

    /**
     * Forget the previous import.
     */
    public static function reset(): void {
        self::$lines = [];
    }

    /**
     * What the last import reported, one entry per line.
     *
     * @return array
     */
    public static function lines(): array {
        return self::$lines;
    }

    /**
     * The lines the last import called an error, ready to show.
     *
     * @return string[]
     */
    public static function errors(): array {
        $errors = [];

        foreach (self::$lines as $line) {
            $messages = [];
            foreach ($line as $field) {
                if ($field['error'] !== '') {
                    $messages[] = self::plain($field['error']);
                }
            }
            if ($messages) {
                $errors[] = self::plain($line['line']['normal']) . ': ' . implode(' ', $messages);
            }
        }

        return $errors;
    }

    /**
     * Nothing to open.
     */
    public function start() {
        $this->_row = null;
    }

    /**
     * Keep the finished line and start the next one.
     */
    public function flush() {
        if (!empty($this->_row) && !empty($this->_row['line']['normal'])) {
            self::$lines[] = $this->_row;
        }

        $this->_row = [];
        foreach ($this->columns as $column) {
            $this->_row[$column] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
        }
    }

    /**
     * Nothing to close, but the last line still wants keeping.
     */
    public function close() {
        $this->flush();
    }

    /**
     * Turn the markup the tracker collects into something readable.
     *
     * @param string $text
     * @return string
     */
    protected static function plain(string $text): string {
        return trim(html_to_text(str_replace('<br />', ' ', $text), 0, false));
    }
}
