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
 * This file contains the definition for the library class for file submission plugin
 *
 * This class provides all the functionality for the new assign module.
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

use assignsubmission_filero\event\submitted_file_archived;

defined('MOODLE_INTERNAL') || die();

// File area for file submission assignment.
if (!defined('assignsubmission_file_FILEAREA')) {
    define('assignsubmission_file_FILEAREA', 'submission_files');
}
// File area for file feedback assignment.
if (!defined('assignfeedback_file_FILEAREA')) {
    define('assignfeedback_file_FILEAREA', 'feedback_files');
}

// open Filero log file
if (isset($_REQUEST['assignsubmission_filero_showLog'])) {
    $filero = new assignsubmission_filero_filero();
    if ( isset($_REQUEST['submissiontimemodified'])) {
        $filero->showLog($_REQUEST['assignsubmission_filero_showLog'], $_REQUEST['submissiontimemodified']);
    } else {
        $filero->showLog($_REQUEST['assignsubmission_filero_showLog']);
    }
    exit;
}

/**
 * Library class for filer archiving of assignment data plugin extending submission plugin base class
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/} {@link https://www.LIB-IT.de/}
 */
class assign_submission_filero extends assign_submission_plugin {

     /**
     * Carry out FILERO tasks when the work is submitted for grading
     * Event: \mod_assign\event\assessable_submitted
      * Notes: submit_for_grading() wird vor assessable_submitted und vor \mod_assign\event\statement_accepted
      * aufgerufen. Daher wäre es eventuell besser diese Methode umzubenonnen und über den Event Observer aufzurufen.
      *
     * @param stdClass $submission the assign_submission record being submitted.
     * @return boolean
     */
    public function submit_for_grading($submission) {
        global $USER, $DB;

        // disable submissions for graders
        if ( assign_submission_filero::is_graders_assignment($submission)){
            return false;
        }
        //$_SESSION["debugfilero"] = true;
        if ( !$coursemodule = get_coursemodule_from_instance('assign', $submission->assignment)){
            assignsubmission_filero_observer::observer_log(
                    "submit_for_grading(): Course Module not found for assignment $submission->assignment ".
                    " of submission $submission->id!");
            return;
        }
        $coursemodulecontext = context_module::instance($coursemodule->id);

        //$coursemodule = context_module::instance($this->assignment->get_course_module()->id);
        $statement_accepted = assignsubmission_filero_observer::get_statement_accepted($submission);
        //$filerorecord = $this->get_filero_submission($submission->id);
        $grade = $DB->get_record('assign_grades',
                array('assignment' => $submission->assignment, "userid" => $submission->userid));
        $assign = $DB->get_record('assign', array('id' => $submission->assignment));

        if (!$filerorecord = $this->get_filero_submission($submission->id)) {
            $filerorecord = new stdclass;
            $filerorecord->assignment = $submission->assignment;
            $filerorecord->submission = $submission->id;
            if ( isset($grade->id) ) {
                $filerorecord->grade = $grade->id ?: 0;
            }
            $filerorecord->userid = $submission->userid;
            $filerorecord->statement_accepted = $statement_accepted;
            $filerorecord->filerocode = $filerorecord->fileroid = $filerorecord->numfiles = 0;
            $filerorecord->id = $DB->insert_record("assignsubmission_filero", $filerorecord);
        }
        $filerorecord->statement_accepted = $statement_accepted;
        $DB->update_record('assignsubmission_filero', $filerorecord);

        $fs = get_file_storage();
        $files = $fs->get_area_files($coursemodulecontext->id, //$this->assignment->get_context()->id,
                'assignsubmission_file',
                assignsubmission_file_FILEAREA,
                $submission->id,
                'id',
                false);
        $count = $this->safeCount($files);
        if (empty($count)) {
            $_SESSION["debugfilero"] = false;
            assignsubmission_filero_observer::observer_log(
                    "submit_for_grading(): No submitted files found for submission $submission->id!");
            if (!empty($DB->get_records('assignsubmission_filero_file',
                    array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA)))
            ) {
                $DB->delete_records('assignsubmission_filero_file',
                        array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA));
            }
            return false;
        }
        if (assign_submission_filero::use_archiving($submission)) {
            $filero = new assignsubmission_filero_filero($submission, $files, assignsubmission_file_FILEAREA);
            //$filero->showAssignment(); exit;
            $fileroRes = $filero->PutMoodleAssignmentSubmission();

            if (!empty($fileroRes) and isset($fileroRes->filerocode)) {
                if ($numfiles = $this->get_archived_files($submission->id, ($count ?: 1))) {
                    $count = $numfiles;
                }
                $filerorecord->userid = $submission->userid;
                $filerorecord->numfiles = $count;
                $filerorecord->filerocode = $fileroRes->filerocode;
                $filerorecord->lasterrormsg = $fileroRes->fileromsg;
                $filerorecord->fileroid = $fileroRes->fileroid;
                $filerorecord->statement_accepted = $statement_accepted;
                $filerorecord->submissiontimecreated = $fileroRes->filerotimecreated;
                $filerorecord->submissiontimemodified = $fileroRes->filerotimemodified;
                $filerorecord->filerovalidated = $fileroRes->filerovalidated;
                if (isset($grade->id)) {
                    $filerorecord->grade = $grade->id ?: 0;
                }
                $DB->update_record('assignsubmission_filero', $filerorecord);
                if (isset($fileroRes->validated_files) and is_countable($fileroRes->validated_files)) {
                    $submittedfiles = new stdClass();
                    $submittedfiles->fileroid = $fileroRes->fileroid;
                    $submittedfiles->assignment = $submission->assignment;
                    $submittedfiles->submission = $submission->id;
                    $submittedfiles->userid = $submission->userid;
                    if (!empty($DB->get_records('assignsubmission_filero_file',
                            array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA)))
                    ) {
                        $DB->delete_records('assignsubmission_filero_file',
                                array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA));
                    }
                    foreach ($fileroRes->validated_files as $validated_file) {
                        $submittedfiles->filesid = $validated_file['filesid'];
                        $submittedfiles->filename = $validated_file['filename'];
                        $submittedfiles->filesize = $validated_file['filesize'];
                        $submittedfiles->contenthashsha1 = $validated_file['contenthashsha1'];
                        $submittedfiles->contenthashsha512 = $validated_file['contenthashsha512'];
                        $submittedfiles->filearea = $validated_file['filearea'];
                        $submittedfiles->timecreated = $validated_file['timecreated'];
                        $submittedfiles->timemodified = $validated_file['timemodified'];
                        $DB->insert_record('assignsubmission_filero_file', $submittedfiles);
                    }
                }

                $groupname = null;
                $groupid = 0;
                // Get the group name as other fields are not transcribed in the logs. This information is important.
                if (empty($submission->userid) && !empty($submission->groupid)) {
                    $groupname = $DB->get_field('groups', 'name', array('id' => $submission->groupid), MUST_EXIST);
                    $groupid = $submission->groupid;
                }
                $params = array(
                        'context' => $coursemodulecontext,
                        'courseid' => $assign->course,
                        'objectid' => $filerorecord->id,
                        'other' => array(
                                'content' => '',
                                'submissionid' => $submission->id,
                                'submissionattempt' => $submission->attemptnumber,
                                'submissionstatus' => "submitted",  // $submission->status,
                                'filesubmissioncount' => $count,
                                'groupid' => $groupid,
                                'groupname' => $groupname,
                                'pathnamehashes' => array_keys($files),
                                'fileroresults' => $fileroRes
                        )
                );

                if (!empty($submission->userid) && ($submission->userid != $USER->id)) {
                    $params['relateduserid'] = $submission->userid;
                }
                if ($assign->blindmarking) {
                    $params['anonymous'] = 1;
                }
                // need to fix debug message regarding missing #this->assign instance.
                $event = submitted_file_archived::create($params);
                $event->set_legacy_files($files);
                $event->trigger();
            }
        }
        else{
            assignsubmission_filero_observer::observer_log(
                    "submit_for_grading(): Filero Archivierung ist deaktiviert für Submission $submission->id. User ID $submission->userid!");
        }
        if (!isset($_SESSION['filero_submit_for_grading_'.$submission->id])){
            $this->grader_submissions($submission,"duplicate");
        }
        return true;
    }

    /*
     * Get submitted files information from the database
     *
     * @param int $submissionid
     * @return mixed
     */

    /**
     * Get filero archiving information from the database
     *
     * @param int $submissionid
     * @return mixed
     */
    private function get_filero_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_filero', array('submission' => $submissionid));
    }

    private function get_archived_files($submissionid, $count = false, $filearea = false) {
        global $DB;
        if (!empty($filearea)) {
            $records = $DB->get_records('assignsubmission_filero_file',
                    array('submission' => $submissionid, 'filearea' => $filearea), 'filearea DESC');
        } else {
            $records = $DB->get_records('assignsubmission_filero_file',
                    array('submission' => $submissionid), 'filearea DESC');
        }

        if ($count !== false) {
            return (is_countable($records) ? count($records) : $count);
        }
        return $records;
    }

    /**
     * Get file submission information from the database
     *
     * @param int $submissionid
     * @return mixed
     */
    private function get_file_submission($submissionid) {
        global $DB;
        return $DB->get_record('assignsubmission_file', array('submission'=>$submissionid));
    }

    /**
     * Purpose:
     * duplicate submissions to all grader assginments of user in same course
     *      - title of student's assignment for submissions: "Prüfungsabgabe"
     *      - title of assigments for grading: "Erstgutachten", "Zweitgutachten".
     *
     * Task Description:
     * Prerequisites:
     *
     *  - Added new plugin settings with defaults for "multiple graders", title tags  and grader roles to plugin global configuration form.
     *  - Not done: Add multiple graders, title tag and roles of graders to per assignment configuration options
     *  - add call to grader_submissions() after call to submit_for_grading()
     *
     * User frontend:
     *
     *  Process flow:
     * 1. Check if current assignment is flagged as "multiple graders" and title tag has been set.
     *      - if not, then return
     * 1. Open all Assignments in course which start with NameTag for Submission of Diploma, Bachelor or Master examinations.
     *      - Setting title tag
     * 2. Validate that current assignment title starts with title tag for submissions
     * 6. Synchronize all Assignments with grading title tag with all submission data from assignment with submissions ttile tag.
     *
     * @param stdClass $submission - assign_submission data if any
     * @param string action - action flag, default "duplicate'
     * @return boolean
     */
    private function grader_submissions($submission,$action="duplicate") {
        global $CFG, $DB;

        if ( !assign_submission_filero::init_multiple_graders($submission)){
            return false;
        }
        $submission_title_tag = $_SESSION['filero_submission_title_tag'];
        $grading_title_tag = $_SESSION['filero_grading_title_tag'];

        $currentsubmission = $DB->get_record('assign_submission',
                array('assignment' => $submission->assignment,'userid' => $submission->userid));
        $currentassignment = $DB->get_record('assign', array('id' => $submission->assignment));
        $assignmentname = $currentassignment->name;
        $assignmentcourse = $currentassignment->course;

        // ignore assignments that don't have tag "abgabe" in name
        if ($action == "duplicate" AND !stristr($assignmentname, $submission_title_tag)){
            assignsubmission_filero_observer::observer_log("grader_submissions: duplicate: "
                    ."Assignment " .$assignment->name." was ignored as title is not tagged as $submission_title_tag");
            return false;
        }
        /*
        if ($filerorecord = $this->get_filero_submission($currentsubmission->id)) {
            $_SESSION['filero_statement_accepted'] = $filerorecord->statement_accepted;
        }
        */
        // old version DiPa 1.1
        //$assignments = $DB->get_records('assign', array('course' => $assignmentcourse), 'id DESC');

        if (!$course =$DB->get_record('course', array('id' => $assignmentcourse), '*')) {
            assignsubmission_filero_observer::observer_log(
                    "grader_submissions(): Course with id $assignmentcourse for assignment and submission id $currentsubmission->i not found.");
            return;
        }

        $assignments = $DB->get_records_sql("SELECT a.* FROM {assign} as a
 				inner join {course} as c on c.id = a.course 
     					WHERE c.category = $course->category ORDER BY a.id DESC");
        foreach ($assignments AS $assignment ) {
            // print "<br><br><hr>Assignment: " . print_r($assignment->name, true) ."<hr>";
            // loop if not grader assignment
            if (empty($assignment->name) OR
                    (!stristr($assignment->name, $submission_title_tag) AND !stristr($assignment->name, $grading_title_tag))){
                //assignsubmission_filero_observer::observer_log("grader_submissions: "
                //        ."Assignment " .$assignment->name." was ignored. It is not tagged with '$submission_title_tag' or '$grading_title_tag' in title");
                continue;
            }
            elseif ( $assignment->id == $submission->assignment){
                // assignsubmission_filero_observer::observer_log("grader_submissions: ignore current assignment " .$assignment->name);
                continue;
            }
            if (!$this->check_enrolment($assignment->course, $submission->userid)){
                assignsubmission_filero_observer::observer_log("User $submission->userid is not enrolled in course $assignment->course");
                continue;
            }
            $destsubmission = $DB->get_record('assign_submission',
                    array('assignment' => $assignment->id,'userid' => $submission->userid));
            assignsubmission_filero_observer::observer_log("grader_submissions: Assignments loop: "
                    ."Assignment " .$assignment->name." (".$assignment->id . ") from user id $submission->userid");
            if ( isset($destsubmission->id)) {
                if ( $action == "revert" OR $action == "remove") {
                    if (!$coursemodule = get_coursemodule_from_instance('assign', $destsubmission->assignment)) {
                        assignsubmission_filero_observer::observer_log(
                                "grader_submissions(): Course Module not found for submission $destsubmission->id of assignment $assignment->name!");
                        continue;
                    }
                    $coursemodulecontext = context_module::instance($coursemodule->id);
                    $assign = new assign($coursemodulecontext, $coursemodule, $assignment->course);
                }
                if ( $action == "revert"){
                    assignsubmission_filero_observer::observer_log("grader_submissions: revert: "
                            ."Submission $destsubmission->id for assignment " .$assignment->name." from user id $submission->userid was reverted to draft");
                    // Session var to avoid looping when assign->revert_to_draft is called on filero plugin
                    $_SESSION['filero_revert_to_draft_' . $destsubmission->id] = true;
                    $assign->revert_to_draft($destsubmission->userid);
                    unset($_SESSION['filero_revert_to_draft_' . $destsubmission->id]);
                }
                elseif ( $action == "remove"){
                    assignsubmission_filero_observer::observer_log("grader_submissions: remove: "
                            ."Submission $destsubmission->id for assignment " .$assignment->name." from user id $submission->userid was removed");
                    // Session var to avoid looping when $assign->remove_submission is called on filero plugin
                    $_SESSION['filero_remove_submission_' . $destsubmission->id] = true;
                    $assign->remove_submission($destsubmission->userid);
                    unset($_SESSION['filero_remove_submission_' . $destsubmission->id]);
                }
            }
            if ( $action == "duplicate"){
                // create new submission if not exists
                if (!isset($destsubmission->id)) {
                    $destsubmission = clone $currentsubmission;
                    unset($destsubmission->id);
                    $destsubmission->assignment = $assignment->id;
                    $destsubmission->status = "submitted";
                    $destsubmission->id = $DB->insert_record('assign_submission', $destsubmission);
                    assignsubmission_filero_observer::observer_log("grader_submissions: New submission record inserted: "
                            ."Submission $destsubmission->id for assignment " .$assignment->name." from user id $submission->userid was inserted");
                }
                // loop if current submission
                if ($currentsubmission->id == $destsubmission->id) {
                    assignsubmission_filero_observer::observer_log("grader_submissions: "
                            . "Current submission $currentsubmission->id is identical with destination submission $destsubmission->id. "
                            . "Assignment " . $assignment->name . " was ignored");
                    continue;
                }

                $destsubmission->status = "submitted";
                $destsubmission->timecreated = $currentsubmission->timecreated;
                $destsubmission->timemodified = $currentsubmission->timemodified;
                if (!isset($currentsubmission->timestarted)) {
                    $currentsubmission->timestarted = $currentsubmission->timecreated;
                }
                $destsubmission->timestarted = $currentsubmission->timestarted;
                $DB->update_record('assign_submission', $destsubmission);
                $this->copy_submission_file($currentsubmission, $destsubmission);
                assignsubmission_filero_observer::observer_log("grader_submissions: Submission "
                        . $destsubmission->id . " of assignment " . $assignment->name . " updated from submission "
                        . $currentsubmission->id . " of assignment " . $assignmentname);
                if (!$coursemodule = get_coursemodule_from_instance('assign', $destsubmission->assignment)) {
                    assignsubmission_filero_observer::observer_log(
                            "grader_submissions(): Course Module not found for submission $destsubmission->id of assignment $assignment->name!");
                    continue;
                }
                $this->notify_graders( $destsubmission, $assignment, $coursemodule, $currentsubmission->id, $assignmentname);
            }
        }
        return true;
    }

    /**
     * Returns a list of users that should receive notification about given submission.
     *
     * @param int $userid The submission to grade
     * @return array
     */
    protected function get_notifiable_users($userid, $assign) {
        // Potential users should be active users only.
        $potentialusers = get_enrolled_users($assign->get_context(), "mod/assign:receivegradernotifications",
                null, 'u.*', null, null, null, true);

        $notifiableusers = array();
        if (groups_get_activity_groupmode($assign->get_course_module()) == SEPARATEGROUPS) {
            if ($groups = groups_get_all_groups($assign->get_course()->id, $userid, $assign->get_course_module()->groupingid)) {
                foreach ($groups as $group) {
                    foreach ($potentialusers as $potentialuser) {
                        if ($potentialuser->id == $userid) {
                            // Do not send self.
                            continue;
                        }
                        if (groups_is_member($group->id, $potentialuser->id)) {
                            $notifiableusers[$potentialuser->id] = $potentialuser;
                        }
                    }
                }
            } else {
                // User not in group, try to find graders without group.
                foreach ($potentialusers as $potentialuser) {
                    if ($potentialuser->id == $userid) {
                        // Do not send self.
                        continue;
                    }
                    if (!groups_has_membership($assign->get_course_module(), $potentialuser->id)) {
                        $notifiableusers[$potentialuser->id] = $potentialuser;
                    }
                }
            }
        } else {
            foreach ($potentialusers as $potentialuser) {
                if ($potentialuser->id == $userid) {
                    // Do not send self.
                    continue;
                }
                // Must be enrolled.
                if (is_enrolled($assign->get_course_context(), $potentialuser->id)) {
                    $notifiableusers[$potentialuser->id] = $potentialuser;
                }
            }
        }
        return $notifiableusers;
    }


    /**
     * Send notifications to graders upon student submissions.
     *
     * @param stdClass $submission, $assignment, $coursemodule
     * @return void
     */
    protected function notify_graders( $submission, $assignment, $coursemodule, $sourcesubmissionid, $assignmentname) {
        global $DB, $USER;

        $coursemodulecontext = context_module::instance($coursemodule->id);
        $assign = new assign($coursemodulecontext, $coursemodule, $assignment->course);

        $instance = $assign->get_instance();

        $late = $instance->duedate && ($instance->duedate < time());

        if (!$instance->sendnotifications && !($late && $instance->sendlatenotifications)) {
            // No need to do anything.
            assignsubmission_filero_observer::observer_log("grader_submissions: notify_graders: Not sent due to settings- "
                    . $submission->id . " of assignment " . $assignment->name . " created from submission "
                    . $sourcesubmissionid . " of assignment " . $assignmentname);
            return;
        }

        if ($submission->userid) {
            $user = $DB->get_record('user', array('id'=>$submission->userid), '*', MUST_EXIST);
        } else {
            $user = $USER;
        }

        if ($notifyusers = $this->get_notifiable_users($user->id, $assign)) {
            foreach ($notifyusers as $notifyuser) {
                $assign->send_notification($user,
                        $notifyuser,
                        'gradersubmissionupdated',
                        'assign_notification',
                        $submission->timemodified);
                assignsubmission_filero_observer::observer_log("grader_submissions: notify_graders: Mailed notification to "
                        . $notifyuser->firstname . " " . $notifyuser->lastname
                        . " of assignment " . $assign->name . " created from submission "
                        . $sourcesubmissionid . " of assignment " . $assignmentname);
            }
        }
    }



    /**
     * Copy the student's submission from a previous submission. Used when a student opts to base their resubmission
     * on the last submission.
     *
     * @param stdClass $sourcesubmission
     * @param stdClass $destsubmission
     */
    public function copy_submission_file(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;

        // copy links to the files across.
        $contextid = $this->assignment->get_context()->id;
        $module = get_coursemodule_from_instance('assign', $destsubmission->assignment);
        $context = context_module::instance($module->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id,
                'assignsubmission_file',
                ASSIGNSUBMISSION_FILE_FILEAREA,
                $destsubmission->id);

        /*if ( $dfilesubmission = $this->get_file_submission($destsubmission->id) ){
            $DB->delete_records('assignsubmission_file', "id" => $dfilesubmission->id);
        }*/

        $files = $fs->get_area_files($contextid,
                'assignsubmission_file',
                ASSIGNSUBMISSION_FILE_FILEAREA,
                $sourcesubmission->id,
                'id',
                false);
        foreach ($files as $file) {
            // unset($file->pathnamehash);
            // echo "<br><br><br><br><hr>File: " . print_r($file, true) ."<hr>";
            $filename = $file->get_filename();
            $fieldupdates = array('itemid' => $destsubmission->id,'contextid' => $context->id);
            $filter = "contextid=$context->id AND component='assignsubmission_file' AND filearea= 'submission_files'
                    AND itemid=$destsubmission->id AND filepath = '/' AND filename ='$filename'";

            //  $file->contextid =  $context->id;
            if ( $destfile = $DB->get_record_sql("SELECT * FROM {files} WHERE $filter limit 1" )){
                $destfile->timemodified =  time();
                $DB->update_record('files',$destfile);
            }
            else {
                $fs->create_file_from_storedfile($fieldupdates, $file);
            }
        }

        // Copy the assignsubmission_file record.
        $dfilesubmission = $this->get_file_submission($destsubmission->id);
        if ($sfilesubmission = $this->get_file_submission($sourcesubmission->id)) {
            //$sfilesubmission->status = "submitted";
            $sfilesubmission->submission = $destsubmission->id;
            $sfilesubmission->assignment = $destsubmission->assignment;
            $sfilesubmission->numfiles=$this->safeCount($files);
            if ($dfilesubmission) {
                $sfilesubmission->id = $dfilesubmission->id;
                $DB->update_record('assignsubmission_file', $sfilesubmission);
            }
            else{
                unset($sfilesubmission->id);
                $DB->insert_record('assignsubmission_file', $sfilesubmission);
            }

        }
        /* makes no sense... we always copy from titel tag assignments only */
        else{
            if($dfilesubmission){
                $dfilesubmission->numfiles=$this->safeCount($files);
                $DB->update_record('assignsubmission_file', $dfilesubmission);

            }else{
                $dfilesubmission = new stdClass();
                $dfilesubmission->submission = $destsubmission->id;
                $dfilesubmission->assignment = $destsubmission->assignment;
                $dfilesubmission->numfiles=$this->safeCount($files);
                $DB->insert_record('assignsubmission_file', $dfilesubmission);
            }
        }
        return true;
    }


    private function revert_grader_submissions($submission){
        return $this->grader_submissions($submission, "revert");
    }
    private function remove_grader_submissions($submission){
        return $this->grader_submissions($submission, "remove");
    }

    public static function is_graders_assignment($submission): bool {
        global $DB;
        if (!assign_submission_filero::init_multiple_graders($submission)) {
            return false;
        }
        $multiple_graders = $_SESSION['filero_multiple_graders'];
        $grading_title_tag = $_SESSION['filero_grading_title_tag'];
        $assignment = $DB->get_record('assign', array('id' => $submission->assignment));
        if ($multiple_graders and stristr($assignment->name, $grading_title_tag)) {
            assignsubmission_filero_observer::observer_log("is_graders_assignments: "
                    . "Assignment " . $assignment->name . " is a grader assignment");
            return true;
        }
        return false;
    }


    public static function is_student_assignment($submission): bool {
        global $DB;
        if (!assign_submission_filero::init_multiple_graders($submission)) {
            return false;
        }
        $multiple_graders = $_SESSION['filero_multiple_graders'];
        //$grading_title_t`ag = $_SESSION['filero_grading_title_tag'];
        $submission_title_tag = $_SESSION['filero_submission_title_tag'];
        $assignment = $DB->get_record('assign', array('id' => $submission->assignment));
        if ($multiple_graders and stristr($assignment->name, $submission_title_tag)) {
            assignsubmission_filero_observer::observer_log("is_student_assignment: "
                    . "Assignment " . $assignment->name . " is a Student assignment");
            return true;
        }
        return false;
    }

    public static function archive_feedback_after_grading($submission): bool{
        $config = get_config('assignsubmission_filero');
        $_SESSION['filero_archive_feedback_after_grading'] = $config->archive_feedback_after_grading;
        return $_SESSION['filero_archive_feedback_after_grading'] ?:false;
    }

    public static function init_multiple_graders($submission): bool{
        $config = get_config('assignsubmission_filero');
        $_SESSION['filero_multiple_graders'] = $config->multiple_graders;
        $_SESSION['filero_grading_title_tag'] = $config->grading_title_tag;
        $_SESSION['filero_submission_title_tag'] = $config->submission_title_tag;
        $_SESSION['filero_archive_feedback_after_grading'] = $config->archive_feedback_after_grading;
        return $_SESSION['filero_multiple_graders'] ?:false;
    }

    public static function use_archiving($submission): bool{
        $config = get_config('assignsubmission_filero');
        $_SESSION['filero_use_archiving'] = $config->use_archiving;
        return $_SESSION['filero_use_archiving'] ?:false;
    }

    /**
     * Carry out any extra processing required when the work reverted to draft.
     * Used here for removing Filero Submissions
     *
     * @param stdClass $submission - assign_submission data
     * @return void
     */
    public function revert_to_draft(stdClass $submission) {
        global $DB;
        $currentsubmission = $this->get_filero_submission($submission->id);
        // set filero record to default 0
        if ($currentsubmission) {
            $fileroRecs = $DB->get_records('assignsubmission_filero',
                    array('submission' => $submission->id));
            foreach ($fileroRecs as $fileroRec) {
                if ($DB->get_records('assignsubmission_filero_file',
                        array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA))
                ) {
                    $DB->delete_records('assignsubmission_filero_file',
                            array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA));
                }
            }
            /*$DB->delete_records('assignsubmission_filero',
                            array('submission'=>$submission->id));
            */
            if (!assign_submission_filero::use_archiving($submission)){
                return true;
            }
            // Delete Filero record of submission
            $filero = new assignsubmission_filero_filero($submission);
            $filero->DeleteMoodleAssignmentSubmission();

            // $currentsubmission->fileroid =
            $currentsubmission->numfiles = $currentsubmission->filerocode
                    = $currentsubmission->submissiontimecreated
                    = $currentsubmission->submissiontimemodified = 0;
            $currentsubmission->statement_accepted = $currentsubmission->lasterrormsg = "";
            $DB->update_record('assignsubmission_filero', $currentsubmission);
            if ( !isset($_SESSION['filero_revert_to_draft_' . $submission->id])) {
                $this->revert_grader_submissions($submission);
            }
        }
    }



    /**
     * Carry out any extra processing required when the work is locked.
     *
     * @param stdClass|false $submission - assign_submission data if any
     * @param stdClass $flags - User flags record
     * @return void
     */
    // public function lock($submission, stdClass $flags) {
    // }

    /**
     * Carry out any extra processing required when the work is unlocked.
     *
     * @param stdClass|false $submission - assign_submission data if any
     * @param stdClass $flags - User flags record
     * @return void
     */
    // public function unlock($submission, stdClass $flags) {
    // }

    /**
     * Get the default setting for file submission plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        /*
		global $CFG, $COURSE;
        if ($this->assignment->has_instance()) {
            $defaultenabled = $this->get_config('fileroenabled');
        } else {
            $defaultenabled = get_config('assignsubmission_filero','fileroenabled');
        }
        */
        /*
        // Filero per assignment settings
        * This is the place for any per assignment settings like: yet to be defined!
        */
        /*
        $name = get_string('fileroenabled', 'assignsubmission_filero');
        $mform->addElement('checkbox', 'assignsubmission_filero_fileroenabled', $name);
        $mform->addHelpButton('assignsubmission_filero_fileroenabled', 'fileroenabled', 'assignsubmission_filero');
        $mform->setDefault('assignsubmission_filero_fileroenabled', $defaultenabled); // "notchecked");
        $mform->hideIf('assignsubmission_filero_fileroenabled', 'assignsubmission_filero_enabled', 'notchecked');
        // $mform->hideIf('assignsubmission_file_enabled', 'assignsubmission_filero_enabled', 'checked');
        // $mform->hideIf('assignsubmission_filero_enabled', 'assignsubmission_file_enabled', 'checked');
        */
        // return true;
    }


    /**
     * Save the settings for file submission plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        /*
         // set Filero assignment settings
         //$this->set_config('fileroenabled', 0);
         if ( !empty($data->assignsubmission_filero_fileroenabled)) {
             $this->set_config('fileroenabled', $data->assignsubmission_filero_fileroenabled);
         } else {
             $this->set_config('fileroenabled', "0");
         }
         */
        return true;
    }

    /**
     * Remove files from this submission.
     *
     * @param stdClass $submission The submission
     * @return boolean
     */
    public function remove(stdClass $submission) {
        global $DB;
        $filerosubmission = $this->get_filero_submission($submission->id);
        if ($filerosubmission) {
            $fileroRecs = $DB->get_records('assignsubmission_filero',
                    array('submission' => $filerosubmission->submission));
            foreach ($fileroRecs AS $fileroRec) {
                if ($DB->get_records('assignsubmission_filero_file',
                        array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA))
                ) {
                    $DB->delete_records('assignsubmission_filero_file',
                            array('submission' => $submission->id, 'filearea' => assignsubmission_file_FILEAREA));
                }
            }

            if (!assign_submission_filero::use_archiving($submission)){
                return true;
            }
            // Empty Filero record of submission
            $filero = new assignsubmission_filero_filero($submission);
            $filero->DeleteMoodleAssignmentSubmission();

            /*$filerosubmission->numfiles = $filerosubmission->filerocode
                    = $filerosubmission->submissiontimecreated
                    = $filerosubmission->submissionimemodified = 0;
            $filerosubmission->statement_accepted = "";
            */
            $DB->delete_records('assignsubmission_filero', array("id" => $filerosubmission->id));
            assignsubmission_filero_observer::observer_log("assignsubmission_filero: remove: "
                    ."Filero data of submission $submission->id from user id $submission->userid was removed");
        }
        if ( !isset($_SESSION['filero_remove_submission_' . $submission->id])) {
            assignsubmission_filero_observer::observer_log("assignsubmission_filero: remove: "
                    ."calling remove_grader_submissions for submission $submission->id from user id $submission->userid was removed");
            $this->remove_grader_submissions($submission);
        }
        return true;
    }

    /**
     * Produce a list of files suitable for export that represent this feedback or submission
     *
     * @param stdClass $submission The submission
     * @param stdClass $user The user record - unused
     * @return array - return an array of files indexed by filename
     */
    public function get_files(stdClass $submission, stdClass $user) {
        $result = array();
        $fs = get_file_storage();

        $files = $fs->get_area_files($this->assignment->get_context()->id,
                'assignsubmission_file',
                assignsubmission_file_FILEAREA,
                $submission->id,
                'timemodified',
                false);

        foreach ($files as $file) {
            // Do we return the full folder path or just the file name?
            if (isset($submission->exportfullpath) && $submission->exportfullpath == false) {
                $result[$file->get_filename()] = $file;
            } else {
                $result[$file->get_filepath() . $file->get_filename()] = $file;
            }
        }
        return $result;
    }

    /**
     * Get formatted submitted files information from the database
     *
     * @param int $submissionid
     * @return string
     */
    private function get_archived_files_info($submission) {
        global $DB, $USER;
        $submissionid = $submission->id;
        $this->assignsubmission_filero_validate_settings($submission->assignment);
        $files = $this->get_archived_files($submissionid);
        $filero = $this->get_filero_submission($submissionid);
        $grade = $DB->get_record('assign_grades',
                array('assignment' => $submission->assignment, "userid" => $submission->userid));
        $info = "-";
        if ($filero AND is_countable($files) AND count($files)) {
            $numfiles = count($files);
            $LogfilePath = assignsubmission_filero_filero::LogfilePath($submission->id);
            if (empty($LogfilePath) OR !is_readable($LogfilePath)){
                return "Fehler: Log Datei '$LogfilePath' für Abgabe id $submissionid ist nicht vorhanden, oder kann nicht gelesen werden!";
            }
            $info = "\n<script>\n
                function toggleViewFiles_".$submissionid."() { var obj = document.getElementById('FileroFiles_".$submissionid."');
                    obj.style.display = (obj.style.display === 'none') ? 'block' : 'none';}
                    </script>\n"
                    . '<span title="Klick zum Anzeigen der Daten zur Archivierung" onclick="toggleViewFiles_'
                    .$submissionid.'();">Daten'
                    . ($numfiles ? (" und " . $numfiles . " Datei" . ($numfiles > 1 ? "en" : "")) : "")
                    . '&nbsp;<i class="fa fa-angle-down" aria-hidden="true" style="font-weight:bolder;color:darkgreen;"></i>'
                    . "</span>\n";

            $info .= "\n" . '<div id="FileroFiles_'.$submissionid.'" style="display:none;border:2px solid darkgreen;margin:6px;">';
            // requiresubmissionstatement has been provided and logged
            // show last error msg if any
            if (!empty($filero->lasterrormsg) AND !$filero->filerovalidated AND (is_siteadmin() or !user_has_role_assignment($USER->id, 5))) {
                $info .= "Fehler #".$filero->filerocode." wurde mit der letzten Archivierung gemeldet: <b>" .$filero->lasterrormsg
                        ."</b><br><br>";
            }

            if (!empty($filero->statement_accepted)) {
                $statement_accepted = $this->show_statement_accepted($submission);
                $info .= $this->show_statement_accepted($submission) . "<br><br>";
            }
            $filearea = "none";
            $cnt = $feedbackfiles = 0;
            foreach ($files as $file) {
                if (empty($file->timecreated)) {
                    $file->timecreated = $filero->submissiontimecreated;
                }
                if (empty($file->timemodified)) {
                    $file->timemodified = $file->timecreated;
                }
                $fileromodified = $filero->feedbacktimemodified;
                if ($file->filearea == assignsubmission_file_FILEAREA) {
                    $fileromodified = $filero->submissiontimemodified;
                }
                if ($filearea != $file->filearea) {
                    $info .= (!$cnt ? "" : "<br>") . "<b>Rechtssicher archiviert am "
                            . date('d.m.Y \u\m H:i:s', $fileromodified) . "</b>";
                }
                $cnt++;
                $is_combined = ($file->filearea == 'combined');

                $is_submission = ($file->filearea == assignsubmission_file_FILEAREA or $is_combined);
                $uploaded_or_created = get_string("uploaded", 'assignsubmission_filero');
                $gfullname = "";
                $area_tag = get_string("filesubmission", 'assignsubmission_filero');

                if (!$is_submission and $grade and !empty($grade->grader)) {
                    $area_tag = get_string("feedback", 'assignsubmission_filero');
                    $grader = core_user::get_user($grade->grader);
                    if ($grader) {
                        $gfullname = " von " . $grader->firstname . " " . $grader->lastname;
                    }
                } else if ($is_combined) {
                    $uploaded_or_created = get_string("autocreated", 'assignsubmission_filero');
                }
                if ($file->filearea == 'download') {
                    $uploaded_or_created = get_string("annotated_submissions", 'assignsubmission_filero');
                }
                $size_tag = get_string("size");
                $info .= "<br><b>" . $area_tag . "</b>" . $gfullname . ": "
                        . $file->filename
                        . " - $uploaded_or_created: " . date('d.m.Y \u\m H:i:s', $file->timecreated)
                        . " - " . $size_tag . ": " . number_format($file->filesize, 0)
                        . " Bytes"
                        . "<br>ContentHash: " . $file->contenthashsha1 . "<br>";
                /*. "SHA512: "
                . substr($file->contenthashsha512,0,60). "<br>"
                . substr($file->contenthashsha512,59). "<br>";*/
                $filearea = $file->filearea;
                if ($filearea != $file->filearea) {
                    $feedbackfiles++;
                }
            }
            if (is_siteadmin() or !user_has_role_assignment($USER->id, 5)) {
                // show info regarding $_SESSION['filero_archive_feedback_after_grading']
                if ($grade = $DB->get_record('assign_grades',
                        array('assignment' => $submission->assignment, 'userid' => $submission->userid))) {
                    if (!isset($_SESSION['filero_archive_feedback_after_grading'])) {
                        $this->archive_feedback_after_grading($submission);
                    }
                    if (!$feedbackfiles AND $_SESSION['filero_archive_feedback_after_grading'] and !($grade->grade > 0)
                            AND !stristr($info, "Feedback</b>:")) {
                        $info .= '<br><b>Das Feedback von Bewertern wird erst nach Bewertung archiviert.</b>';
                    }
                }
            }
            $info .= "</div>";

        }
        return $info;
    }

    function show_statement_accepted($submission) {
        // global $DB;
        $filero = $this->get_filero_submission($submission->id);
        $statement_accepted = $filero->statement_accepted;
        $statement_tag = get_string('submission_statement', 'assignsubmission_filero');
        $statement_accepted = str_replace($statement_tag, "<b>" . $statement_tag . "</b>", $statement_accepted);
        return $statement_accepted;
    }


    /**
     * Display Filero archiving Infos
     *
     * @param stdClass $submission
     * @param bool $showviewlink Set this to true if the list of files is long
     * @return string
     * html Classes: fileuploadsubmission and fileuploadsubmissiontime
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        return $this->view($submission);
    }

    /**
     * No full submission view - the summary contains the list of files and that is the whole submission
     *
     * @param stdClass $submission
     * @return string
     */
    public function view(stdClass $submission) {
        global $DB, $USER;
        $filesubmission = $this->get_filero_submission($submission->id);
        $fileroRes = "-";
        if ($filesubmission AND assign_submission_filero::use_archiving($submission)) {
            $fileroRes = $fileroFiles = $this->get_archived_files_info($submission);
            if (isset($_REQUEST['action']) and $_REQUEST['action'] != "grader"
                    and (is_siteadmin() or !user_has_role_assignment($USER->id, 5))) {
                // archive manually now if button was pressen
                if (isset($_POST['assignsubmission_filero_archive'])
                        and $_POST['assignsubmission_filero_archive'] == $submission->id) {
                    $_SESSION['filero_submit_for_grading_' . $submission->id] = true;
                    assignsubmission_filero_observer::observer_log(
                            "Start manual archiving of all submission and feedback data and files for "
                            . "'userid'=>$submission->userid, 'assignment'=>$submission->assignment!");
                    $this->submit_for_grading($submission);
                    // disable archiving for non-grader feedeback
                    if ( !$this->is_student_assignment($submission)) {
                        assignsubmission_filero_observer::archive_feedback($submission);
                    }
                    unset($_SESSION['filero_submit_for_grading_' . $submission->id]);
                }

                $cm = context_module::instance($this->assignment->get_course_module()->id);
                $pluginfo = assign_submission_filero::get_plugin_version();
                $info = "\n\n".$this->get_name()." Plugin Version: ".$pluginfo->version." - Release: "
                        .$pluginfo->release;
                $fileroRes .= '<form method="POST" target="showLog" style="font-size:81%;display:inline;">
                            <input type="hidden" name="id" value="' . $cm->instanceid . '">
                            <input type="hidden" name="submissiontimemodified" value="'
                        . $filesubmission->submissiontimemodified . '">
                            <button name="assignsubmission_filero_showLog" value="' . $submission->id . '" 
                             title="Studierende sehen diesen Button nicht!' . $info . '">Log anzeigen</button>'
                        . "</form>\n";
                $currentsubmission = $DB->get_record('assign_submission',
                        array('assignment' => $submission->assignment,'userid' => $submission->userid));
                if ($currentsubmission && $currentsubmission->status == "submitted") {
                    $fileroRes .= '<form method="POST" style="font-size:81%;display:inline;">'
                    . '<button name="assignsubmission_filero_archive" value="' . $submission->id
                    . '" title="Studierende sehen diesen Button nicht!' . $info . '">'
                    . (strlen($fileroFiles) > 30 ? 'Erneut a' : 'A') . 'rchivieren</button>'
                    . "</form>\n";
                }
                // print "Submission:<br>" . var_export($submission,true);
            }
        }
        return $fileroRes;
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 assignment of this type
     * and version.
     *
     * @param string $type
     * @param int $version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {

        $uploadsingletype = 'uploadsingle';
        $uploadtype = 'upload';

        if (($type == $uploadsingletype || $type == $uploadtype) && $version >= 2021112900) {
            return true;
        }
        return false;
    }

    /**
     * Upgrade the submission from the old assignment to the new one
     *
     * @param context $oldcontext The context of the old assignment
     * @param stdClass $oldassignment The data record for the old oldassignment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $submission The data record for the new submission
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext,
            stdClass $oldassignment,
            stdClass $oldsubmission,
            stdClass $submission,
            &$log) {
        return true;
        /*
        global $DB;
        $filesubmission = new stdClass();

        $filesubmission->numfiles = $oldsubmission->numfiles;
        $filesubmission->submission = $submission->id;
        $filesubmission->assignment = $this->assignment->get_instance()->id;

        if (!$DB->insert_record('assignsubmission_filero', $filesubmission) > 0) {
            $log .= get_string('couldnotconvertsubmission', 'mod_assign', $submission->userid);
            return false;
        }
        return true;
        */
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * Note: Filero tables are currently persistant and function as evidence of successfull archiving.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        return true;
        /*
        if ($DB->get_records('assignsubmission_filero',
                array('assignment' => $this->assignment->get_instance()->id))
        ) {
            $DB->delete_records('assignsubmission_filero',
                    array('assignment' => $this->assignment->get_instance()->id));
        }
        if ($DB->get_records('assignsubmission_filero_file',
                array('assignment' => $this->assignment->get_instance()->id))
        ) {
            $DB->delete_records('assignsubmission_filero_file',
                    array('assignment' => $this->assignment->get_instance()->id));
        }
        return true;
        */
    }

    /**
     * Formatting for log info
     *
     * @param stdClass $submission The submission
     * @return string
     */
    public function format_for_log(stdClass $submission) {
        // Format the info for each submission plugin (will be added to log).
        $filecount = $this->count_files($submission->id, assignsubmission_file_FILEAREA);

        return get_string('numfilesforlog', 'assignsubmission_filero', $filecount);
    }

    /**
     * Count the number of files
     *
     * @param int $submissionid
     * @param string $area
     * @return int
     */
    private function count_files($submissionid, $area) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->assignment->get_context()->id,
                'assignsubmission_file',
                $area,
                $submissionid,
                'id',
                false);

        return count($files);
    }

    /**
     * Return true if there are no submission files
     *
     * @param stdClass $submission
     */
    public function is_empty(stdClass $submission) {
        return $this->count_files($submission->id, assignsubmission_file_FILEAREA) == 0;
    }

    /**
     * Determine if a submission is empty
     *
     * This is distinct from is_empty in that it is intended to be used to
     * determine if a submission made before saving is empty.
     *
     * @param stdClass $data The submission data
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        global $USER;
        $fs = get_file_storage();
        // Get a count of all the draft files, excluding any directories.
        $files = $fs->get_area_files(context_user::instance($USER->id)->id,
                'user',
                'draft',
                $data->files_filemanager,
                'id',
                false);
        return count($files) == 0;
    }


    /**
     * Get file areas returns a list of areas this plugin stores files
     *
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(assignsubmission_file_FILEAREA => get_string('file', 'assignsubmission_file'));
    }

    /**
     * Get the name of the file submission plugin
     *
     * @return string
     */
    public function get_name() {
        return get_string('filero', 'assignsubmission_filero');
    }

    /**
     * Return a description of external params suitable for uploading a file submission from a webservice.
     *
     * @return external_description|null
     */
    public function get_external_parameters() {
        return null;
        /*
        return array(
                'files_filemanager' => new external_value(
                        PARAM_INT,
                        'The id of a draft area containing files for this submission.',
                        VALUE_OPTIONAL
                )
        );
        */
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        global $CFG;

        $configs = $this->get_config();
        return (array) $configs;
    }

    /**
     * Get the type sets configured for this assignment.
     *
     * @return array('groupname', 'mime/type', ...)
     */
    /*
    private function get_configured_typesets() {
        $typeslist = (string)$this->get_config('filetypeslist');

        $util = new \core_form\filetypes_util();
        $sets = $util->normalize_file_types($typeslist);

        return $sets;
    }
    */
    /**
     * Determine if the plugin allows image file conversion
     *
     * @return bool
     */
    public function allow_image_conversion() {
        return true;
    }

    public function safeCount($count) {
        if (is_countable($count)) {
            return count($count);
        }
        if (is_numeric($count)) {
            return $count;
        }
        return 0;
    }

    public function get_assign_cm_from_id($assign)
    {	global $DB;
        $cm = $DB->get_record_sql("SELECT * FROM {course_modules} AS cm, {modules} AS m WHERE
						cm.instance = $assign->id and cm.module = m.id  and m.name='assign'");
        return ($cm);
    }

    static public function get_plugin_version($component = "assignsubmission_filero") {
        list($plugintype, $pluginname) = core_component::normalize_component($component);
        $pluginpath = core_component::get_plugin_directory($plugintype, $pluginname);
        $plugin = new \stdClass();
        require $pluginpath.'/version.php';
        //return $plugin->version;
        return $plugin;
    }

    /*
     * Function to control and show assign->requiresubmissionstatement (Eigenständigkeitserklärung)
    */
    function assignsubmission_filero_validate_settings($assignmentid) {
        global $DB;
        $update = false;
        // is_siteadmin() ||
        if ( !isset($_SESSION['filero_settings_validated_'.$assignmentid])) {
            $_SESSION['filero_settings_validated_'.$assignmentid] = true;
            $config = get_config('assignsubmission_filero');
            $requiresubmissionstatement = $config->requiresubmissionstatement;
            if ($assign = $DB->get_record("assign", array("id" => $assignmentid))) {
                if ($requiresubmissionstatement and !$assign->requiresubmissionstatement) {
                    $assign->requiresubmissionstatement = 1;
                    $update = true;
                }
                if (!$assign->submissiondrafts) {
                    $assign->submissiondrafts = 1;
                    $update = true;
                }
                if ($update) {
                    $DB->update_record('assign', $assign);
                    assignsubmission_filero_observer::observer_log(
                            "validate_settings: Updated assignment '$assign->name' with id $assign->id");
                }
                /*else {
                    assignsubmission_filero_observer::observer_log(
                            "validate_settings: No update required for assignment '$assign->name' with id $assign->id");
                }*/
            }
            else{
                assignsubmission_filero_observer::observer_log(
                        "validate_settings: No assignment with id $assignmentid found!");
            }
        }
        return $update;
    }

    function check_enrolment($courseid, $userid){
        $enrolled = false;
        if ( $context = context_course::instance($courseid)) {
            $enrolled = is_enrolled($context, $userid, '', true);
        }
        return $enrolled;
    }
}

