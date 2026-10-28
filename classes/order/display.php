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

use html_table;
use html_table_row;
use html_writer;

/**
 * The pieces the order page is drawn from.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display {
    /**
     * Hidden fields for everything the next step needs to know.
     *
     * The page keeps no state of its own between steps. Whatever was entered
     * travels with the form, so that going back and forth loses nothing and
     * a stale session cannot mix two orders.
     *
     * @param array $values name => value, an array value becomes name[key]
     * @return string
     */
    public static function hidden(array $values): string {
        $out = '';

        foreach ($values as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $out .= html_writer::empty_tag('input', [
                        'type' => 'hidden',
                        'name' => $name . '[' . $key . ']',
                        'value' => $item,
                    ]);
                }
                continue;
            }

            $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
        }

        return $out;
    }

    /**
     * A submit button that moves to a step.
     *
     * @param int $step
     * @param string $label
     * @param string $class
     * @param bool $disabled
     * @return string
     */
    public static function step_button(int $step, string $label, string $class, bool $disabled = false): string {
        $attributes = [
            'type' => 'submit',
            'name' => 'step',
            'value' => $step,
            'class' => 'btn ' . $class . ' mr-2 me-2',
        ];
        if ($disabled) {
            $attributes['disabled'] = 'disabled';
        }

        return html_writer::tag('button', $label, $attributes);
    }

    /**
     * The course an order makes, as a list of labelled values.
     *
     * @param array $pairs label => value, values are escaped here
     * @return string
     */
    public static function summary(array $pairs): string {
        $items = '';
        foreach ($pairs as $label => $value) {
            $items .= html_writer::tag('dt', $label, ['class' => 'col-sm-3']);
            $items .= html_writer::tag('dd', s($value), ['class' => 'col-sm-9']);
        }

        return html_writer::tag('dl', $items, ['class' => 'row mb-4']);
    }

    /**
     * The column assignment with the first lines underneath as evidence.
     *
     * @param array[] $rows as returned by parser::rows()
     * @param array $map column index => type
     * @return string
     */
    public static function columns_table(array $rows, array $map): string {
        $width = count($rows[0]);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [];
        $selects = [];

        for ($index = 0; $index < $width; $index++) {
            $label = get_string('ordercolumn', 'local_coursesoverview', $index + 1);
            $table->head[] = $label;
            $selects[] = html_writer::select(
                columnmap::types(),
                'map[' . $index . ']',
                $map[$index] ?? columnmap::IGNORE,
                false,
                ['class' => 'custom-select form-select', 'aria-label' => $label]
            );
        }

        $table->data[] = $selects;
        foreach (array_slice($rows, 0, 5) as $cells) {
            $table->data[] = array_map('s', $cells);
        }

        return html_writer::table($table);
    }

    /**
     * What the mapping made of the list, before the database has been asked.
     *
     * @param participant[] $people
     * @return string
     */
    public static function people_table(array $people): string {
        $roles = participant::roles();

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('orderrow', 'local_coursesoverview'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('role'),
            get_string('ordernotes', 'local_coursesoverview'),
        ];

        foreach ($people as $person) {
            $problems = $person->problems();

            $row = new html_table_row([
                $person->rownumber,
                s($person->firstname),
                s($person->lastname),
                s($person->email),
                $roles[$person->role],
                implode(' ', $problems),
            ]);
            if ($problems) {
                $row->attributes['class'] = 'coursesoverview-problem';
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Every line with its user name, its role to choose and its verdict.
     *
     * @param check[] $checks
     * @return string
     */
    public static function checks_table(array $checks): string {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('orderrow', 'local_coursesoverview'),
            get_string('username'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('role'),
            get_string('ordernotes', 'local_coursesoverview'),
        ];

        foreach ($checks as $check) {
            $person = $check->person;

            $role = html_writer::select(
                participant::roles(),
                'role[' . $check->index . ']',
                $person->role,
                false,
                ['class' => 'custom-select form-select', 'aria-label' => get_string('role')]
            );

            $notes = [];
            foreach ($check->errors as $error) {
                $notes[] = html_writer::div($error, 'text-danger');
            }
            foreach ($check->notes as $note) {
                $notes[] = html_writer::div($note, 'text-muted');
            }

            // Offered only where it is allowed, so that the box never promises
            // something the check would then refuse.
            if ($check->may_reuse()) {
                $id = 'coursesoverview-reuse-' . $check->index;
                $box = html_writer::checkbox(
                    'reuse[' . $check->index . ']',
                    1,
                    $check->reuse,
                    get_string('orderreuse', 'local_coursesoverview'),
                    ['id' => $id, 'class' => 'mr-2 me-2']
                );
                $notes[] = html_writer::div($box, 'mt-1');
            }

            $row = new html_table_row([
                $person->rownumber,
                s($check->username),
                s($person->firstname),
                s($person->lastname),
                s($person->email),
                $role,
                implode('', $notes),
            ]);
            if ($check->blocked()) {
                $row->attributes['class'] = 'coursesoverview-problem';
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * The credentials of an order that hands them back as a list.
     *
     * @param array $credentials as provision::run() returns them
     * @return string
     */
    public static function credentials_table(array $credentials): string {
        $roles = participant::roles();

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('username'),
            get_string('password'),
            get_string('fullname'),
            get_string('email'),
            get_string('role'),
        ];

        foreach ($credentials as $entry) {
            $table->data[] = [
                s($entry->username),
                html_writer::tag('code', s($entry->password)),
                s($entry->name),
                s($entry->email),
                $roles[$entry->role] ?? s($entry->role),
            ];
        }

        return html_writer::table($table);
    }
}
