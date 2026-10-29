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
 * Turn a customer's order into a course and its participants.
 *
 * Step one takes the order and the list as it arrived, step two says what the
 * columns of that list mean, step three holds every line against the
 * database, and only step four creates anything.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursesoverview\export;
use local_coursesoverview\helper;
use local_coursesoverview\order\columnmap;
use local_coursesoverview\order\credentials;
use local_coursesoverview\order\display;
use local_coursesoverview\order\emailpattern;
use local_coursesoverview\order\module;
use local_coursesoverview\order\parser;
use local_coursesoverview\order\participant;
use local_coursesoverview\order\provision;
use local_coursesoverview\order\validator;
use local_coursesoverview\output;

$action = optional_param('action', '', PARAM_ALPHA);
$step = optional_param('step', 1, PARAM_INT);

// Set when people are reported late to an order that already exists.
$orderid = optional_param('orderid', 0, PARAM_INT);

// The order itself.
$moduleprefix = optional_param('module', '', PARAM_TEXT);
$company = trim(optional_param('company', '', PARAM_TEXT));
$abnr = trim(optional_param('abnr', '', PARAM_TEXT));
$startdate = optional_param('startdate', '', PARAM_TEXT);
$enddate = optional_param('enddate', '', PARAM_TEXT);
$scheme = optional_param('scheme', validator::SCHEME_EMAIL, PARAM_ALPHA);
$delivery = optional_param('delivery', '', PARAM_ALPHA);

// How the customer's addresses are built. Missing rather than empty means
// a late report that takes it over from its order.
$emailpattern = optional_param('emailpattern', null, PARAM_TEXT);
$useguess = optional_param('useguess', 0, PARAM_BOOL);

// The pasted list and how it is cut up.
$list = optional_param('list', '', PARAM_RAW);
$delimiter = optional_param('delimiter', parser::AUTO, PARAM_ALPHA);
$hasheader = optional_param('hasheader', -1, PARAM_INT);
$groupheads = optional_param('groupheads', -1, PARAM_INT);
$map = optional_param_array('map', [], PARAM_ALPHA);

// What the posted mapping was built from, so that a changed shape is noticed.
$lastdelimiter = optional_param('lastdelimiter', '', PARAM_ALPHA);
$lastheader = optional_param('lastheader', -1, PARAM_INT);
$lastgroupheads = optional_param('lastgroupheads', -1, PARAM_INT);

// The choices made per line in step three.
$roles = optional_param_array('role', [], PARAM_ALPHA);
$reuse = optional_param_array('reuse', [], PARAM_BOOL);

admin_externalpage_setup('localcoursesovervieworder');
require_capability('local/coursesoverview:createorder', context_system::instance());

$pageurl = new moodle_url('/local/coursesoverview/order.php');

// Starting a held order early, from the box on the participants page.
if ($action === 'release') {
    require_sesskey();

    $held = $DB->get_record('local_coursesoverview_order', ['id' => $orderid], '*', MUST_EXIST);
    $sent = credentials::release($held);

    redirect(
        new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $held->courseid]),
        get_string('orderreleased', 'local_coursesoverview', $sent),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// The list of credentials is sent from the session, where the order that
// created them left it. Passwords are never written to the database.
if ($action === 'credentials') {
    require_sesskey();

    $stored = $SESSION->local_coursesoverview_credentials ?? null;
    if (!$stored) {
        throw new moodle_exception('ordercredentialsgone', 'local_coursesoverview', $pageurl->out(false));
    }

    $rolenames = participant::roles();
    $grouped = display::has_groups($stored->credentials);
    $columns = [
        get_string('orderusername', 'local_coursesoverview'),
        get_string('orderstartpassword', 'local_coursesoverview'),
        get_string('firstname'),
        get_string('lastname'),
        get_string('email'),
        get_string('course'),
        get_string('role'),
    ];
    if ($grouped) {
        $columns[] = get_string('group');
    }

    $rows = [];
    foreach ($stored->credentials as $entry) {
        $values = [
            $entry->username,
            $entry->password,
            $entry->firstname,
            $entry->lastname,
            $entry->email,
            $stored->shortname,
            $rolenames[$entry->role] ?? $entry->role,
        ];
        if ($grouped) {
            $values[] = $entry->group ?? '';
        }
        $rows[] = ['values' => $values, 'bgcolor' => null];
    }

    export::download(
        'excel',
        get_string('ordercredentialsfile', 'local_coursesoverview') . '_' . $stored->shortname,
        $stored->shortname,
        $columns,
        $rows,
        $stored->preamble
    );
}

