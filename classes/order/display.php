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
use html_table;
use html_table_row;
use html_writer;
use local_coursesoverview\helper;
use moodle_url;
use single_button;

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
     * @param bool $hasheader whether the first row names the columns
     * @param array $headings row number => group name
     * @return string
     */
    public static function columns_table(array $rows, array $map, bool $hasheader, array $headings = []): string {
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
        foreach (array_slice($rows, 0, 5) as $number => $cells) {
            $row = new html_table_row(array_map('s', $cells));

            // Set apart, so that it does not read as the first person.
            if ($hasheader && $number === 0) {
                $row->attributes['class'] = 'text-muted font-italic fst-italic';
            } else if (isset($headings[$number + 1])) {
                $row->attributes['class'] = 'font-weight-bold fw-bold';
            }

            $table->data[] = $row;
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
        $grouped = self::has_groups($people);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('orderrow', 'local_coursesoverview'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('role'),
        ];
        if ($grouped) {
            $table->head[] = get_string('group');
        }
        $table->head[] = get_string('ordernotes', 'local_coursesoverview');

        foreach ($people as $person) {
            $problems = $person->problems();

            $cells = [
                $person->rownumber,
                s($person->firstname),
                s($person->lastname),
                s($person->email),
                $roles[$person->role],
            ];
            if ($grouped) {
                $cells[] = s($person->group);
            }
            $cells[] = implode(' ', $problems);

            $row = new html_table_row($cells);
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
        $grouped = self::has_groups(array_column($checks, 'person'));

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('orderrow', 'local_coursesoverview'),
            get_string('orderusername', 'local_coursesoverview'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('role'),
        ];
        if ($grouped) {
            $table->head[] = get_string('group');
        }
        $table->head[] = get_string('ordernotes', 'local_coursesoverview');

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
            foreach ($check->warnings as $warning) {
                $notes[] = html_writer::div($warning);
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

            $cells = [
                $person->rownumber,
                s($check->username),
                s($person->firstname),
                s($person->lastname),
                s($person->email),
                $role,
            ];
            if ($grouped) {
                $cells[] = s($person->group);
            }
            $cells[] = implode('', $notes);

            $row = new html_table_row($cells);
            if ($check->blocked()) {
                $row->attributes['class'] = 'coursesoverview-problem';
            } else if ($check->warnings) {
                $row->attributes['class'] = 'coursesoverview-warning';
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
        $grouped = self::has_groups($credentials);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('orderusername', 'local_coursesoverview'),
            get_string('password'),
            get_string('fullname'),
            get_string('email'),
            get_string('role'),
        ];
        if ($grouped) {
            $table->head[] = get_string('group');
        }

        foreach ($credentials as $entry) {
            $cells = [
                s($entry->username),
                html_writer::tag('code', s($entry->password)),
                s($entry->name),
                s($entry->email),
                $roles[$entry->role] ?? s($entry->role),
            ];
            if ($grouped) {
                $cells[] = s($entry->group);
            }
            $table->data[] = $cells;
        }

        return html_writer::table($table);
    }

    /**
     * Whether anybody in a list has a group, which is when groups are shown at all.
     *
     * @param object[] $entries people or credentials, anything with a group
     * @return bool
     */
    public static function has_groups(array $entries): bool {
        foreach ($entries as $entry) {
            if (($entry->group ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * The groups of a list with how many people go into each.
     *
     * A heading nobody follows shows up with nought, which is the sign of a
     * person without an address read as a heading.
     *
     * @param participant[] $people
     * @param string[] $headings group names from headings, people or not
     * @param string[] $existing group names the course already has
     * @return string nothing when the list has no groups
     */
    public static function groups_line(array $people, array $headings = [], array $existing = []): string {
        $counts = array_fill_keys($headings, 0);
        foreach ($people as $person) {
            if ($person->group !== '') {
                $counts[$person->group] = ($counts[$person->group] ?? 0) + 1;
            }
        }
        if (!$counts) {
            return '';
        }

        $known = array_map([core_text::class, 'strtolower'], $existing);
        $entries = [];
        foreach ($counts as $name => $count) {
            $a = (object) ['name' => s($name), 'count' => $count];
            $exists = in_array(core_text::strtolower((string) $name), $known, true);
            $entries[] = get_string($exists ? 'ordergroupexisting' : 'ordergroupentry', 'local_coursesoverview', $a);
        }

        return html_writer::tag('p', get_string('ordergroupsline', 'local_coursesoverview', implode(', ', $entries)));
    }

    /**
     * What our own team needs to see of the order behind a course.
     *
     * Shown on the participants page, which customers' organisers see as
     * well, so the caller checks the capability to create orders first.
     *
     * @param int $courseid
     * @return string nothing when the course was not made by an order
     */
    public static function order_box(int $courseid): string {
        global $DB, $OUTPUT;

        $order = $DB->get_record('local_coursesoverview_order', ['courseid' => $courseid], '*', IGNORE_MULTIPLE);
        if (!$order) {
            return '';
        }

        $a = (object) [
            'company' => s($order->company),
            'abnr' => s($order->abnr),
            'created' => helper::format_date($order->timecreated),
        ];
        $out = html_writer::tag('h4', get_string('orderbox', 'local_coursesoverview'), ['class' => 'h5']);
        $out .= html_writer::tag('p', get_string('orderboxinfo', 'local_coursesoverview', $a));

        $buttons = '';
        [$people, $senddate] = credentials::waiting((int) $order->id);
        if ($people) {
            $a = (object) [
                'count' => $people,
                'date' => helper::format_date($senddate),
                'time' => userdate($senddate, '%H:%M'),
            ];
            $out .= html_writer::tag('p', helper::counted('orderboxwaiting', $people, $a));

            $url = new moodle_url('/local/coursesoverview/order.php', [
                'action' => 'release',
                'orderid' => $order->id,
                'sesskey' => sesskey(),
            ]);
            $button = new single_button($url, get_string('orderrelease', 'local_coursesoverview'), 'post');
            $button->add_confirm_action(helper::counted('orderreleaseconfirm', $people, $a));
            $buttons .= html_writer::div($OUTPUT->render($button), 'mr-2 me-2');
        }

        $addurl = new moodle_url('/local/coursesoverview/order.php', ['orderid' => $order->id]);
        $buttons .= html_writer::link($addurl, get_string('orderaddpeople', 'local_coursesoverview'), [
            'class' => 'btn btn-secondary',
        ]);
        $out .= html_writer::div($buttons, 'd-flex align-items-start');

        return html_writer::div($out, 'card card-body mb-3');
    }
}
