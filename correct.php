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
 * Correct the name or the address of somebody an order created.
 *
 * Without a user id the page lists the people of the order. With one it
 * shows that person's details to correct, checks them, and saves them on the
 * second press, handing out new credentials if asked to.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursesoverview\helper;
use local_coursesoverview\order\correction;
use local_coursesoverview\order\display;
use local_coursesoverview\order\participant;
use local_coursesoverview\order\provision;

$orderid = required_param('orderid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$listurl = new moodle_url('/local/coursesoverview/correct.php', ['orderid' => $orderid]);
admin_externalpage_setup('localcoursesovervieworder', '', ['orderid' => $orderid], '/local/coursesoverview/correct.php');
require_capability('local/coursesoverview:createorder', context_system::instance());

$order = $DB->get_record('local_coursesoverview_order', ['id' => $orderid], '*', MUST_EXIST);
$course = $order->courseid ? $DB->get_record('course', ['id' => $order->courseid]) : null;
if (!$course) {
    $overviewurl = new moodle_url('/local/coursesoverview/index.php');
    throw new moodle_exception('orderwithoutcourse', 'local_coursesoverview', $overviewurl->out());
}

$coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
$participantsurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);
$rolenames = participant::roles();

// How somebody's credentials went out, or when they will.
$state = function (stdClass $row): string {
    if ($row->reused) {
        return get_string('correctstatereused', 'local_coursesoverview');
    }
    if ($row->delivery === provision::DELIVERY_LIST) {
        return get_string('correctstatelist', 'local_coursesoverview');
    }
    if ($row->timesent) {
        return get_string('correctstatesent', 'local_coursesoverview', helper::format_date($row->timesent));
    }
    if ($row->senddate > time()) {
        $a = (object) ['date' => helper::format_date($row->senddate), 'time' => userdate($row->senddate, '%H:%M')];
        return get_string('correctstatewaiting', 'local_coursesoverview', $a);
    }

    return get_string('correctstatedue', 'local_coursesoverview');
};

if (!$userid) {
    // The people of the order, to pick the one to correct.
    $rows = $DB->get_records('local_coursesoverview_orderuser', ['orderid' => $orderid]);
    $users = $rows ? $DB->get_records_list('user', 'id', array_column($rows, 'userid')) : [];

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('correctheading', 'local_coursesoverview', $coursename));
    echo html_writer::tag('p', get_string('correctlist_help', 'local_coursesoverview'));

    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('fullname'),
        get_string('orderusername', 'local_coursesoverview'),
        get_string('email'),
        get_string('role'),
        get_string('correctstate', 'local_coursesoverview'),
        get_string('lastlogin'),
        '',
    ];

    foreach ($rows as $row) {
        $user = $users[$row->userid] ?? null;
        if (!$user || $user->deleted) {
            continue;
        }

        $editurl = new moodle_url($listurl, ['userid' => $user->id]);
        $table->data[] = [
            s(fullname($user)),
            s($user->username),
            s($user->email),
            $rolenames[$row->role] ?? s($row->role),
            $state($row),
            helper::format_date($user->lastlogin ?: $user->currentlogin, get_string('never')),
            html_writer::link($editurl, get_string('correct', 'local_coursesoverview')),
        ];
    }

    echo html_writer::table($table);
    echo html_writer::link($participantsurl, get_string('correctback', 'local_coursesoverview'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die();
}

$row = $DB->get_record('local_coursesoverview_orderuser', ['orderid' => $orderid, 'userid' => $userid], '*', MUST_EXIST);
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

$firstname = optional_param('firstname', $user->firstname, PARAM_NOTAGS);
$lastname = optional_param('lastname', $user->lastname, PARAM_NOTAGS);
$email = optional_param('email', $user->email, PARAM_RAW_TRIMMED);

// New credentials go out the way the old ones did, unless chosen otherwise.
$defaultresend = $row->delivery === provision::DELIVERY_LIST ? correction::RESEND_LIST : correction::RESEND_DIRECT;
$resend = optional_param('resend', $defaultresend, PARAM_ALPHA);
$resends = [
    correction::RESEND_DIRECT => get_string('correctresenddirect', 'local_coursesoverview'),
    correction::RESEND_LIST => get_string('correctresendlist', 'local_coursesoverview'),
    correction::RESEND_NONE => get_string('correctresendnone', 'local_coursesoverview'),
];
if (!array_key_exists($resend, $resends)) {
    $resend = $defaultresend;
}

if ($action !== '') {
    require_sesskey();
}

$correction = new correction($order, $row, $user, $firstname, $lastname, $email);
$resending = $correction->can_resend() && $resend !== correction::RESEND_NONE;

$result = null;
if ($action === 'save' && !$correction->errors) {
    if (!$correction->changes() && !$resending) {
        $correction->errors[] = get_string('correctnothing', 'local_coursesoverview');
    } else {
        $result = $correction->save($resend);
    }
}

if ($result) {
    // The account as it is now, after the correction, down to the heading.
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
}

$pageurl = new moodle_url($listurl, ['userid' => $userid]);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('correctpersonheading', 'local_coursesoverview', s(fullname($user))));