$modules = module::menu();
$schemes = [
    validator::SCHEME_EMAIL => get_string('orderschemeemail', 'local_coursesoverview'),
    validator::SCHEME_NAME => get_string('orderschemename', 'local_coursesoverview'),
];
$deliveries = [
    provision::DELIVERY_DIRECT => get_string('orderdeliverydirect', 'local_coursesoverview'),
    provision::DELIVERY_LIST => get_string('orderdeliverylist', 'local_coursesoverview'),
];

// A late report keeps everything of its order except how the credentials go
// out, which is chosen anew.
$existing = null;
$course = null;
if ($orderid) {
    $existing = $DB->get_record('local_coursesoverview_order', ['id' => $orderid], '*', MUST_EXIST);
    $course = $existing->courseid ? $DB->get_record('course', ['id' => $existing->courseid]) : null;
    if (!$course) {
        throw new moodle_exception('orderwithoutcourse', 'local_coursesoverview', $pageurl->out(false));
    }

    $moduleprefix = $existing->module;
    $company = $existing->company;
    $abnr = $existing->abnr;
    $scheme = $existing->usernamescheme;
    if ($delivery === '') {
        $delivery = $existing->delivery;
    }
    if ($emailpattern === null) {
        $emailpattern = $existing->emailpattern;
    }
    $startdate = date('Y-m-d', $course->startdate);
    $enddate = date('Y-m-d', $course->enddate);
}

if (!array_key_exists($scheme, $schemes)) {
    $scheme = validator::SCHEME_EMAIL;
}
$emailpattern = trim((string) $emailpattern);
if (!array_key_exists($delivery, $deliveries)) {
    $delivery = provision::DELIVERY_DIRECT;
}

// Dates are prefilled with today and the configured runtime, both changeable.
if ($startdate === '') {
    $startdate = date('Y-m-d');
}
if ($enddate === '') {
    $months = max(1, (int) get_config('local_coursesoverview', 'ordermonths'));
    $enddate = date('Y-m-d', strtotime('+' . $months . ' months'));
}

$errors = [];
$module = null;
$starttime = helper::parse_date($startdate);
$endtime = helper::parse_date($enddate);

if ($existing) {
    // Only for showing its name, the course already exists.
    $module = module::get($moduleprefix);
}

if ($step >= 2) {
    require_sesskey();
}

if ($step >= 2 && !$existing) {
    $module = module::get($moduleprefix);
    if (!$module) {
        $errors[] = get_string('ordererrormodule', 'local_coursesoverview');
    } else if (!$module->template_course()) {
        $errors[] = get_string('ordererrortemplate', 'local_coursesoverview', s($module->template));
    } else if (!$module->category()) {
        $errors[] = get_string('ordererrorcategory', 'local_coursesoverview', s($module->name));
    }

    if (module::clean($company) === '') {
        $errors[] = get_string('ordererrorcompany', 'local_coursesoverview');
    }
    if (module::clean($abnr) === '') {
        $errors[] = get_string('ordererrorabnr', 'local_coursesoverview');
    }
    if (!$starttime || !$endtime) {
        $errors[] = get_string('ordererrordate', 'local_coursesoverview');
    } else if ($endtime <= $starttime) {
        $errors[] = get_string('ordererrorrange', 'local_coursesoverview');
    }
}

