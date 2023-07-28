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

use assignsubmission_filero\event\feedback_file_archived;
use mod_assign\event\statement_accepted;
use mod_assign\event\submission_graded;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers supported by this module
 *
 * @package   assignsubmission_filero
 * @copyright 2016 Marina Glancy
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH
 */
class assignsubmission_filero_observer {

    /**
     * Observer for the even course_content_deleted - delete all course templates.
     *
     * @aram \core\event\course_content_deleted $event
     */
    /*
     public static function submission_comments_comment_created(\mod_assign\event\submission_updated $event) {
         global $DB;

     }
     public static function submission_comments_comment_deleted(\mod_assign\event\submission_updated $event) {
         global $DB;

     }
     */

    /**
     * Listen to events and process Filero archiving.
     *
     * @param submission_graded $event
     */
    public static function process_submission_graded(submission_graded $event) {
        // public static function process_submission_graded($event) {
        global $DB;
        assignsubmission_filero_observer::observer_log("\nObserver for submission_graded has been called by event handler");
        // catch looping calls of this observer class
        static $filero_feedback = 1;
        if ($filero_feedback > 1) {
            assignsubmission_filero_observer::observer_log("Observer for submission_graded is looping (#" . $filero_feedback . ")");
            return;
        }
        /* assignment id userid timecreated timemodified grader grade attemptnumber */
        if (!$grade = $DB->get_record('assign_grades', array('id' => $event->objectid))) {
            assignsubmission_filero_observer::observer_log("No assign_grades with id=" . $event->objectid . "!");
            return;
        }
        assignsubmission_filero_observer::observer_log("Table assign_grades with id=" . $event->objectid . "!");
        /* id assignment userid timecreated timemodified timestarted status groupid attemptnumber latest */
        if (!$submission = $DB->get_record('assign_submission',
                array('userid' => $grade->userid, 'assignment' => $grade->assignment))) {
            assignsubmission_filero_observer::observer_log("No submission with 'userid'=>$grade->userid,'assignment'=>$grade->assignment!");
            return;
        }
        assignsubmission_filero_observer::observer_log(
                "Start archiving of feedback for 'userid'=>$grade->userid,'assignment'=>$grade->assignment!");
        assignsubmission_filero_observer::feedback_archive($submission, $grade);
        $filero_feedback++;
        //return false;
        return true;
    }

    /**
     * Listen to events and process Filero archiving.
     *
     * @param statement_accepted $event
     */