if ($result) {

    if ($result->saved) {
        echo $OUTPUT->notification(get_string('correctsaved', 'local_coursesoverview', s(fullname($user))), 'success');
    }
    if ($result->mailed) {
        echo $OUTPUT->notification(get_string('correctmailed', 'local_coursesoverview', s($user->email)), 'success');
    }
    foreach ($result->errors as $error) {
        echo $OUTPUT->notification($error, 'warning');
    }
    if ($correction->held()) {
        $a = (object) ['date' => helper::format_date($row->senddate), 'time' => userdate($row->senddate, '%H:%M')];
        echo $OUTPUT->notification(get_string('correctheld', 'local_coursesoverview', $a), 'info');
    }

    if ($result->password !== '') {
        echo $OUTPUT->notification(get_string('correctpassword', 'local_coursesoverview'), 'info');
        echo display::summary([
            get_string('fullname') => fullname($user),
            get_string('orderusername', 'local_coursesoverview') => $user->username,
            get_string('password') => $result->password,
            get_string('email') => $user->email,
        ]);
    }

    echo html_writer::link($listurl, get_string('correctbacklist', 'local_coursesoverview'), [
        'class' => 'btn btn-secondary mr-2 me-2',
    ]);
    echo html_writer::link($participantsurl, get_string('correctback', 'local_coursesoverview'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die();
}

echo display::summary([
    get_string('course') => $coursename,
    get_string('orderusername', 'local_coursesoverview') => $user->username,
    get_string('email') => $user->email,
    get_string('correctstate', 'local_coursesoverview') => $state($row),
]);

foreach ($correction->errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

$form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach (['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email] as $name => $value) {
    $id = 'coursesoverview-' . $name;
    $field = html_writer::tag('label', get_string($name), ['for' => $id, 'class' => 'd-block font-weight-bold fw-bold']);
    $field .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => $name,
        'id' => $id,
        'value' => $value,
        'size' => 40,
        'class' => 'form-control',
        'spellcheck' => 'false',
    ]);
    $form .= html_writer::div($field, 'form-group mb-3');
}

if ($correction->can_resend()) {
    $field = html_writer::tag('label', get_string('correctresend', 'local_coursesoverview'), [
        'for' => 'coursesoverview-resend',
        'class' => 'd-block font-weight-bold fw-bold',
    ]);
    $field .= html_writer::select($resends, 'resend', $resend, false, [
        'id' => 'coursesoverview-resend',
        'class' => 'custom-select form-select',
    ]);
    $field .= html_writer::tag('small', get_string('correctresend_help', 'local_coursesoverview'), [
        'class' => 'form-text text-muted',
    ]);
    $form .= html_writer::div($field, 'form-group mb-3');
} else if ($correction->held()) {
    $a = (object) ['date' => helper::format_date($row->senddate), 'time' => userdate($row->senddate, '%H:%M')];
    $form .= html_writer::tag('p', get_string('correctheld', 'local_coursesoverview', $a));
} else {
    $form .= html_writer::tag('p', get_string('correctreused', 'local_coursesoverview'));
}

// What saving would change, once it has been checked.
if ($action === 'check' && !$correction->errors) {
    $after = [
        get_string('firstname') => [$user->firstname, $correction->person->firstname],
        get_string('lastname') => [$user->lastname, $correction->person->lastname],
        get_string('email') => [$user->email, $correction->person->email],
        get_string('orderusername', 'local_coursesoverview') => [$user->username, $correction->username],
    ];

    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = ['', get_string('correctbefore', 'local_coursesoverview'), get_string('correctafter', 'local_coursesoverview')];
    foreach ($after as $label => [$before, $now]) {
        $cell = $before === $now ? s($now) : html_writer::tag('strong', s($now));
        $table->data[] = [$label, s($before), $cell];
    }
    $form .= html_writer::table($table);

    foreach ($correction->notes as $note) {
        $form .= $OUTPUT->notification($note, 'info');
    }
}

$buttons = html_writer::tag('button', get_string('correctcheck', 'local_coursesoverview'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'check',
    'class' => 'btn btn-secondary mr-2 me-2',
]);

// Saving is offered only once what it does has been shown.
if ($action === 'check' && !$correction->errors) {
    $buttons .= html_writer::tag('button', get_string('correctsave', 'local_coursesoverview'), [
        'type' => 'submit',
        'name' => 'action',
        'value' => 'save',
        'class' => 'btn btn-primary mr-2 me-2',
    ]);
}
$buttons .= html_writer::link($listurl, get_string('cancel'), ['class' => 'btn btn-link']);
$form .= html_writer::div($buttons, 'mt-3');

echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);
echo $OUTPUT->footer();