if ($step >= 2) {
    if (trim($list) === '') {
        $errors[] = get_string('ordererrorlist', 'local_coursesoverview');
    }

    if ($errors) {
        $step = 1;
    }
}

$rows = [];
$headings = [];
$people = [];
$checks = [];
$blockers = [];
$guess = null;

if ($step >= 2) {
    $rows = parser::rows($list, $delimiter);
    $width = $rows ? count($rows[0]) : 0;

    if ($hasheader < 0) {
        $hasheader = parser::looks_like_header($rows) ? 1 : 0;
    }

    // Groups are only ever read from a list that has headings, and the box
    // in step two turns that off for a list that merely looks like it.
    $headings = parser::group_headings($rows, (bool) $hasheader);
    if ($groupheads < 0) {
        $groupheads = $headings ? 1 : 0;
    }
    if (!$groupheads) {
        $headings = [];
    }

    // A changed separator or header line makes the posted mapping meaningless.
    $reshaped = ($lastdelimiter !== $delimiter) || ($lastheader !== $hasheader) || ($lastgroupheads !== $groupheads);
    if ($reshaped || count($map) !== $width) {
        $map = columnmap::suggest($rows, (bool) $hasheader, $headings);
    }

    $people = columnmap::apply($rows, $map, (bool) $hasheader, $headings);

    if ($step >= 3 && (!columnmap::complete($map) || !$people)) {
        $step = 2;
    }
}

if ($step >= 2 && $existing) {
    $shortname = $course->shortname;
    $fullname = $course->fullname;
} else if ($step >= 2) {
    $shortname = $module->shortname($company, $abnr);
    $fullname = $module->fullname($company, $abnr);
}

if ($step >= 3) {
    $guess = emailpattern::guess($people);
    if ($useguess && $guess) {
        $emailpattern = $guess->pattern;
    }
}
$pattern = emailpattern::parse($emailpattern);

if ($step >= 3) {
    $checks = validator::run($people, $scheme, $roles, $reuse, $existing ? (int) $course->id : 0, $pattern);

    if (!$existing && $DB->record_exists('course', ['shortname' => $shortname])) {
        $blockers[] = get_string('ordercourseexists', 'local_coursesoverview', s($shortname));
    }
}

$blocked = (bool) $blockers;
foreach ($checks as $check) {
    if ($check->blocked()) {
        $blocked = true;
    }
}

$result = null;
if ($step >= 4) {
    if ($blocked) {
        $step = 3;
    } else if ($existing) {
        $result = provision::add($existing, $course, $checks, $delivery);

        // The next late report of the same customer starts from this pattern.
        $patterntext = $pattern ? $pattern->text() : '';
        if ($patterntext !== $existing->emailpattern) {
            $DB->set_field('local_coursesoverview_order', 'emailpattern', $patterntext, ['id' => $existing->id]);
        }
    } else {
        $order = (object) [
            'company' => $company,
            'abnr' => $abnr,
            'module' => $module->prefix,
            'shortname' => $shortname,
            'fullname' => $fullname,
            'startdate' => $starttime,
            'enddate' => $endtime,
            'scheme' => $scheme,
            'delivery' => $delivery,
            'emailpattern' => $pattern ? $pattern->text() : '',
        ];

        $result = provision::run($order, $module, $checks);

        // Without a course nothing happened, and the checked list is still
        // the best place to be.
        if (!$result->courseid) {
            $blockers = array_merge($blockers, $result->errors);
            $result = null;
            $step = 3;
        }
    }
}

echo $OUTPUT->header();
if ($existing) {
    $coursename = format_string($course->fullname, true, ['context' => context_course::instance($course->id)]);
    echo $OUTPUT->heading(get_string('orderaddheading', 'local_coursesoverview', $coursename));
} else {
    echo $OUTPUT->heading(get_string('neworder', 'local_coursesoverview'));
}

