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

/**
 * The reminders of an order: when they go out, who has had one, and sending one now.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursesoverview\helper;
use local_coursesoverview\order\display;
use local_coursesoverview\order\reminder;

$orderid = required_param('orderid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$selected = optional_param_array('users', [], PARAM_INT);

$pageurl = new moodle_url('/local/coursesoverview/remind.php', ['orderid' => $orderid]);
admin_externalpage_setup('localcoursesovervieworder', '', ['orderid' => $orderid], '/local/coursesoverview/remind.php');
require_capability('local/coursesoverview:createorder', context_system::instance());

$order = $DB->get_record('local_coursesoverview_order', ['id' => $orderid], '*', MUST_EXIST);
$course = $order->courseid ? $DB->get_record('course', ['id' => $order->courseid]) : null;
if (!$course) {
    $overviewurl = new moodle_url('/local/coursesoverview/index.php');
    throw new moodle_exception('orderwithoutcourse', 'local_coursesoverview', $overviewurl->out());
}

$coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
$participantsurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);
$kinds = [reminder::AFTER, reminder::BEFORE];

if ($action !== '') {
    require_sesskey();
}

$errors = [];

// The days of this order, as typed, until they are saved.
$typed = [];
foreach ($kinds as $kind) {
    $typed[$kind] = reminder::uses_default($order, $kind) ? '' : (string) $order->{'reminder' . $kind};
}

if ($action === 'schedule') {
    $changes = ['id' => $order->id];
    foreach ($kinds as $kind) {
        $field = 'reminder' . $kind;
        $typed[$kind] = trim(optional_param($field, '', PARAM_TEXT));

        // Days typed in count, whatever the box says. Only an empty field
        // falls back to the settings, or to none without the box.
        if ($typed[$kind] === '' && optional_param($field . 'default', 0, PARAM_BOOL)) {
            $changes[$field] = null;
            continue;
        }

        $days = reminder::parse($typed[$kind]);
        if ($days === null) {
            $errors[] = get_string('reminderinvaliddays', 'local_coursesoverview', s($typed[$kind]));
            continue;
        }
        $changes[$field] = reminder::text($days, $kind);
    }

    if (!$errors) {
        $DB->update_record('local_coursesoverview_order', (object) $changes);
        redirect(
            $pageurl,
            get_string('reminderscheduled', 'local_coursesoverview'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$people = reminder::people($course);
$sent = reminder::sent((int) $order->id);
$now = time();

if ($action === 'preview' || $action === 'send') {
    // Only people still open can be reminded, whatever came in.
    $selected = array_values(array_filter(
        array_unique($selected),
        fn($userid) => isset($people[$userid]) && !$people[$userid]->completed
    ));
    if (!$selected) {
        $errors[] = get_string('remindernobody', 'local_coursesoverview');
    }
}

if ($action === 'send' && !$errors) {
    $count = 0;
    $failed = 0;
    foreach ($selected as $userid) {
        if (reminder::send($people[$userid]->user, $order, $course, reminder::MANUAL, 0, 0)) {
            $count++;
        } else {
            $failed++;
        }
    }

    $message = helper::counted('remindersent', $count);
    if ($failed) {
        $message .= ' ' . get_string('reminderfailed', 'local_coursesoverview');
    }
    redirect(
        $pageurl,
        $message,
        null,
        $failed ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('remindheading', 'local_coursesoverview', $coursename));

if ($action === 'preview' && !$errors) {
    // What is about to go out, before it does.
    $first = $people[$selected[0]]->user;
    [$subject, $html] = reminder::compose($first, $order, $course);

    echo $OUTPUT->notification(helper::counted('reminderconfirm', count($selected)), 'info');
    echo html_writer::tag('p', get_string('reminderpreview', 'local_coursesoverview', s(fullname($first))));
    $mail = html_writer::tag('p', html_writer::tag('strong', s($subject)));
    $mail .= html_writer::empty_tag('hr') . $html;
    echo html_writer::div($mail, 'card card-body mb-3');

    $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    foreach ($selected as $userid) {
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'users[]', 'value' => $userid]);
    }
    $form .= html_writer::tag('button', get_string('remindersendnow', 'local_coursesoverview'), [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'send',
        'class' => 'btn btn-primary mr-2 me-2',
    ]);
    $form .= html_writer::link($pageurl, get_string('cancel'), ['class' => 'btn btn-link']);
    echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);

    echo $OUTPUT->footer();
    die();
}

echo html_writer::tag('p', get_string('remind_help', 'local_coursesoverview'));

foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

echo display::summary([
    get_string('course') => $coursename,
    get_string('enddate') => helper::format_date($course->enddate, get_string('remindernoend', 'local_coursesoverview')),
]);

// When the reminders go out.
echo $OUTPUT->heading(get_string('reminderschedule', 'local_coursesoverview'), 3);

$form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
foreach ($kinds as $kind) {
    $field = 'reminder' . $kind;
    $default = reminder::text(reminder::default_days($kind), $kind);
    if ($default === '') {
        $default = get_string('remindernone', 'local_coursesoverview');
    }
    $usesdefault = reminder::uses_default($order, $kind);
    if ($action === 'schedule') {
        $usesdefault = $typed[$kind] === '' && optional_param($field . 'default', 0, PARAM_BOOL);
    }

    $block = html_writer::tag('label', get_string($field, 'local_coursesoverview'), [
        'for' => 'coursesoverview-' . $field,
        'class' => 'd-block font-weight-bold fw-bold',
    ]);
    $check = html_writer::checkbox(
        $field . 'default',
        1,
        (bool) $usesdefault,
        get_string('reminderdefault', 'local_coursesoverview', $default),
        ['id' => 'coursesoverview-' . $field . 'default'],
        ['class' => 'ml-1 ms-1']
    );
    $block .= html_writer::div($check, 'mb-1');
    $block .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => $field,
        'id' => 'coursesoverview-' . $field,
        'value' => $typed[$kind],
        'size' => 20,
        'class' => 'form-control d-inline-block w-auto',
    ]);
    $block .= html_writer::tag('small', get_string($field . '_help', 'local_coursesoverview'), [
        'class' => 'form-text text-muted',
    ]);
    $form .= html_writer::div($block, 'form-group mb-3');
}
$form .= html_writer::tag('button', get_string('savechanges'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'schedule',
    'class' => 'btn btn-secondary',
]);
echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false), 'class' => 'mb-4']);

// Who has had a reminder, who is next, and sending one now.
echo $OUTPUT->heading(get_string('reminderpeople', 'local_coursesoverview'), 3);

if (!$people) {
    echo html_writer::tag('p', get_string('remindernopeople', 'local_coursesoverview'));
    echo html_writer::link($participantsurl, get_string('correctback', 'local_coursesoverview'), ['class' => 'btn btn-secondary']);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        '',
        get_string('fullname'),
        get_string('email'),
        get_string('reminderjoined', 'local_coursesoverview'),
        get_string('remindercompleted', 'local_coursesoverview'),
        get_string('reminderhad', 'local_coursesoverview'),
        get_string('remindernext', 'local_coursesoverview'),
    ];

    foreach ($people as $userid => $person) {
        $had = array_map([reminder::class, 'describe'], $sent[$userid] ?? []);

        if ($person->completed) {
            $box = '';
            $completed = helper::format_date($person->completed);
            $next = '';
        } else {
            $checked = $action === '' ? true : in_array($userid, $selected);
            $box = html_writer::checkbox('users[]', $userid, $checked, '', [
                'id' => 'coursesoverview-user' . $userid,
                'aria-label' => fullname($person->user),
            ]);
            $completed = get_string('reminderopen', 'local_coursesoverview');

            $plan = reminder::plan($order, $course, $person->joined, $sent[$userid] ?? [], $now);
            $due = reminder::next($plan, $sent[$userid] ?? []);
            if ($due) {
                $a = (object) ['date' => helper::format_date($due), 'time' => userdate($due, '%H:%M')];
                $next = get_string('remindernextat', 'local_coursesoverview', $a);
            } else {
                $next = get_string('remindernone', 'local_coursesoverview');
            }
        }

        $row = new html_table_row([
            $box,
            s(fullname($person->user)),
            s($person->user->email),
            helper::format_date($person->joined, ''),
            $completed,
            implode(html_writer::empty_tag('br'), array_map('s', $had)),
            $next,
        ]);
        if ($person->completed) {
            $row->attributes['class'] = 'text-muted';
        }
        $table->data[] = $row;
    }

    $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::table($table);
    $form .= html_writer::tag('button', get_string('remindersend', 'local_coursesoverview'), [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'preview',
        'class' => 'btn btn-primary mr-2 me-2',
    ]);
    $form .= html_writer::link($participantsurl, get_string('correctback', 'local_coursesoverview'), [
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);
}

echo $OUTPUT->footer();
