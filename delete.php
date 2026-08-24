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
 * Delete a settled course together with the accounts nobody else needs.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursesoverview\purge;

$courseid = required_param('courseid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$checked = optional_param('checked', 0, PARAM_BOOL);

require_login();

$course = get_course($courseid);
$context = context_course::instance($courseid);
$overviewurl = new moodle_url('/local/coursesoverview/index.php');

// Deleting the course and deleting accounts are two different powers, and this
// page needs both.
if ($course->id == SITEID || !can_delete_course($courseid)) {
    throw new moodle_exception('cannotdeletecourse', 'error');
}
require_capability('moodle/user:delete', context_system::instance());
require_capability('local/coursesoverview:view', context_system::instance());

// A course is hidden once it has been settled. Requiring that first means
// nothing can be deleted that was not deliberately marked as finished.
if ($course->visible) {
    throw new moodle_exception('deletenothidden', 'local_coursesoverview', $overviewurl->out());
}

// The page survives the deletion by living in the category context.
$PAGE->set_url('/local/coursesoverview/delete.php', ['courseid' => $courseid]);
$PAGE->set_context(context_coursecat::instance($course->category));
$PAGE->set_pagelayout('admin');

$coursename = format_string($course->fullname, true, ['context' => $context]);
$pagetitle = get_string('deleteheading', 'local_coursesoverview', $coursename);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($SITE->fullname);

// The plan is worked out again here rather than trusted from the form. What is
// deleted is what is true now, not what was true when the page was drawn.
$plan = purge::plan($courseid, $context);

if ($confirm && $checked && confirm_sesskey()) {
    $deleted = 0;

    foreach ($plan['delete'] as $user) {
        $record = $DB->get_record('user', ['id' => $user->id]);
        if ($record && delete_user($record)) {
            $deleted++;
        }
    }

    delete_course($course, false);
    fix_course_sortorder();

    $a = (object) ['course' => $coursename, 'users' => $deleted];
    redirect(
        $overviewurl,
        get_string('deletedone', 'local_coursesoverview', $a),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

if ($confirm && !$checked) {
    echo $OUTPUT->notification(get_string('deleteunchecked', 'local_coursesoverview'), 'error');
}

echo $OUTPUT->notification(get_string('deleteirreversible', 'local_coursesoverview'), 'warning');

if (empty($plan['delete'])) {
    echo html_writer::tag('p', get_string('deletenousers', 'local_coursesoverview'));
} else {
    $count = count($plan['delete']);
    echo html_writer::tag('h3', get_string('deleteusers', 'local_coursesoverview', $count));

    $items = '';
    foreach ($plan['delete'] as $user) {
        $items .= html_writer::tag('li', s(fullname($user)) . ' — ' . s($user->email));
    }
    echo html_writer::tag('ul', $items);
}

if (!empty($plan['keep'])) {
    $count = count($plan['keep']);
    echo html_writer::tag('h3', get_string('keepusers', 'local_coursesoverview', $count));

    $items = '';
    foreach ($plan['keep'] as $entry) {
        $name = s(fullname($entry['user']));
        $items .= html_writer::tag('li', $name . ' — ' . s($entry['reason']));
    }
    echo html_writer::tag('ul', $items);
}

$form = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$checkbox = html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'checked',
    'value' => 1,
    'id' => 'coursesoverview-deletechecked',
    'class' => 'mr-2 me-2',
]);
$label = html_writer::tag(
    'label',
    $checkbox . get_string('deletechecked', 'local_coursesoverview'),
    ['for' => 'coursesoverview-deletechecked']
);
$form .= html_writer::div($label, 'mb-3');

$form .= html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('deleteconfirm', 'local_coursesoverview'),
    'class' => 'btn btn-danger mr-2 me-2',
]);
$form .= html_writer::link($overviewurl, get_string('cancel'), ['class' => 'btn btn-secondary']);

echo html_writer::tag('form', $form, [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
]);

echo $OUTPUT->footer();