    public static function process_submission_statement_accepted(statement_accepted $event) {
        global $DB;
        assignsubmission_filero_observer::observer_log("\nObserver for submission_statement_accepted has been called by event handler");

        // catch looping calls of this observer class
        static $filero_feedback = 1;
        if ($filero_feedback > 1) {
            assignsubmission_filero_observer::observer_log(
                    "Observer for submission_statement_accepted is looping (#" . $filero_feedback . ")");
            return false;
        }
        $submission = $DB->get_record('assign_submission', array('id' => $event->objectid));
        $assign = $DB->get_record('assign', array('id' => $submission->assignment));
        if (!$submission) {
            assignsubmission_filero_observer::observer_log(
                    "No submission with 'userid'=>$grade->userid,'assignment'=>$grade->assignment!");
            return false;
        }
        assignsubmission_filero_observer::observer_log(
                "Start saving of statement_accepted for 'userid'=$submission->userid and 'assignment'=$submission->assignment");
        $assignsubmission_filero = $DB->get_record('assignsubmission_filero', array('submission' => $submission->id));
        if (!$assignsubmission_filero) {
            $grade = $DB->get_record('assign_grades',
                    array('assignment' => $submission->assignment, "userid" => $submission->userid));
            $assignsubmission_filero = new stdclass;
            $assignsubmission_filero->assignment = $submission->assignment;
            $assignsubmission_filero->submission = $submission->id;
            $assignsubmission_filero->grade = $grade->id;
            $assignsubmission_filero->userid = $submission->userid;
            $assignsubmission_filero->filerocode = $assignsubmission_filero->fileroid
                    = $assignsubmission_filero->numfiles = 0;
            $assignsubmission_filero->feedbacktimecreated = $assignsubmission_filero->feedbacktimemodified = time();
            $assignsubmission_filero->id = $DB->insert_record("assignsubmission_filero", $assignsubmission_filero);
        }

        if ($assignsubmission_filero) {
            // assignsubmission_filero_observer::statement_accepted($assignsubmission_filero);
            //assignsubmission_filero_observer::observer_log("Event: " .print_r($event,true));
            $student = core_user::get_user($submission->userid);
            $fullname = "Der Student";
            if ($student) {
                $fullname = $student->firstname . " " . $student->lastname;
            }
            $config = get_config('assign');
            $submissionstatement = $config->submissionstatement;
            $msg = "$fullname hat am "
                    . date('d.m.Y \u\m H:i:s', $event->timecreated)
                    . " diese Eigenständigkeitserklärung abgegeben."
                    . (isset($_SERVER['REMOTE_ADDR']) ? " (IP: " . $_SERVER['REMOTE_ADDR'] . ")" : "")
                    . ": ".$submissionstatement;
            $assignsubmission_filero->statement_accepted = $msg;
            assignsubmission_filero_observer::observer_log($msg);
            $DB->update_record('assignsubmission_filero', $assignsubmission_filero);
        }
        $filero_feedback++;
        //return false;
        return true;
    }

    public static function observer_log($txt) {
        global $CFG;
        $logfile = $CFG->dataroot . "/filero/observer.log";
        file_put_contents($logfile, (date("Y-m-d H:i:s") . " " . $txt . "\n"), FILE_APPEND);
        //print nl2br($txt);
        return true;
    }

    /**
     * Carry out any extra processing required when the work is submitted for grading
     *
     * @param stdClass $submission the assign_submission record being submitted.
     * @return void
     */
    public static function feedback_archive($submission, $grade = false) {
        // Used by Filero
        /* Needs to be called on event \mod_assign\event\submission_graded
         * $string['eventassessablesubmitted'] = 'A submission has been submitted.';
         *
        */
        global $USER, $DB;
        // File areas for file feedback assignment.
        if (!defined('assignfeedback_file_FILEAREA')) {
            define('assignfeedback_file_FILEAREA', 'feedback_files');
        }
        // require_once($CFG->dirroot . '/mod/assign/submission/filero/lib.php');
        $assign = $DB->get_record("assign", array("id" => $submission->assignment));
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $context = context_module::instance($cm->id);
        $course = get_course($assign->course);
        if (!$grade or !$grade = $DB->get_record('assign_grades',
                        array('assignment' => $submission->assignment, "userid" => $submission->userid))
        ) {
            assignsubmission_filero_observer::observer_log("No assign_grades with 'assignment'=>$submission->assignment, 'userid' => $submission->userid!");
            return false;
        }

        //$_SESSION["debugfilero"] = true;
        $assignsubmission_filero = $DB->get_record('assignsubmission_filero', array('submission' => $submission->id));
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id,
                'assignfeedback_file',
                assignfeedback_file_FILEAREA,
                $grade->id,
                'id',
                false);

        $files += $fs->get_area_files($context->id,
                'assignfeedback_editpdf',
                'combined',
                $grade->id,
                'id',
                false);
        //assignsubmission_filero_observer::observer_log("Files: " . print_r($files,true));
        $count = ($files ? count($files) : 0);
        if (empty($count)) {
            $_SESSION["debugfilero"] = false;
            assignsubmission_filero_observer::observer_log("No feedback files!");
            if (!empty($DB->get_records('assignsubmission_filero_file',
                    array('grade' => $grade->id)))
            ) {
                $DB->delete_records('assignsubmission_filero_file',
                        array('grade' => $grade->id));
            }
            // return;
        }

