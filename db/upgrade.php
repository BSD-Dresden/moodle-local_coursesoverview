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
 * Database upgrades.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion the version the site is coming from
 * @return bool
 */
function xmldb_local_coursesoverview_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026102800) {
        // The plugin had no tables of its own until orders arrived, so an
        // existing installation needs them created here.
        $table = new xmldb_table('local_coursesoverview_order');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('company', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('abnr', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('module', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usernamescheme', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('delivery', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('senddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('usercreated', XMLDB_KEY_FOREIGN, ['usercreated'], 'user', ['id']);
        $table->add_index('senddate-timesent', XMLDB_INDEX_NOTUNIQUE, ['senddate', 'timesent']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_coursesoverview_orderuser');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('orderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reused', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('orderid', XMLDB_KEY_FOREIGN, ['orderid'], 'local_coursesoverview_order', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('orderid-userid', XMLDB_KEY_UNIQUE, ['orderid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026102800, 'local', 'coursesoverview');
    }

    if ($oldversion < 2026102900) {
        // Courses deleted before the observer existed left their orders
        // pointing at nothing.
        $DB->execute(
            "UPDATE {local_coursesoverview_order}
                SET courseid = NULL
              WHERE courseid IS NOT NULL
                    AND NOT EXISTS (SELECT 1 FROM {course} c WHERE c.id = {local_coursesoverview_order}.courseid)"
        );

        upgrade_plugin_savepoint(true, 2026102900, 'local', 'coursesoverview');
    }

    if ($oldversion < 2026102901) {
        // People reported late can get their credentials differently from
        // the order they join, and on a day of their own, so both move from
        // the order to the person.
        $table = new xmldb_table('local_coursesoverview_orderuser');

        $field = new xmldb_field('delivery', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '', 'reused');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('senddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'delivery');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('senddate-timesent', XMLDB_INDEX_NOTUNIQUE, ['senddate', 'timesent']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Everybody so far was part of the order that created them.
        $DB->execute(
            "UPDATE {local_coursesoverview_orderuser}
                SET delivery = (SELECT o.delivery
                                  FROM {local_coursesoverview_order} o
                                 WHERE o.id = {local_coursesoverview_orderuser}.orderid)"
        );

        upgrade_plugin_savepoint(true, 2026102901, 'local', 'coursesoverview');
    }

    if ($oldversion < 2026102903) {
        // The customer's address pattern, so that a late report is checked
        // against the same one.
        $table = new xmldb_table('local_coursesoverview_order');
        $field = new xmldb_field('emailpattern', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'delivery');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026102903, 'local', 'coursesoverview');
    }

    if ($oldversion < 2026103002) {
        // The final numbers of an order, kept when its course is deleted so
        // that the list of orders can still show them.
        $table = new xmldb_table('local_coursesoverview_order');
        $fields = [
            new xmldb_field('timefrozen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated'),
            new xmldb_field('finaltotal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timefrozen'),
            new xmldb_field('finalcompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'finaltotal'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026103002, 'local', 'coursesoverview');
    }

    return true;
}
