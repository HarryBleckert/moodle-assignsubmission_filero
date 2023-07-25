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
 * This file defines the admin settings for this plugin
 *
 * @package   assignsubmission_filero
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH
 */

$settings->add(new admin_setting_configcheckbox('assignsubmission_filero/default',
        new lang_string('default', 'assignsubmission_filero'),
        new lang_string('default_help', 'assignsubmission_filero'), 0));

$element = new admin_setting_configtext('assignsubmission_filero/location',
        'location',
        new lang_string('location', 'assignsubmission_filero'),
        "https://10.20.58.10/csp/fileroapi//FLC.PKG.Moodle.Soap.Api.cls", PARAM_RAW, 72);
$settings->add($element);

$element = new admin_setting_configtext('assignsubmission_filero/username',
        'username',
        new lang_string('username', 'assignsubmission_filero'),
        "Administrator", PARAM_TEXT);
$settings->add($element);

$element = new admin_setting_configtext('assignsubmission_filero/password',
        'password',
        new lang_string('password', 'assignsubmission_filero'),
        "Admin1234!", PARAM_TEXT);
$settings->add($element);

$element = new admin_setting_configtext('assignsubmission_filero/productkey',
        'productkey',
        new lang_string('productkey', 'assignsubmission_filero'),
        "Prod436", PARAM_TEXT);
$settings->add($element);

// override assign settings if activaed
$name = new lang_string('requiresubmissionstatement', 'mod_assign');
$description = new lang_string('requiresubmissionstatement_help', 'mod_assign');
$element = new admin_setting_configcheckbox('assignsubmission_filero/requiresubmissionstatement',
        $name,
        $description,
        1);
$settings->add($element);

// override assign settings if activaed
$name = new lang_string('multiple_graders', 'assignsubmission_filero');
$description = new lang_string('multiple_graders_help', 'assignsubmission_filero');
$element = new admin_setting_configcheckbox('assignsubmission_filero/multiple_graders',
        $name,
        $description,
        1);
$settings->add($element);

$name = new lang_string('title_tag', 'assignsubmission_filero');
$description = new lang_string('title_tag_help', 'assignsubmission_filero');
$element = new admin_setting_configtext('assignsubmission_filero/title_tag',
        $name,
        $description,
        "Abgabe Bachelorarbeiten", PARAM_TEXT);
$settings->add($element);

$name = new lang_string('grader_roles', 'assignsubmission_filero');
$description = new lang_string('grader_roles_help', 'assignsubmission_filero');
$element = new admin_setting_configtext('assignsubmission_filero/grader_roles',
        $name,
        $description,
        "3", PARAM_TEXT);
$settings->add($element);


// "multiple graders", title tag and roles of graders
// submissiondrafts = Abgabebestätigung. Muss immer gesetzt sein für Filero, sonst kein submit_for_grading
// wird über assignsubmission_filero_validate_settings() beim requir von locallib  validiert und gesetzt
/*  $name = new lang_string('submissiondrafts', 'mod_assign');
$description = new lang_string('submissiondrafts_help', 'mod_assign');
$setting = new admin_setting_configcheckbox('assignsubmission_filero/submissiondrafts',
        $name,
        $description,
        1);
$settings->add($element);
*/