if (!$modules) {
    $settingsurl = new moodle_url('/admin/settings.php', ['section' => 'localcoursesoverviewsettings']);
    echo $OUTPUT->notification(get_string('ordernomodules', 'local_coursesoverview'), 'warning');
    echo html_writer::link($settingsurl, get_string('settings', 'local_coursesoverview'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die();
}

foreach ($errors as $error) {
    echo $OUTPUT->notification($error, 'error');
}

// Everything entered in step one, carried through all later steps.
$carried = [
    'sesskey' => sesskey(),
    'orderid' => $orderid,
    'module' => $moduleprefix,
    'company' => $company,
    'abnr' => $abnr,
    'startdate' => $startdate,
    'enddate' => $enddate,
    'scheme' => $scheme,
    'delivery' => $delivery,
    'emailpattern' => $emailpattern,
    'list' => $list,
];

// Wraps a label and its control in a form group, with an optional hint below.
$field = function (string $label, string $control, string $id, string $help = ''): string {
    $out = html_writer::tag('label', $label, ['for' => $id, 'class' => 'd-block font-weight-bold fw-bold']);
    $out .= $control;
    if ($help !== '') {
        $out .= html_writer::tag('small', $help, ['class' => 'form-text text-muted']);
    }

    return html_writer::div($out, 'form-group mb-3');
};

if ($step < 2) {
    // Step one: the order and the list as the customer sent it.
    $form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'orderid', 'value' => $orderid]);

    if ($existing) {
        // What the order already settled is shown, not asked.
        echo display::summary([
            get_string('ordershortname', 'local_coursesoverview') => $course->shortname,
            get_string('ordercompany', 'local_coursesoverview') => $company,
            get_string('orderabnr', 'local_coursesoverview') => $abnr,
            get_string('orderscheme', 'local_coursesoverview') => $schemes[$scheme],
        ]);
        echo html_writer::tag('p', get_string('orderadd_help', 'local_coursesoverview'));
    } else {
        $form .= $field(
            get_string('ordermodule', 'local_coursesoverview'),
            html_writer::select($modules, 'module', $moduleprefix, false, [
                'id' => 'coursesoverview-module',
                'class' => 'custom-select form-select',
            ]),
            'coursesoverview-module'
        );

        foreach (['company' => 40, 'abnr' => 20] as $name => $size) {
            $form .= $field(
                get_string('order' . $name, 'local_coursesoverview'),
                html_writer::empty_tag('input', [
                    'type' => 'text',
                    'name' => $name,
                    'id' => 'coursesoverview-' . $name,
                    'value' => $name === 'company' ? $company : $abnr,
                    'size' => $size,
                    'class' => 'form-control',
                ]),
                'coursesoverview-' . $name
            );
        }

        $form .= $field(
            get_string('orderstartdate', 'local_coursesoverview'),
            html_writer::empty_tag('input', [
                'type' => 'date',
                'name' => 'startdate',
                'id' => 'coursesoverview-startdate',
                'value' => $startdate,
                'class' => 'form-control',
            ]),
            'coursesoverview-startdate',
            get_string('orderstartdate_help', 'local_coursesoverview')
        );

        $form .= $field(
            get_string('orderenddate', 'local_coursesoverview'),
            html_writer::empty_tag('input', [
                'type' => 'date',
                'name' => 'enddate',
                'id' => 'coursesoverview-enddate',
                'value' => $enddate,
                'class' => 'form-control',
            ]),
            'coursesoverview-enddate'
        );

        $form .= $field(
            get_string('orderscheme', 'local_coursesoverview'),
            html_writer::select($schemes, 'scheme', $scheme, false, [
                'id' => 'coursesoverview-scheme',
                'class' => 'custom-select form-select',
            ]),
            'coursesoverview-scheme',
            get_string('orderscheme_help', 'local_coursesoverview')
        );
    }

    $form .= $field(
        get_string('orderdelivery', 'local_coursesoverview'),
        html_writer::select($deliveries, 'delivery', $delivery, false, [
            'id' => 'coursesoverview-delivery',
            'class' => 'custom-select form-select',
        ]),
        'coursesoverview-delivery',
        get_string('orderdelivery_help', 'local_coursesoverview')
    );

    $form .= $field(
        get_string('orderemailpattern', 'local_coursesoverview'),
        html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'emailpattern',
            'id' => 'coursesoverview-emailpattern',
            'value' => $emailpattern,
            'size' => 40,
            'class' => 'form-control',
            'spellcheck' => 'false',
        ]),
        'coursesoverview-emailpattern',
        get_string('orderemailpattern_help', 'local_coursesoverview')
    );

    $form .= $field(
        get_string('orderlist', 'local_coursesoverview'),
        html_writer::tag('textarea', s($list), [
            'name' => 'list',
            'id' => 'coursesoverview-list',
            'rows' => 12,
            'class' => 'form-control',
            'spellcheck' => 'false',
        ]),
        'coursesoverview-list',
        get_string('orderlist_help', 'local_coursesoverview')
    );

    $form .= display::step_button(2, get_string('ordercontinue', 'local_coursesoverview'), 'btn-primary');

    echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo $OUTPUT->footer();
    die();
}

