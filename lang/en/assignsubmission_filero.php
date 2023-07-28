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
 * Strings for component 'assignsubmission_filero', language 'en'
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

$string['location'] = 'FILERO SOAP URL';
$string['location_help'] = 'The complete FILERO SOAP URL must be entered here';
$string['username'] = 'FILERO user name';
$string['username_help'] = 'User name of FILERO account';
$string['password'] = 'FILERO password';
$string['password_help'] = 'Password of FILERO account';
$string['productkey'] = 'FILERO product key';
$string['productkey_help'] = 'The so-called "product key" is assigned by FILERO';
$string['multiple_graders'] = 'Multiple graders';
$string['multiple_graders_help'] = 'If set, submissions from assignments tagged as submission assignments '
        . 'will be copied to all assignments of the same user tagged as grading assignments.';
$string['exam_submission'] = 'Exam submission';
$string['exam_grading'] = 'Exam grading';
$string['submission_title_tag'] = 'Please enter a text tag for submission assignments';
$string['submission_title_tag_help'] =
        'Submission assignments can\'t be graded and all submissions will be duplicated to existing grader assignments';
$string['grading_title_tag'] = 'Please enter a text tag for grading assignments';
$string['grading_title_tag_help'] =
        'Grading assignments will not allow submissions, but receive duplicates of submissions from submission assignment';
$string['grader_roles'] = 'Allowed grader role';
$string['grader_roles_help'] = 'Please select the role of graders';

$string['countfiles'] = '{$a} files';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['defaultacceptedfiletypes'] = 'Default accepted file types';
$string['enabled'] = 'FILERO archiving';
$string['enabled_help'] = 'If enabled, files and data of submissions and feedbacks will be archived with FILERO.';
$string['eventfileroarchived'] = 'File(s) have been archived with FILERO.';
$string['filero'] = 'FILERO Archiving';
$string['fileroenabled'] = 'FILERO archiving of file submissions and feedback files';
$string['fileroenabled_help'] = 'Check atctivates legally assured archiving with FILERO';
$string['filerofilename'] = 'filero.html';
$string['filerosubmission'] = 'Allow archiving with Filero';
$string['numfilesforlog'] = 'Number of files: {$a}.';
$string['pluginname'] = 'FILERO archiving';
$string['submissionfilearea'] = 'Uploaded submission and feedback files';
