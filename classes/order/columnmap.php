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
 * What the columns of a pasted list mean.
 *
 * The guess below only has to be close. It is shown as a row of drop-downs
 * that can be corrected before anything is read, which is cheaper than
 * teaching the detection every shape a customer list can take.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class columnmap {
    /** @var string column nobody needs */
    const IGNORE = 'ignore';

    /** @var string */
    const FIRSTNAME = 'firstname';

    /** @var string */
    const LASTNAME = 'lastname';

    /** @var string surname and first name in one cell, separated by a comma */
    const LASTFIRST = 'lastfirst';

    /** @var string the whole name in one cell, separated by a blank */
    const FULLNAME = 'fullname';

    /** @var string */
    const EMAIL = 'email';

    /** @var string name and address in one cell, the way a mail client writes it */
    const NAMEMAIL = 'namemail';

    /** @var string */
    const ROLE = 'role';

    /**
     * The column meanings as a select menu.
     *
     * @return array type => name
     */
    public static function types(): array {
        return [
            self::IGNORE => get_string('colignore', 'local_coursesoverview'),
            self::FIRSTNAME => get_string('colfirstname', 'local_coursesoverview'),
            self::LASTNAME => get_string('collastname', 'local_coursesoverview'),
            self::LASTFIRST => get_string('collastfirst', 'local_coursesoverview'),
            self::FULLNAME => get_string('colfullname', 'local_coursesoverview'),
            self::EMAIL => get_string('colemail', 'local_coursesoverview'),
            self::NAMEMAIL => get_string('colnamemail', 'local_coursesoverview'),
            self::ROLE => get_string('colrole', 'local_coursesoverview'),
        ];
    }

    /**
     * What a column title says the column holds.
     *
     * @param string $cell
     * @return string a type, or IGNORE when the title says nothing
     */
    public static function read_header(string $cell): string {
        $value = core_text::strtolower(trim($cell));
        if ($value === '') {
            return self::IGNORE;
        }

        // Tested before the single words, because it contains both of them.
        if (preg_match('/^(nach|familien|sur|last)?name\s*,\s*(vor|first|given)?name$/u', $value)) {
            return self::LASTFIRST;
        }

        $titles = [
            self::EMAIL => '/mail|adresse|address/u',
            self::ROLE => '/rolle|role|funktion|function/u',
            self::FIRSTNAME => '/vorname|firstname|first name|given/u',
            self::LASTNAME => '/nachname|familienname|lastname|last name|surname|^name$/u',
            self::FULLNAME => '/^(voller|full|ganzer) name$|teilnehmer|participant|person/u',
        ];

        foreach ($titles as $type => $pattern) {
            if (preg_match($pattern, $value)) {
                return $type;
            }
        }

        return self::IGNORE;
    }

    /**
     * Guess what each column holds.
     *
     * @param array[] $rows as returned by parser::rows()
     * @param bool $hasheader whether the first row names the columns
     * @return array column index => type
     */
    public static function suggest(array $rows, bool $hasheader): array {
        if (!$rows) {
            return [];
        }

        $width = count($rows[0]);
        $map = array_fill(0, $width, self::IGNORE);
        $data = $hasheader ? array_slice($rows, 1) : $rows;

        // A title beats anything the content might suggest.
        if ($hasheader) {
            foreach ($rows[0] as $index => $cell) {
                $map[$index] = self::read_header($cell);
            }
        }

        $taken = array_filter($map, function ($type) {
            return $type !== self::IGNORE;
        });

        foreach ($map as $index => $type) {
            if ($type !== self::IGNORE) {
                continue;
            }

            $cells = array_column($data, $index);

            if (!in_array(self::NAMEMAIL, $taken, true) && self::most($cells, '/^[^@]*\s[^@]*@/u')) {
                $map[$index] = self::NAMEMAIL;
            } else if (!in_array(self::EMAIL, $taken, true) && self::most($cells, '/@/u')) {
                $map[$index] = self::EMAIL;
            } else if (!in_array(self::ROLE, $taken, true) && self::most($cells, self::role_pattern())) {
                $map[$index] = self::ROLE;
            } else if (!self::has_name($taken) && self::most($cells, '/^[^,]+,\s*\S/u')) {
                $map[$index] = self::LASTFIRST;
            } else if (!in_array(self::FIRSTNAME, $taken, true) && self::most($cells, '/\S/u')) {
                $map[$index] = self::FIRSTNAME;
            } else if (!in_array(self::LASTNAME, $taken, true) && self::most($cells, '/\S/u')) {
                $map[$index] = self::LASTNAME;
            }

            if ($map[$index] !== self::IGNORE) {
                $taken[$index] = $map[$index];
            }
        }

        // One name column on its own holds a whole name, not a first name.
        if (in_array(self::FIRSTNAME, $map, true) && !in_array(self::LASTNAME, $map, true)) {
            $index = array_search(self::FIRSTNAME, $map, true);
            if (self::most(array_column($data, $index), '/\S\s+\S/u')) {
                $map[$index] = self::FULLNAME;
            }
        }

        return $map;
    }

    /**
     * Whether the mapping can produce people.
     *
     * @param array $map column index => type
     * @return bool
     */
    public static function complete(array $map): bool {
        return self::has_name($map) && self::has_email($map);
    }

    /**
     * What the mapping still lacks.
     *
     * @param array $map column index => type
     * @return string[] ready to show
     */
    public static function missing(array $map): array {
        $missing = [];

        if (!self::has_name($map)) {
            $missing[] = get_string('ordermissingnamecolumn', 'local_coursesoverview');
        }
        if (!self::has_email($map)) {
            $missing[] = get_string('ordermissingemailcolumn', 'local_coursesoverview');
        }

        return $missing;
    }

    /**
     * Read the people out of the rows.
     *
     * @param array[] $rows as returned by parser::rows()
     * @param array $map column index => type
     * @param bool $hasheader whether the first row names the columns
     * @return participant[]
     */
    public static function apply(array $rows, array $map, bool $hasheader): array {
        $people = [];
        $number = 0;

        foreach ($rows as $index => $cells) {
            $number++;
            if ($hasheader && $index === 0) {
                continue;
            }

            $person = new participant($number);
            $role = '';

            foreach ($cells as $column => $cell) {
                switch ($map[$column] ?? self::IGNORE) {
                    case self::FIRSTNAME:
                        $person->firstname = $cell;
                        break;
                    case self::LASTNAME:
                        $person->lastname = $cell;
                        break;
                    case self::LASTFIRST:
                        [$person->lastname, $person->firstname] = self::split_lastfirst($cell);
                        break;
                    case self::FULLNAME:
                        [$person->firstname, $person->lastname] = self::split_fullname($cell);
                        break;
                    case self::EMAIL:
                        $person->email = self::read_email($cell);
                        break;
                    case self::NAMEMAIL:
                        $person->email = self::read_email($cell);
                        [$person->firstname, $person->lastname] = self::split_fullname(self::strip_email($cell));
                        break;
                    case self::ROLE:
                        $role = $cell;
                        break;
                }
            }

            $person->role = participant::read_role($role);

            // A row that holds nothing at all is a leftover, not a person.
            if ($person->name() !== '' || $person->email !== '') {
                $people[] = $person;
            }
        }

        return $people;
    }

    /**
     * Pull the address out of a cell that may carry a name as well.
     *
     * @param string $cell
     * @return string
     */
    public static function read_email(string $cell): string {
        $cell = str_ireplace('mailto:', '', trim($cell));

        // Covers "Max Mustermann <max@example.com>" and the same without the name.
        if (preg_match('/<([^>]+)>/', $cell, $matches)) {
            $cell = $matches[1];
        } else if (preg_match('/\S+@\S+/u', $cell, $matches)) {
            $cell = $matches[0];
        }

        return core_text::strtolower(trim($cell, " \t<>,;"));
    }

    /**
     * Take the address out of a cell so that the name is left.
     *
     * @param string $cell
     * @return string
     */
    protected static function strip_email(string $cell): string {
        $name = trim(preg_replace('/<[^>]*>/', '', $cell));

        // Without angle brackets the address is just one of the words.
        if (strpos($name, '@') !== false) {
            $name = preg_replace('/\S+@\S+/u', '', $name);
        }

        return trim($name, " ,;\"");
    }

    /**
     * Split "Mustermann, Max" into surname and first name.
     *
     * @param string $cell
     * @return string[] surname, first name
     */
    protected static function split_lastfirst(string $cell): array {
        $parts = explode(',', $cell, 2);

        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * Split "Max Mustermann" into first name and surname.
     *
     * The last word is taken as the surname, so "Anna Maria Schmidt" comes out
     * right while "Max von Mustermann" does not. Lists in that shape are the
     * reason the two name columns can be assigned separately.
     *
     * @param string $cell
     * @return string[] first name, surname
     */
    protected static function split_fullname(string $cell): array {
        $parts = preg_split('/\s+/u', trim($cell));

        if (count($parts) < 2) {
            return [trim($cell), ''];
        }

        $lastname = array_pop($parts);

        return [implode(' ', $parts), $lastname];
    }

    /**
     * Whether a mapping names people one way or another.
     *
     * @param array $map
     * @return bool
     */
    protected static function has_name(array $map): bool {
        foreach ([self::LASTFIRST, self::FULLNAME, self::LASTNAME, self::NAMEMAIL] as $type) {
            if (in_array($type, $map, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a mapping carries an address one way or another.
     *
     * @param array $map
     * @return bool
     */
    protected static function has_email(array $map): bool {
        return in_array(self::EMAIL, $map, true) || in_array(self::NAMEMAIL, $map, true);
    }

    /**
     * Whether most of the cells match, blanks not counted.
     *
     * @param array $cells
     * @param string $pattern
     * @return bool
     */
    protected static function most(array $cells, string $pattern): bool {
        $filled = 0;
        $hits = 0;

        foreach ($cells as $cell) {
            if (trim((string) $cell) === '') {
                continue;
            }
            $filled++;
            if (preg_match($pattern, $cell)) {
                $hits++;
            }
        }

        return $filled > 0 && $hits / $filled > 0.5;
    }

    /**
     * The words that mark a role column.
     *
     * @return string
     */
    protected static function role_pattern(): string {
        return '/teilnehmer|organisator|organiser|vorgesetz|participant|rolle|role/iu';
    }
}