$summary = [
    get_string('ordershortname', 'local_coursesoverview') => $shortname,
    get_string('orderfullname', 'local_coursesoverview') => $fullname,
    get_string('ordermodule', 'local_coursesoverview') => $module ? $module->name : $moduleprefix,
    get_string('orderruntime', 'local_coursesoverview') =>
        helper::format_date($starttime) . ' - ' . helper::format_date($endtime),
    get_string('orderscheme', 'local_coursesoverview') => $schemes[$scheme],
    get_string('orderdelivery', 'local_coursesoverview') => $deliveries[$delivery],
];
if ($pattern) {
    $summary[get_string('orderemailpattern', 'local_coursesoverview')] = $pattern->text();
}

if ($result) {
    // Step four: what was created.
    $course = get_course($result->courseid);
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $participantsurl = new moodle_url('/local/coursesoverview/participants.php', ['courseid' => $course->id]);

    $a = (object) [
        'course' => s($course->shortname),
        'created' => $result->created,
        'reused' => $result->reused,
    ];
    $done = $existing ? 'orderaddeddone' : 'orderdone';
    echo $OUTPUT->notification(get_string($done, 'local_coursesoverview', $a), 'success');

    foreach ($result->errors as $error) {
        echo $OUTPUT->notification(s($error), 'warning');
    }
    if ($result->grouped) {
        $a = (object) ['members' => $result->grouped, 'groups' => $result->groups];
        echo $OUTPUT->notification(get_string('ordergroupsdone', 'local_coursesoverview', $a), 'info');
    }

    echo display::summary($summary);

    if ($delivery === provision::DELIVERY_LIST && $result->credentials) {
        $SESSION->local_coursesoverview_credentials = (object) [
            'shortname' => $course->shortname,
            'credentials' => $result->credentials,
            'preamble' => [
                [get_string('ordercompany', 'local_coursesoverview'), $company],
                [get_string('orderabnr', 'local_coursesoverview'), $abnr],
                [get_string('ordershortname', 'local_coursesoverview'), $course->shortname],
                [get_string('orderruntime', 'local_coursesoverview'),
                    helper::format_date($starttime) . ' - ' . helper::format_date($endtime)],
            ],
        ];

        echo $OUTPUT->notification(get_string('orderlistready', 'local_coursesoverview'), 'info');
        $downloadurl = new moodle_url($pageurl, ['action' => 'credentials', 'sesskey' => sesskey()]);
        echo html_writer::div(
            html_writer::link($downloadurl, get_string('orderdownload', 'local_coursesoverview'), [
                'class' => 'btn btn-primary',
            ]),
            'mb-3'
        );
        echo display::credentials_table($result->credentials);
    } else if ($result->held) {
        $a = (object) [
            'count' => $result->held,
            'date' => helper::format_date($result->senddate),
            'time' => userdate($result->senddate, '%H:%M'),
        ];
        echo $OUTPUT->notification(get_string('orderheld', 'local_coursesoverview', $a), 'info');
    } else if ($delivery === provision::DELIVERY_DIRECT && $result->created) {
        echo $OUTPUT->notification(get_string('ordermailed', 'local_coursesoverview', $result->mailed), 'info');
        if ($result->mailed < $result->created) {
            echo $OUTPUT->notification(get_string('ordermailfailed', 'local_coursesoverview'), 'warning');
        }
    }

    echo html_writer::link($courseurl, get_string('ordergotocourse', 'local_coursesoverview'), [
        'class' => 'btn btn-secondary mr-2 me-2',
    ]);
    echo html_writer::link($participantsurl, get_string('ordergotoparticipants', 'local_coursesoverview'), [
        'class' => 'btn btn-secondary',
    ]);

    echo $OUTPUT->footer();
    die();
}

