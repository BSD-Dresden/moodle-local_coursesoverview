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
 * Admin tree entry.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/coursesoverview:view', context_system::instance())) {
    $ADMIN->add('courses', new admin_externalpage(
        'localcoursesoverview',
        get_string('overview', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/index.php'),
        'local/coursesoverview:view'
    ));
}

if ($hassiteconfig || has_capability('local/coursesoverview:createorder', context_system::instance())) {
    $ADMIN->add('courses', new admin_externalpage(
        'localcoursesovervieworder',
        get_string('neworder', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/order.php'),
        'local/coursesoverview:createorder'
    ));
}

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'localcoursesoverviewsettings',
        get_string('settings', 'local_coursesoverview'),
        'moodle/site:config'
    );

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/notifyemails',
        get_string('notifyemails', 'local_coursesoverview'),
        get_string('notifyemails_desc', 'local_coursesoverview'),
        '',
        PARAM_NOTAGS
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_coursesoverview/ordermodules',
        get_string('ordermodules', 'local_coursesoverview'),
        get_string('ordermodules_desc', 'local_coursesoverview'),
        '',
        PARAM_RAW,
        60,
        6
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/orderpattern',
        get_string('orderpattern', 'local_coursesoverview'),
        get_string('orderpattern_desc', 'local_coursesoverview'),
        '{module} {company} (AB {abnr})',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/ordermonths',
        get_string('ordermonths', 'local_coursesoverview'),
        get_string('ordermonths_desc', 'local_coursesoverview'),
        2,
        PARAM_INT
    ));

    $ADMIN->add('courses', $settings);
}