        assignsubmission_filero_observer::observer_log(
                "Calling PutMoodleAssignmentGrade to archive grade data and $count feedback file(s)");
        // .var_export($submission,true)."\n");
        $filero = new assignsubmission_filero_filero($submission, $files, assignfeedback_file_FILEAREA);
        //$filero->showAssignment(); exit;
        $fileroRes = $filero->PutMoodleAssignmentGrade();
        if (empty($fileroRes) or !isset($fileroRes->filerocode)) {
            /*
            global $OUTPUT;
            echo $OUTPUT->continue_button("/mod/assign/view.php?id=".$cm->id);
            echo $OUTPUT->footer(); exit;
            */
            assignsubmission_filero_observer::observer_log("No data or files were archived!");
            return;
        }

        if (!empty($fileroRes) and isset($fileroRes->filerocode)) {
            if (!$assignsubmission_filero) {
                $assignsubmission_filero = new stdclass;
                $assignsubmission_filero->assignment = $submission->assignment;
                $assignsubmission_filero->submission = $submission->id;
                $assignsubmission_filero->grade = $grade->id;
                $assignsubmission_filero->userid = $submission->userid;
                $assignsubmission_filero->feedbacktimecreated = time();
                $assignsubmission_filero->feedbacktimemodified = time();
                $assignsubmission_filero->filerocode = $assignsubmission_filero->fileroid
                        = $assignsubmission_filero->numfiles = 0;
                $assignsubmission_filero->id = $DB->insert_record("assignsubmission_filero", $assignsubmission_filero);
            }
            $numfiles = $DB->get_records('assignsubmission_filero_file', array('submission' => $submission->id));
            if (is_countable($numfiles)) {
                $count = count($numfiles);
            }
            $assignsubmission_filero->numfiles = $count;
            $assignsubmission_filero->filerocode = $fileroRes->filerocode;
            $assignsubmission_filero->fileroid = $fileroRes->fileroid;
            $assignsubmission_filero->feedbacktimecreated = $fileroRes->filerotimecreated;
            $assignsubmission_filero->feedbacktimemodified = $fileroRes->filerotimemodified;
            $assignsubmission_filero->filerovalidated = $fileroRes->filerovalidated;
            $DB->update_record('assignsubmission_filero', $assignsubmission_filero);
            if (isset($fileroRes->validated_files) and is_countable($fileroRes->validated_files)) {
                $submittedfiles = new stdClass();
                $submittedfiles->fileroid = $fileroRes->fileroid;
                $submittedfiles->assignment = $assign->id;
                $submittedfiles->submission = $submission->id;
                $submittedfiles->grade = $grade->id;
                //$submittedfiles->timemodified = $fileroRes->filerotimemodified;
                //$submittedfiles->timecreated = $fileroRes->filerotimemodified;
                if (!empty($DB->get_records('assignsubmission_filero_file',
                        array('grade' => $grade->id)))
                ) {
                    $DB->delete_records('assignsubmission_filero_file',
                            array('grade' => $grade->id));
                }
                foreach ($fileroRes->validated_files as $validated_file) {
                    $submittedfiles->filesid = $validated_file['filesid'];
                    $submittedfiles->filename = $validated_file['filename'];
                    $submittedfiles->filesize = $validated_file['filesize'];
                    $submittedfiles->contenthashsha1 = $validated_file['contenthashsha1'];
                    $submittedfiles->contenthashsha512 = $validated_file['contenthashsha512'];
                    $submittedfiles->timecreated = $validated_file['timecreated'];
                    $submittedfiles->timemodified = $validated_file['timemodified'];
                    $submittedfiles->filearea = $validated_file['filearea'];
                    $DB->insert_record('assignsubmission_filero_file', $submittedfiles);
                }
            }

            $assign = new assign($context, $cm, $course);
            $event = feedback_file_archived::create_from_grade($assign, $grade);
            // create($params);
            $event->set_legacy_files($files);
            $event->trigger();
        }
    }

}
