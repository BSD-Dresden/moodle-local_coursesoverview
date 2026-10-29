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
 * Turn a pasted participant list into rows and columns.
 *
 * Customers send their lists in whatever their office produces, so the aim
 * here is not to understand the list but to cut it up reliably. What the
 * columns mean is decided afterwards, by hand if need be.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parser {
    /** @var string work the separator out from the text */
    const AUTO = 'auto';

    /** @var string what every spreadsheet puts on the clipboard */
    const TAB = 'tab';

    /** @var string */
    const SEMICOLON = 'semicolon';

    /** @var string */
    const COMMA = 'comma';

    /** @var string runs of blanks, for lists that were never a table */
    const SPACE = 'space';

    /**
     * The separators as a select menu.
     *
     * @return array key => name
     */
    public static function delimiters(): array {
        return [
            self::AUTO => get_string('delimiterauto', 'local_coursesoverview'),
            self::TAB => get_string('delimitertab', 'local_coursesoverview'),
            self::SEMICOLON => get_string('delimitersemicolon', 'local_coursesoverview'),
            self::COMMA => get_string('delimitercomma', 'local_coursesoverview'),
            self::SPACE => get_string('delimiterspace', 'local_coursesoverview'),
        ];
    }

    /**
     * The lines of the pasted text, blank ones dropped.
     *
     * @param string $text
     * @return string[]
     */
    public static function lines(string $text): array {
        $lines = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Guess which separator the list uses.
     *
     * A tab wins outright, because nothing else puts one in a pasted list.
     * After that the candidate has to appear on every line, otherwise it is
     * punctuation inside a field rather than a separator between fields.
     * Only the lines with an address count when there are any, because a
     * group heading or a title on its own has nothing to separate.
     *
     * @param string $text
     * @return string one of the constants, never AUTO
     */
    public static function detect(string $text): string {
        $lines = self::lines($text);
        if (!$lines) {
            return self::TAB;
        }

        $people = array_filter($lines, function ($line) {
            return strpos($line, '@') !== false;
        });
        if ($people) {
            $lines = $people;
        }

        foreach ([self::TAB => "\t", self::SEMICOLON => ';', self::COMMA => ','] as $key => $character) {
            $everywhere = true;
            foreach ($lines as $line) {
                if (substr_count($line, $character) < 1) {
                    $everywhere = false;
                    break;
                }
            }
            if ($everywhere) {
                return $key;
            }
        }

        return self::SPACE;
    }

    /**
     * Cut the pasted text into a rectangle of cells.
     *
     * Short rows are padded, so that a column always means the same thing no
     * matter which row it is read from.
     *
     * @param string $text
     * @param string $delimiter one of the constants
     * @return array[] rows of trimmed strings
     */
    public static function rows(string $text, string $delimiter = self::AUTO): array {
        if ($delimiter === self::AUTO) {
            $delimiter = self::detect($text);
        }

        $rows = [];
        $width = 0;

        foreach (self::lines($text) as $line) {
            $cells = self::split($line, $delimiter);

            // A line of separators alone carries nothing.
            if (implode('', $cells) === '') {
                continue;
            }

            $width = max($width, count($cells));
            $rows[] = $cells;
        }

        foreach ($rows as $index => $cells) {
            $rows[$index] = array_pad($cells, $width, '');
        }

        return $rows;
    }

    /**
     * Whether the first row names the columns rather than holding a person.
     *
     * @param array[] $rows as returned by rows()
     * @return bool
     */
    public static function looks_like_header(array $rows): bool {
        if (!$rows) {
            return false;
        }

        $first = $rows[0];

        // An address in the first row settles it: that is a person, not a title.
        foreach ($first as $cell) {
            if (strpos($cell, '@') !== false) {
                return false;
            }
        }

        foreach ($first as $cell) {
            if (columnmap::read_header($cell) !== columnmap::IGNORE) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a row is the heading of a group rather than a person.
     *
     * Some customers sort their list under headings, one line with the name
     * of a department and the people below it. Such a line has a single
     * filled cell and no address.
     *
     * @param string[] $cells one row as returned by rows()
     * @return bool
     */
    public static function is_group_heading(array $cells): bool {
        $filled = array_filter($cells, function ($cell) {
            return $cell !== '';
        });

        return count($filled) === 1 && strpos(reset($filled), '@') === false;
    }

    /**
     * The group a heading row names.
     *
     * @param string[] $cells one row for which is_group_heading() holds
     * @return string
     */
    public static function group_name(array $cells): string {
        $name = trim(implode('', $cells), " \t:");

        // As long as the database takes it.
        return core_text::substr($name, 0, 254);
    }

    /**
     * The group headings of a list, by row number.
     *
     * Only a list that has addresses can have headings, otherwise every row
     * of a list of names would be one.
     *
     * @param array[] $rows as returned by rows()
     * @param bool $hasheader whether the first row names the columns
     * @return array row number counted from one => group name
     */
    public static function group_headings(array $rows, bool $hasheader): array {
        $addresses = false;
        foreach ($rows as $cells) {
            if (strpos(implode('', $cells), '@') !== false) {
                $addresses = true;
                break;
            }
        }
        if (!$addresses) {
            return [];
        }

        $headings = [];
        foreach ($rows as $index => $cells) {
            if (($hasheader && $index === 0) || !self::is_group_heading($cells)) {
                continue;
            }
            $headings[$index + 1] = self::group_name($cells);
        }

        return $headings;
    }

    /**
     * Split one line into cells.
     *
     * @param string $line
     * @param string $delimiter
     * @return string[]
     */
    protected static function split(string $line, string $delimiter): array {
        switch ($delimiter) {
            case self::TAB:
                $cells = explode("\t", $line);
                break;
            case self::SEMICOLON:
                $cells = explode(';', $line);
                break;
            case self::COMMA:
                $cells = explode(',', $line);
                break;
            default:
                $cells = preg_split('/\s{2,}|\t/u', $line);
                break;
        }

        return array_map([self::class, 'clean_cell'], $cells);
    }

    /**
     * Trim a cell and take off the quotes a csv export leaves behind.
     *
     * @param string $cell
     * @return string
     */
    protected static function clean_cell(string $cell): string {
        $cell = trim($cell);

        if (strlen($cell) > 1 && $cell[0] === '"' && substr($cell, -1) === '"') {
            $cell = trim(substr($cell, 1, -1));
        }

        return $cell;
    }
}
