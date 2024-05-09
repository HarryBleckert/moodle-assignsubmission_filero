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
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
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
        $config = get_config('assignsubmission_filero');

        if (!$config->use_archiving){
            return;
        }

        assignsubmission_filero_observer::observer_log("\nObserver for submission_graded has been called by event handler");
        // catch looping calls of this observer class
        static $filero_feedback = 1;
        if ($filero_feedback > 1) {
            assignsubmission_filero_observer::observer_log("Observer for submission_graded is looping (#" . $filero_feedback . ")");
            return;
        }
        /* assignment id userid timecreated timemodified grader grade attemptnumber */
        if (!$grade = $DB->get_record('assign_grades', array('id' => $event->objectid))) {
            assignsubmission_filero_observer::observer_log("No assign_grades record with id $event->objectid!");
            return;
        }
        // Task: Only archive feedbacks if submission has been graded!
        if ( !($grade->grade >0) AND $config->archive_feedback_after_grading ){
            assignsubmission_filero_observer::observer_log("No archiving: Record assign_grades with id $event->objectid has not been graded yet.");
            return;
        }

        assignsubmission_filero_observer::observer_log("Table assign_grades with id=" . $event->objectid . "!");
        /* id assignment userid timecreated timemodified timestarted status groupid attemptnumber latest */
        if (!$submission = $DB->get_record('assign_submission',
                array('userid' => $grade->userid, 'assignment' => $grade->assignment))) {
            assignsubmission_filero_observer::observer_log("No archiving with 'userid'=>$grade->userid,'assignment'=>$grade->assignment!");
            return;
        }

        // no archiving if multiple graders and student assignmend
        if (assign_submission_filero::is_student_assignment($submission)){
            assignsubmission_filero_observer::delete_filero_file_grade_records($grade->id);
            assignsubmission_filero_observer::observer_log("No feedback archiving for assignment $grade->assignment: This is a student assignment.");
            return;
        }

        assignsubmission_filero_observer::observer_log(
                "Start archiving of feedback for 'userid'=>$grade->userid,'assignment'=>$grade->assignment!");
        assignsubmission_filero_observer::archive_feedback($submission, $grade);
        $filero_feedback++;
        //return false;
        return true;
    }

    /**
     * Listen to process_submission_statement_accepted and save submission statement.
     * Note: Th event process_submission_statement_accepted is triggered AFTER assessable submitted!!!
     * @param statement_accepted $event
     */

    public static function delete_filero_file_grade_records($gradeid){
        global $DB;
        if (!empty($DB->get_records('assignsubmission_filero_file',
                array('grade' => $gradeid)))
        ) {
            $DB->delete_records('assignsubmission_filero_file',
                    array('grade' => $gradeid));
        }
    }
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
        if (!$submission) {
            assignsubmission_filero_observer::observer_log(
                    "No submission with 'userid'=>$submission->userid,'assignment'=>$submission->assignment!");
            return false;
        }
        $assignment = $DB->get_record('assign', array('id' => $submission->assignment));
        if ( !isset($assignment->requiresubmissionstatement) OR !$assignment->requiresubmissionstatement){
            assignsubmission_filero_observer::observer_log(
                    "statement_accepted: submissionstatement is not required in '$assignment->name. Aborting.");
            return false;
        }
        assignsubmission_filero_observer::observer_log(
                "statement_accepted: Start saving of statement_accepted for 'userid'=$submission->userid and 'assignment'=$submission->assignment");

        $filerorecord = $DB->get_record('assignsubmission_filero', array('submission' => $submission->id));
        if (!$filerorecord) {
            $grade = $DB->get_record('assign_grades',
                    array('assignment' => $submission->assignment, "userid" => $submission->userid));
            $filerorecord = new stdclass;
            $filerorecord->assignment = $submission->assignment;
            $filerorecord->submission = $submission->id;
            $filerorecord->grade = $grade->id;
            $filerorecord->userid = $submission->userid;
            $filerorecord->filerocode = $filerorecord->fileroid = $filerorecord->numfiles = 0;
            $filerorecord->feedbacktimecreated = $filerorecord->feedbacktimemodified = time();
            $filerorecord->id = $DB->insert_record("assignsubmission_filero", $filerorecord);
        }

        if ($filerorecord) {
            // assignsubmission_filero_observer::statement_accepted($assignsubmission_filero);
            //assignsubmission_filero_observer::observer_log("Event: " .print_r($event,true));
            $statement_accepted = assignsubmission_filero_observer::get_statement_accepted($submission);
            $filerorecord->statement_accepted = $statement_accepted;
            assignsubmission_filero_observer::observer_log($statement_accepted);
            $DB->update_record('assignsubmission_filero', $filerorecord);

            // handle multiple graders
            // $assignmentname = $assignment->name;
            $assignmentcourse = $assignment->course;
            $configfilero = get_config('assignsubmission_filero');
            $multiple_graders = $configfilero->multiple_graders;
            $grading_title_tag = $configfilero->grading_title_tag;
            $submission_title_tag = $configfilero->submission_title_tag;
            if (isset($multiple_graders) and $multiple_graders) {
                assignsubmission_filero_observer::observer_log("statement_accepted: multiple_graders");
                $assignments = $DB->get_records('assign', array('course' => $assignmentcourse), 'id DESC');
                foreach ($assignments as $assignment) {
                    // loop if not grader assignment
                    if ($assignment->id == $submission->assignment) { // AND $action == "duplicate"){
                        assignsubmission_filero_observer::observer_log(
                                "statement_accepted: ignore current assignment ".$assignment->name);
                        continue;
                    }
                    elseif (!stristr($assignment->name, $submission_title_tag) and !stristr($assignment->name, $grading_title_tag)) {
                        //assignsubmission_filero_observer::observer_log("grader_submissions: "
                        //        ."Assignment " .$assignment->name." was ignored. It is not tagged with '$submission_title_tag' or '$grading_title_tag' in title");
                        continue;
                    }
                    if ($filerorec = $DB->get_record('assignsubmission_filero',
                            array('assignment' => $assignment->id, 'userid' => $submission->userid))) {
                        $filerorec->statement_accepted = $statement_accepted;
                        $DB->update_record('assignsubmission_filero', $filerorec);
                        assignsubmission_filero_observer::observer_log("statement_accepted: updated submission $filerorec->submission");
                    }
                }
            }
        }
        $filero_feedback++;
        //return false;
        return true;
    }

    public static function get_observer_logfilename() {
        global $CFG;
        $logfile = $CFG->dataroot . "/filero/observer.log";
        //print nl2br($txt);
        return $logfile;
    }

    public static function observer_log($txt) {
        $logfile = assignsubmission_filero_observer::get_observer_logfilename();
        file_put_contents($logfile, (date("Y-m-d H:i:s") . " " . $txt . "\n"), FILE_APPEND);
        //print nl2br($txt);
        return true;
    }

    public static function get_statement_accepted($submission){
        global $DB;
        $assignment = $DB->get_record('assign', array('id' => $submission->assignment));
        if ( !isset($assignment->requiresubmissionstatement) OR !$assignment->requiresubmissionstatement){
            assignsubmission_filero_observer::observer_log(
                    "statement_accepted: submissionstatement is not required in '$assignment->name. No response.");
            return "Es musste keine Eigenständigkeitserklärung abgegeben werden.";
        }

        $fullname = "Der/die Teilnehmer_in mit userid: $submission->userid";
        if ($student = core_user::get_user($submission->userid)) {
            $fullname = $student->firstname . " " . $student->lastname . " (userid: $submission->userid)";
        }
        $configassign = get_config('assign');
        $submissionstatement = $configassign->submissionstatement;
        $statement_accepted = $fullname ." hat mit der Abgabe am "
                . date('d.m.Y \u\m H:i:s', $submission->timemodified)
                . (isset($_SERVER['REMOTE_ADDR']) ? " (IP: " . $_SERVER['REMOTE_ADDR'] . ")" : "")
                . " diese Eigenständigkeitserklärung abgegeben"

                . ': "' . $submissionstatement . '"';
        return $statement_accepted;
    }

    /**
     * Carry out any extra processing required when the work is submitted for grading
     *
     * @param stdClass $submission the assign_submission record being submitted.
     * @return void
     */
    public static function archive_feedback($submission, $grade = false) {
        // Used by Filero
        /* Needs to be called on event \mod_assign\event\submission_graded
         * $string['eventassessablesubmitted'] = 'A submission has been submitted.';
         *
        */
        global $DB;
        // File areas for file feedback assignment.
        if (!defined('assignfeedback_file_FILEAREA')) {
            define('assignfeedback_file_FILEAREA', 'feedback_files');
        }
        // require_once($CFG->dirroot . '/mod/assign/submission/filero/lib.php');
        $assign = $DB->get_record("assign", array("id" => $submission->assignment));
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $context = context_module::instance($cm->id);
        $course = get_course($assign->course);
        if (!$grade and !$grade = $DB->get_record('assign_grades',
                        array('assignment' => $submission->assignment, "userid" => $submission->userid))
        ) {
            assignsubmission_filero_observer::observer_log("No assign_grades with 'assignment'=>$submission->assignment, 'userid' => $submission->userid!");
            return;
        }

        //$_SESSION["debugfilero"] = true;
        $filerorecord = $DB->get_record('assignsubmission_filero', array('submission' => $submission->id));
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id,
                'assignfeedback_file',
                assignfeedback_file_FILEAREA,
                $grade->id,
                'id',
                false);

        /* removed on Nov 14 because DHBW doesn't need this
        $files += $fs->get_area_files($context->id,
                'assignfeedback_editpdf',
                'combined',
                $grade->id,
                'id',
                false);
        */
        //assignsubmission_filero_observer::observer_log("Files: " . print_r($files,true));
        $count = ($files ? count($files) : 0);
        if (empty($count)) {
            $_SESSION["debugfilero"] = false;
            assignsubmission_filero_observer::observer_log("observer: archive_feedback: No feedback files!");
            assignsubmission_filero_observer::delete_filero_file_grade_records($grade->id);
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
            if (!$filerorecord) {
                $filerorecord = new stdclass;
                $filerorecord->assignment = $submission->assignment;
                $filerorecord->submission = $submission->id;
                $filerorecord->grade = $grade->id ?:0;
                $filerorecord->userid = $submission->userid;
                $filerorecord->feedbacktimecreated = time();
                $filerorecord->feedbacktimemodified = time();
                $filerorecord->filerocode = $filerorecord->fileroid
                        = $filerorecord->numfiles = 0;
                $filerorecord->id = $DB->insert_record("assignsubmission_filero", $filerorecord);
            }
            $numfiles = $DB->get_records('assignsubmission_filero_file', array('submission' => $submission->id));
            if (is_countable($numfiles)) {
                $count = count($numfiles);
            }
            $filerorecord->numfiles = $count;
            $filerorecord->filerocode = $fileroRes->filerocode;
            $filerorecord->lasterrormsg = $fileroRes->fileromsg;
            $filerorecord->fileroid = $fileroRes->fileroid;
            $filerorecord->userid = $submission->userid;
            $filerorecord->feedbacktimecreated = $fileroRes->filerotimecreated;
            $filerorecord->feedbacktimemodified = $fileroRes->filerotimemodified;
            $filerorecord->filerovalidated = $fileroRes->filerovalidated;
            $DB->update_record('assignsubmission_filero', $filerorecord);
            if (isset($fileroRes->validated_files) and is_countable($fileroRes->validated_files)) {
                $submittedfiles = new stdClass();
                $submittedfiles->fileroid = $fileroRes->fileroid;
                $submittedfiles->assignment = $assign->id;
                $submittedfiles->submission = $submission->id;
                $submittedfiles->userid = $submission->userid;
                $submittedfiles->grade = $grade->id ?:0;
                assignsubmission_filero_observer::delete_filero_file_grade_records($grade->id);
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