echo display::summary($summary);

if ($step === 2) {
    // Step two: what the columns mean, and what that makes of the list.
    echo $OUTPUT->heading(get_string('ordercolumns', 'local_coursesoverview'), 3);
    echo html_writer::tag('p', get_string('ordercolumns_help', 'local_coursesoverview'));

    $form = display::hidden($carried + [
        'lastdelimiter' => $delimiter,
        'lastheader' => $hasheader,
        'lastgroupheads' => $groupheads,
    ]);

    $shape = display::step_button(2, get_string('orderupdate', 'local_coursesoverview'), 'btn-secondary');
    $shape .= html_writer::label(get_string('orderdelimiter', 'local_coursesoverview'), 'coursesoverview-delimiter', true, [
        'class' => 'ml-2 ms-2 mr-2 me-2',
    ]);
    $shape .= html_writer::select(parser::delimiters(), 'delimiter', $delimiter, false, [
        'id' => 'coursesoverview-delimiter',
        'class' => 'custom-select form-select mr-3 me-3 w-auto d-inline-block',
    ]);

    // An unticked box sends nothing, so the hidden zero below it carries the
    // answer and the box overrides it when it is ticked.
    $shape .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'hasheader', 'value' => 0]);
    $shape .= html_writer::checkbox(
        'hasheader',
        1,
        (bool) $hasheader,
        get_string('orderhasheader', 'local_coursesoverview'),
        ['id' => 'coursesoverview-hasheader', 'class' => 'mr-2 me-2']
    );

    $shape .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'groupheads', 'value' => 0]);
    $shape .= html_writer::checkbox(
        'groupheads',
        1,
        (bool) $groupheads,
        get_string('ordergroupheads', 'local_coursesoverview'),
        ['id' => 'coursesoverview-groupheads', 'class' => 'ml-3 ms-3 mr-2 me-2']
    );
    $form .= html_writer::div($shape, 'mb-3');

    if ($rows) {
        $form .= display::columns_table($rows, $map, (bool) $hasheader, $headings);
    } else {
        $form .= $OUTPUT->notification(get_string('ordernolines', 'local_coursesoverview'), 'error');
    }

    foreach (columnmap::missing($map) as $problem) {
        $form .= $OUTPUT->notification($problem, 'error');
    }

    $form .= $OUTPUT->heading(get_string('orderpeople', 'local_coursesoverview'), 3);
    if ($people) {
        $flawed = 0;
        foreach ($people as $person) {
            if ($person->problems()) {
                $flawed++;
            }
        }
        $counts = (object) ['total' => count($people), 'flawed' => $flawed];
        $form .= html_writer::tag('p', get_string('orderpeoplecount', 'local_coursesoverview', $counts));
        $form .= display::groups_line($people, array_values($headings));
        $form .= output::start_tables() . display::people_table($people) . output::end_tables();
    } else {
        $form .= $OUTPUT->notification(get_string('ordernopeople', 'local_coursesoverview'), 'warning');
    }

    $ready = $people && columnmap::complete($map);
    $buttons = display::step_button(1, get_string('orderback', 'local_coursesoverview'), 'btn-secondary');
    $buttons .= display::step_button(3, get_string('ordercheck', 'local_coursesoverview'), 'btn-primary', !$ready);
    $form .= html_writer::div($buttons, 'mt-3');

    echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo $OUTPUT->footer();
    die();
}

