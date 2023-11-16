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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade code for install
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Stub for upgrade code
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_assignsubmission_filero_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    $newversion = 2023061900;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero_file');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('fileroid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('filesid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('contenthashsha1', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
            $table->add_field('contenthashsha512', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            // Adding keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            // Adding indexes
            $table->add_index('fileroid', XMLDB_INDEX_NOTUNIQUE, ['fileroid']);
            // Conditionally launch create table
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }

    $newversion = 2023062100;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero_file');
        $contanthashsha1 = new xmldb_field('contanthashsha1', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $contanthashsha1)) {
            $dbman->rename_field($table, $contanthashsha1, "contenthashsha1");
            $contanthashsha512 =
                    new xmldb_field('contanthashsha512', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $dbman->rename_field($table, $contanthashsha512, "contenthashsha512");
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }

    $newversion = 2023070302;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero_file');
        $field = new xmldb_field('filearea', XMLDB_TYPE_CHAR, '42', null, XMLDB_NOTNULL, null, "");
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }

    $newversion = 2023070600;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero');
        $field = new xmldb_field('filerosubmissionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, "fileroid");
        }
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('assignsubmission_filero_file');
        $field = new xmldb_field('assignment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('submission', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }

    $newversion = 2023071403;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero_file');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('assignsubmission_filero');
        $field = new xmldb_field('statement_accepted', XMLDB_TYPE_CHAR, '420', null, null, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('filerotimecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, "submissiontimecreated");
        }
        $field = new xmldb_field('filerotimemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, "submissiontimemodified");
        }

        $field = new xmldb_field('feedbacktimecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('feedbacktimemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }

    $newversion = 2023073101;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('assignsubmission_filero_file');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }


    $newversion = 2023082101;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero');
        $field = new xmldb_field('lasterrormsg', XMLDB_TYPE_CHAR, '420', null, null, null, '');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }


    $newversion = 2023111601;
    if ($oldversion < $newversion) {
        $table = new xmldb_table('assignsubmission_filero_file');
        $field = new xmldb_field('contenthashsha1', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field, true, true);
        }
        upgrade_plugin_savepoint(true, $newversion, 'assignsubmission', 'filero');
    }
    return true;
}
