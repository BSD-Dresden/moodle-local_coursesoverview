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

// A tab of its own right after "General". Moodle folds a category into "General" while it is a short branch,
// fewer than eight entries and none of them a category, so the settings below are a category of their own.
$ADMIN->add('root', new admin_category(
    'localcoursesoverviewcategory',
    get_string('pluginname', 'local_coursesoverview')
), 'users');

if ($hassiteconfig || has_capability('local/coursesoverview:view', context_system::instance())) {
    $ADMIN->add('localcoursesoverviewcategory', new admin_externalpage(
        'localcoursesoverview',
        get_string('overview', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/index.php'),
        'local/coursesoverview:view'
    ));
}

if ($hassiteconfig || has_capability('local/coursesoverview:createorder', context_system::instance())) {
    $ADMIN->add('localcoursesoverviewcategory', new admin_externalpage(
        'localcoursesovervieworder',
        get_string('neworder', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/order.php'),
        'local/coursesoverview:createorder'
    ));
}

if ($hassiteconfig) {
    $ADMIN->add('localcoursesoverviewcategory', new admin_category(
        'localcoursesoverviewsettingscategory',
        get_string('settings', 'local_coursesoverview')
    ));

    // The page keeps its old name, order.php links to it for the modules.
    $settings = new admin_settingpage(
        'localcoursesoverviewsettings',
        get_string('settingsorders', 'local_coursesoverview'),
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

    $ADMIN->add('localcoursesoverviewsettingscategory', $settings);

    $settings = new admin_settingpage(
        'localcoursesoverviewmail',
        get_string('ordermailheading', 'local_coursesoverview'),
        'moodle/site:config'
    );

    // The minutes live under their own name in the same plugin, shown with two digits.
    $settings->add(new \local_coursesoverview\admin_setting_time(
        'local_coursesoverview/ordersendhour',
        'ordersendminute',
        get_string('ordersendtime', 'local_coursesoverview'),
        get_string('ordersendtime_desc', 'local_coursesoverview'),
        ['h' => 7, 'm' => 0]
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/ordermailsubject',
        get_string('ordermailsubject', 'local_coursesoverview'),
        get_string('ordermailsubject_desc', 'local_coursesoverview'),
        get_string('ordermailsubject_default', 'local_coursesoverview'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_confightmleditor(
        'local_coursesoverview/ordermailbody',
        get_string('ordermailbody', 'local_coursesoverview'),
        get_string('ordermailbody_desc', 'local_coursesoverview'),
        get_string('ordermailbody_default', 'local_coursesoverview')
    ));

    $ADMIN->add('localcoursesoverviewsettingscategory', $settings);

    $settings = new admin_settingpage(
        'localcoursesoverviewreminders',
        get_string('reminderheading', 'local_coursesoverview'),
        'moodle/site:config'
    );

    // The page title already says "Reminders", the heading only carries the explanation.
    $settings->add(new admin_setting_heading(
        'local_coursesoverview/reminderheading',
        '',
        get_string('reminderheading_desc', 'local_coursesoverview')
    ));

    // Numbers separated by commas, an empty field sends none of the kind.
    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/reminderafter',
        get_string('reminderafter', 'local_coursesoverview'),
        get_string('reminderafter_desc', 'local_coursesoverview'),
        \local_coursesoverview\order\reminder::DEFAULT_AFTER,
        '/^[\d,;\s]*$/'
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/reminderbefore',
        get_string('reminderbefore', 'local_coursesoverview'),
        get_string('reminderbefore_desc', 'local_coursesoverview'),
        \local_coursesoverview\order\reminder::DEFAULT_BEFORE,
        '/^[\d,;\s]*$/'
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/remindersubject',
        get_string('remindersubject', 'local_coursesoverview'),
        get_string('remindersubject_desc', 'local_coursesoverview'),
        get_string('remindersubject_default', 'local_coursesoverview'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_confightmleditor(
        'local_coursesoverview/reminderbody',
        get_string('reminderbody', 'local_coursesoverview'),
        get_string('reminderbody_desc', 'local_coursesoverview'),
        get_string('reminderbody_default', 'local_coursesoverview')
    ));

    $ADMIN->add('localcoursesoverviewsettingscategory', $settings);

    // PDF templates for the certificates of attendance. They carry the
    // company's logo and signature, which is why they live here and not in
    // the plugin. One in general, and one per module that needs its own,
    // each with the places where name and date go.
    $settings = new admin_settingpage(
        'localcoursesoverviewcertificates',
        get_string('certificates', 'local_coursesoverview'),
        'moodle/site:config'
    );
    $settings->add(new admin_setting_heading(
        'local_coursesoverview/certificateheading',
        '',
        get_string('certificates_desc', 'local_coursesoverview')
    ));
    $settings->add(new admin_setting_configtext(
        'local_coursesoverview/certificatefontsize',
        get_string('certificatefontsize', 'local_coursesoverview'),
        '',
        12,
        PARAM_INT,
        4
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_coursesoverview/certificatebold',
        get_string('certificatebold', 'local_coursesoverview'),
        '',
        1
    ));

    $templates = [0 => [
        get_string('certificatedefault', 'local_coursesoverview'),
        get_string('certificatedefault_desc', 'local_coursesoverview'),
    ]];
    foreach (\local_coursesoverview\order\module::all() as $prefix => $module) {
        $templates[\local_coursesoverview\certificate::itemid($prefix)] = [
            get_string('certificatemodule', 'local_coursesoverview', $module->name),
            get_string('certificatemodule_desc', 'local_coursesoverview'),
        ];
    }
    foreach ($templates as $itemid => [$label, $description]) {
        $settings->add(new admin_setting_heading(
            'local_coursesoverview/certificateheading_' . $itemid,
            $label,
            $description
        ));
        $settings->add(new admin_setting_configstoredfile(
            'local_coursesoverview/certificate_' . $itemid,
            get_string('certificatetemplate', 'local_coursesoverview'),
            '',
            \local_coursesoverview\certificate::FILEAREA,
            $itemid,
            ['accepted_types' => ['.pdf'], 'maxfiles' => 1]
        ));

        // A sample with a millimetre grid shows where the numbers put the text.
        $positionsdesc = get_string($itemid ? 'certificatepos_module_desc' : 'certificatepos_desc', 'local_coursesoverview');
        if (\local_coursesoverview\certificate::exists($itemid)) {
            $previewurl = new moodle_url('/local/coursesoverview/certificate.php', ['preview' => $itemid]);
            $positionsdesc .= ' ' . html_writer::link(
                $previewurl,
                get_string('certificatepreview', 'local_coursesoverview'),
                ['target' => '_blank']
            );
        }
        $settings->add(new admin_setting_configtext(
            'local_coursesoverview/certificatepos_' . $itemid,
            get_string('certificatepos', 'local_coursesoverview'),
            $positionsdesc,
            $itemid ? '' : \local_coursesoverview\certificate::DEFAULT_POSITIONS,
            '/^\s*$|^\s*\d+([.,]\d+)?([\s\/;]+\d+([.,]\d+)?){3}\s*$/',
            20
        ));
    }
    $ADMIN->add('localcoursesoverviewsettingscategory', $settings);
}