// Step three: every line against the database, nothing created yet.
echo $OUTPUT->heading(get_string('ordercheckheading', 'local_coursesoverview'), 3);
echo html_writer::tag('p', get_string('ordercheck_help', 'local_coursesoverview'));

foreach ($blockers as $blocker) {
    echo $OUTPUT->notification(s($blocker), 'error');
}

$carried += [
    'delimiter' => $delimiter,
    'hasheader' => $hasheader,
    'groupheads' => $groupheads,
    'lastdelimiter' => $delimiter,
    'lastheader' => $hasheader,
    'lastgroupheads' => $groupheads,
    'map' => $map,
];

// The pattern can be changed right here, where its effect is seen.
unset($carried['emailpattern']);
$form = display::hidden($carried);

$patternrow = html_writer::label(
    get_string('orderemailpattern', 'local_coursesoverview'),
    'coursesoverview-emailpattern',
    true,
    ['class' => 'mr-2 me-2']
);
$patternrow .= html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'emailpattern',
    'id' => 'coursesoverview-emailpattern',
    'value' => $emailpattern,
    'size' => 40,
    'class' => 'form-control d-inline-block w-auto mr-2 me-2',
    'spellcheck' => 'false',
]);
$patternrow .= display::step_button(3, get_string('orderupdate', 'local_coursesoverview'), 'btn-secondary');
$form .= html_writer::div($patternrow, 'mb-2');

if ($guess && !$pattern) {
    // The button posts the whole form, only to a URL that asks for the guess.
    $useurl = new moodle_url($pageurl, ['useguess' => 1]);
    $usebutton = html_writer::tag('button', get_string('orderpatternuse', 'local_coursesoverview'), [
        'type' => 'submit',
        'name' => 'step',
        'value' => 3,
        'formaction' => $useurl->out(false),
        'class' => 'btn btn-link p-0 align-baseline',
    ]);
    $a = (object) ['pattern' => s($guess->pattern), 'hits' => $guess->hits, 'total' => $guess->total];
    $form .= html_writer::tag('p', get_string('orderpatternguess', 'local_coursesoverview', $a) . ' ' . $usebutton, [
        'class' => 'text-muted',
    ]);
}

$stopped = 0;
foreach ($checks as $check) {
    if ($check->blocked()) {
        $stopped++;
    }
}
$counts = (object) ['total' => count($checks), 'blocked' => $stopped];
$form .= html_writer::tag('p', get_string('ordercheckcount', 'local_coursesoverview', $counts));

if ($pattern) {
    $flagged = 0;
    foreach ($checks as $check) {
        if ($check->warnings) {
            $flagged++;
        }
    }
    $a = (object) ['flagged' => $flagged, 'total' => count($checks), 'pattern' => s($pattern->text())];
    $form .= html_writer::tag('p', get_string('orderpatterncount', 'local_coursesoverview', $a));
}

$existinggroups = $existing ? array_column(groups_get_all_groups($course->id), 'name') : [];
$form .= display::groups_line(array_column($checks, 'person'), [], $existinggroups);

$form .= output::start_tables() . display::checks_table($checks) . output::end_tables();

$buttons = display::step_button(2, get_string('orderback', 'local_coursesoverview'), 'btn-secondary');
// A late report creates no course, only accounts.
$createlabel = get_string($existing ? 'orderaddcreate' : 'ordercreate', 'local_coursesoverview');
$buttons .= display::step_button(4, $createlabel, 'btn-primary', $blocked);
$form .= html_writer::div($buttons, 'mt-3');

echo html_writer::tag('form', $form, ['method' => 'post', 'action' => $pageurl->out(false)]);

echo $OUTPUT->footer();
