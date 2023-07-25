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
 * Post-install code for the submission_filero module.
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or laterr
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Code run after the assignsubmission_filero module database tables have been created.
 * Moves the plugin to the top of the list (of 3)
 *
 * @return bool
 */
function xmldb_assignsubmission_filero_install() {
    global $CFG;

    // Set the correct initial order for the plugins.
    require_once($CFG->dirroot . '/mod/assign/adminlib.php');
    $pluginmanager = new assign_plugin_manager('assignsubmission');

    $pluginmanager->move_plugin('filero', 'up');
    $pluginmanager->move_plugin('filero', 'up');

    return true;
}
