<?php
/**
 * Admin tree entry for local_coursesoverview.
 *
 * @package local_coursesoverview
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/coursesoverview:view', context_system::instance())) {
    $ADMIN->add('courses', new admin_externalpage(
        'localcoursesoverview',
        get_string('pluginname', 'local_coursesoverview'),
        new moodle_url('/local/coursesoverview/index.php'),
        'local/coursesoverview:view'
    ));
}
