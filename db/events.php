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
 * Filero event handler definition.
 *
 * @package   assignsubmission_filero
 * @copyright 2016 Marina Glancy
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

// List of observers.
$observers = array(
    /*
     *
     array(
     'eventname'   => '\assignsubmission_comments\event\comment_created',
     'callback'    => 'assignsubmission_filero_observer::submission_comments_comment_created',
     ),
     array(
         'eventname'   => '\assignsubmission_comments\event\comment_deleted',
         'callback'    => 'assignsubmission_filero_observer::submission_comments_comment_deleted',
         // 'includefile' => '/mod/assign/feedback/file/locallib.php',
     ),
     */
        array(
                'eventname' => '\mod_assign\event\submission_graded',
                'callback' => 'assignsubmission_filero_observer::process_submission_graded'
        ),
        array(
                'eventname' => '\mod_assign\event\statement_accepted',
                'callback' => 'assignsubmission_filero_observer::process_submission_statement_accepted'
        )

);
