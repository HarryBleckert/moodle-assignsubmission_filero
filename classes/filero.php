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

defined('MOODLE_INTERNAL') || die();

// define Filero Log
if (!defined('assignsubmission_filero_LOG_FOLDER')) {
    global $CFG;
    $logdir = $CFG->dataroot . "/filero";
    if (!is_dir($logdir)) {
        mkdir($logdir);
    }
    define('assignsubmission_filero_LOG_FOLDER', $logdir);
    /*if ( is_file(assignsubmission_filero_LOG_FOLDER) AND filesize(assignsubmission_filero_LOG_FOLDER) > 12000000) {
        if ( is_file(assignsubmission_filero_LOG_FOLDER.".bak") ){
            system("/usr/bin/gzip " . assignsubmission_filero_LOG_FOLDER.".bak");
        }
        rename(assignsubmission_filero_LOG_FOLDER,assignsubmission_filero_LOG_FOLDER.".bak" );
    }*/
}

/**
 * library class for filero submission plugin
 * This class provides all the functionality for the new filero module.
 */
class assignsubmission_filero_filero {
    protected $submission;
    protected $cm;
    protected $files;
    protected $filecount;
    protected $courseid;
    protected $userid;
    protected $filearea;

    /**
     * Constructor
     *
     * @param stdClass $submission submission object, in case of the template
     *     this is the current submission the template is accessed from
     * @param stdClass|cm_info $cm course module object corresponding to the $submission
     *     (at least one of $submission or $cm is required)
     */
    public function __construct($submission = false, $files = false, $filearea = "submission_files") {
        global $DB; // USER, $COURSE;
        $this->submission = $submission;
        if ($submission) {
            $this->grade = $DB->get_record('assign_grades',
                    array('assignment' => $submission->assignment, "userid" => $submission->userid));
        }
        // $this->cm = $cm;
        $this->files = $files;
        $this->filearea = $filearea;
        // $this->submissiondata = $submissiondata;
        $this->output = "";
        $this->status = new stdClass();
        $this->status->msg = "";
        $this->status->code = 0;
        $this->status->id = 0;
        $this->status->className = "";
        $this->starttime = time();
        $this->client = new stdClass();
        $this->fileroDateFormat = "Y-M-d H:i:s";
        $this->utcOffset = date("Z");
        $this->debug = (isset($_SESSION["debugfilero"]) and $_SESSION["debugfilero"]);

    }

    /*
    * assign_submission: id, assignment, userid, timecreated, timemodified, status, groupid, attemptnumber, latest
    * assign_grades: assignment, id, userid, timecreated, timemodified, grader, grade, attemptnumber
    *
    * assign_submission_filero: id,assignment,submission,numfiles,filerostatus,fileroid,filerotimecreated,filerotimemodified
    *
    */
    public function showAssignment() {
        global $DB;
        $this->output = "";
        $assign = $DB->get_record("assign", array("id" => $this->submission->assignment));
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $context = context_module::instance($cm->id);
        // echo "The context is: $context->id";
        $query = "SELECT * FROM {files} WHERE contextid = " . $context->id . " AND itemid = " . $this->submission->id .
                " AND CHAR_LENGTH(filename)>2"
                . " AND userid = " . $this->submission->userid .
                " AND !STRCMP(" . $this->filearea . ",filearea) ORDER by id ASC";
        // echo "<br>The query is: $query<br>";
        $fileRecs = $DB->get_record_sql($query);
        $hostname = gethostname();
        $ip = $_SERVER['SERVER_ADDR']; // gethostbyname($hostname);
        $this->output .= "\n<hr>Filero Submission on Server $hostname - IP: $ip\n"
                . "\nAssignment:\n "
                . var_export($assign, true)
                . "\nCourse module (\$cm):\n "
                . var_export($cm, true)
                . "\n\$Context:\n "
                . var_export($context, true)
                //. "\nFile query:\n " . $query
                //. "\n" . ($files ?count($files) :0) . " Filero file(s) stored at : " . $this->filero_get_filepath($fileRecs) . "\n"
                . "\n\$Files:\n "
                . var_export($this->files, true)
                . "\n\$fileRecs:\n "
                . var_export($fileRecs, true)
                . "\n\$submission:\n "
                . var_export($this->submission, true)
            //. "\n\n<hr>Filero Files:\n"
            //. print_r ($this->files, true)
            //. "\n\n<hr>Filero \$Data:\n"
            //. var_export($this->submissiondata, true)
        ;
        print nl2br(preg_replace("/\n(.*?):/", "\n<b>$1</b>,", $this->output));

    }

    /**
     * Current submission
     *
     * @return stdClass
     */
    public function get_submission() {
        global $DB;
        if (!isset($this->submission->id)) {
            // Make sure the full object is retrieved.
            $this->submission = $DB->get_record('submission', ['id' => $this->submission->id], '*', MUST_EXIST);
        }
        return $this->submission;
    }

    /**
     * Current course module
     *
     * @return stdClass
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Archive Submission including submitted files with Filero
     *
     * @return stdClass with Filero Response objects on success
     *
     */
    private function LoginToFilero() {

        $config = get_config('assignsubmission_filero');
        $location = $config->location; // full WSDL API URL
        $username = $config->username;
        $password = $config->password;
        $productKey = $config->productkey;
        // $this->output .= "\nConfig: ".print_r($config,true);
        // $location = "https://10.20.58.10/csp/fileroapi//FLC.PKG.Moodle.Soap.Api.cls";
        // $wsdl = "http://10.20.58.10/csp/fileroapi//FLC.PKG.Moodle.Soap.Api.cls?wsdl=1";
        $wsdl = $location . "?wsdl=1";

        $arrContextOptions = array("ssl" => array("verify_peer" => false, "verify_peer_name" => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT));
        $options = array(
                'wsdl' => true,
                'encoding' => 'UTF8',
                'location' => $location,
                'soap_version' => SOAP_1_2,
                'exceptions' => true,
                'user_agent' => "Moodle_AssignSubmissionClient",
                'trace' => 1,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => stream_context_create($arrContextOptions)
        );

        $ticketTimeOut = 3600 * 12; // 12 hours expiry
        $secondFactor = 0;

        $Login = new stdClass();
        $Login->username = $username;
        $Login->password = $password;
        $Login->ticketTimeOut = $ticketTimeOut; // 48200,
        $Login->secondFactor = $secondFactor;
        $Login->productKey = $productKey;
        $Login->status = $this->status;
        // $this->output .= "<br>\n" ."Login Credentials: " .var_export($Login,true)."<br>\n";

        // new SoapClient
        $this->client = new SoapClient($wsdl, $options);
        if ($this->debug) {
            $this->output .= "\nSoapClient $wsdl:\n" . var_export($this->client, true) . "\n";
        }

        if (isset($_SESSION['ticketTimeOut']) and $_SESSION['ticketTimeOut'] < time()) {
            unset($_SESSION['ticket'], $_SESSION['ticketTimeOut']);
        }

        try {
            if (!isset($_SESSION['ticket']) or empty($_SESSION['ticket'])) {   // login working as shown below
                // $response_param = $this->client->__soapCall('Login', array($Login));
                // $this->output .=  "\nLogin:\n" . var_export($Login, true) . "\n\n";
                $response_param = $this->client->Login($Login);
                $this->output .= "\nLogin Response:\n" . var_export($response_param, true) . "\n\n";
                $LoginResult = $response_param->LoginResult;
                if (isset($LoginResult->ticket)) {
                    $_SESSION['ticket'] = $LoginResult->ticket;
                    $_SESSION['ticketTimeOut'] = time() + $ticketTimeOut;
                }

            }
            if (isset($_SESSION['ticket']) and !empty($_SESSION['ticket'])) {
                $ticket = $_SESSION['ticket'];
                $this->output .= "\nLogin Ticket: $ticket\nExpiry: "
                        . date($this->fileroDateFormat, $_SESSION['ticketTimeOut']) . "\n\n";
                return true;
            } else {
                $this->output .= "\nLogin Error: " . var_export($Login, true) . "\n\n";
            }
        } catch (Exception $e) {
            $this->output .= "\n<h2><b>Exception Error</b></h2>";
            $this->output .= $e->getMessage() . "\n\n";
            $this->SoapDebug($this->client);
        }
        assignsubmission_filero_observer::observer_log("Filero Login FAILED!");
        $this->output .= "\nFailed: Filero Login!\n";
        $this->output .= "\nTotal processing time: " . (time() - $this->starttime) . " seconds\n"
                . "Submission ID: " . trim($this->submission->id) . "\n";
        $this->filero_log($this->submission->id);
        return false;
    }

    /**
     * Archive Submission including submitted files with Filero
     *
     * @return stdClass with Filero Response objects on success
     *
     */
    public function PutMoodleAssignmentSubmission() {
        global $DB;
        $starttime = time();
        $filerotimecreated = $filerotimemodified = $filerocode = $fileroid = 0;
        $fileromsg = "";
        $validated_files = array();
        // $this->showSpinner();  // showing only after page load!
        $this->output = "\n\nSubmission ID: " . trim($this->submission->id) . "\n";
        $this->output .= "Date: " . date("D, d.m.Y H:i:s e")
                . " (UTC offset: " . $this->utcOffset . "s). Memory used: "
                . (round(memory_get_peak_usage(true) / 1024 / 1024)) . "M of " . ini_get('memory_limit') . "\n"
                . "Submission from: User id " . $this->submission->userid
                . " stored in assign_submission with id:" . $this->submission->id . "\n";

        if (!$this->LoginToFilero()) {
            return $this->status;
        }

        set_time_limit(1800);
        ini_set("memory_limit", "1500M");
        $assign = $DB->get_record("assign", array("id" => $this->submission->assignment));
        $grade = $DB->get_record('assign_grades',
                array('assignment' => $this->submission->assignment, "userid" => $this->submission->userid));
        // id	assignment submission onlinetext onlineformat
        $assignsubmission_onlinetext = $DB->get_record('assignsubmission_onlinetext',
                array('submission' => $this->submission->id, "assignment" => $assign->id));
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $context = context_module::instance($cm->id);
        $course = $DB->get_record("course", array("id" => $assign->course));
        $user = $DB->get_record("user", array("id" => $this->submission->userid));

        $query = "SELECT * FROM {files} WHERE contextid = " . $context->id . " AND itemid = " . $this->submission->id
                . " AND CHAR_LENGTH(filename)>2"
                . " AND userid = " . $this->submission->userid
                . " AND !STRCMP('" . $this->filearea . "',filearea) ORDER by id ASC";

        list($Files, $submittedFiles, $totalfilesize) = $this->create_files_obj($query, $this->filearea);
        $AssignSubmissionFile = new stdClass();
        $AssignFiles = new stdClass();
        if (!empty($submittedFiles)) {
            $AssignSubmissionFile->AssignSubFileId = $AssignSubmissionFile->AssignId = $assign->id;
            $AssignSubmissionFile->Numfiles =
                    (is_countable($submittedFiles) ? count($submittedFiles) : $this->submission->numfiles);
            $AssignSubmissionFile->Files = $Files;
            //unset($Files);
            /*if (isset($Files->File[0])) {
                foreach ($Files->File as $key => $file) {
                    if ( isset($Files->File[$key]->Source)) {
                        $Files->File[$key]->Source = "<base64_encoded>";
                    }
                    else{
                        $this->output .= "Error in $Files->File[$key]->filename\n";
                    }
                }
            }*/
            $AssignFiles->AssignSubmissionFile = $AssignSubmissionFile;
            unset($AssignSubmissionFile);
        }

        $SubmissionOnlinetext = new stdClass();
        if (!empty($assignsubmission_onlinetext)) {
            // $assignsubmission_onlinetext
            // id	assignment submission onlinetext onlineformat
            $SubmissionOnlinetext->AssignSubmissionOnlinetextId = $assignsubmission_onlinetext->id;
            $SubmissionOnlinetext->onlinetext = $assignsubmission_onlinetext->onlinetext;
            $SubmissionOnlinetext->SubmissionId = $assignsubmission_onlinetext->submission;
            $SubmissionOnlinetext->Assignment = $assignsubmission_onlinetext->assignment;
            $SubmissionOnlinetext->OnlineFormat = $assignsubmission_onlinetext->onlineformat;
        }

        $Assign = new stdClass();
        $Assign->AssignId = $assign->id;
        $Assign->Course = $assign->course;
        $Assign->CourseIDNumber = $course->idnumber;
        $Assign->Intro = $assign->intro;
        $Assign->Name = $assign->name;

        $AssignSubmission = new stdClass();
        $AssignSubmission->Assign = $Assign;
        $AssignSubmission->AssignSubmissionId = $this->submission->id;
        $AssignSubmission->AssignSubmissionUserId = $this->submission->userid;
        $AssignSubmission->UserId = $this->submission->userid;
        $AssignSubmission->IdNumber = $user->idnumber;
        // Live status is still 'draft' when processing submit_for_grading()
        // $AssignSubmission->Status = $this->submission->status;
        $AssignSubmission->Status = "submitted";
        $AssignSubmission->StatementAccepted = $this->show_statement_accepted($this->submission);
        $AssignSubmission->TimeModified = $this->submission->timemodified;
        $AssignSubmission->TimeCreated = $this->submission->timecreated;
        $AssignSubmission->TimeStarted = $this->submission->timestarted;
        $AssignSubmission->Groupid = $this->submission->groupid;
        $AssignSubmission->AttemptNumber = $this->submission->attemptnumber;
        $AssignSubmission->Latest = $this->submission->latest;
        $AssignSubmission->AssignFiles = $AssignFiles;
        $AssignSubmission->SubmissionOnlinetext = $SubmissionOnlinetext;
        unset($AssignFiles);

        $this->output .= "Total file size: " . number_format($totalfilesize / 1024, 0)
                . "KB - Memory used after processing files: " . round(memory_get_peak_usage(true) / 1024 / 1024) . "M\n";

        $AssignSubmissionWTicket = new stdClass();
        $AssignSubmissionWTicket->ticket = $_SESSION['ticket'];
        $AssignSubmissionWTicket->submission = $AssignSubmission;
        $AssignSubmissionWTicket->status = $this->status;
        unset($AssignSubmission);

        $this->output .= "\n\n" . "AssignSubmission with ticket data: (Memory used: " .
                round(memory_get_peak_usage(true) / 1024 / 1024) . "M)\n"
                . $this->hideFileContent(var_export($AssignSubmissionWTicket, true)) . "\n";

        try {
            if (strstr(implode("\n", $this->client->__getFunctions()), "PutMoodleAssignmentSubmission")) {
                $response_param = $this->client->PutMoodleAssignmentSubmission($AssignSubmissionWTicket);
                unset($AssignSubmissionWTicket);

                $this->output .= "\nPutMoodleAssignmentSubmission Response: " .
                        $this->hideFileContent(var_export($response_param, true)) .
                        "\n\n";
                $SubmissionResult = $response_param->PutMoodleAssignmentSubmissionResult;
                $filerocode = $SubmissionResult->code;
                $fileromsg = $SubmissionResult->msg;
                $fileroid = $SubmissionResult->id;
                $filerovalidated = 0;
                if (isset($SubmissionResult->TimeCreated) and $SubmissionResult->TimeCreated > 0
                        and isset($SubmissionResult->TimeModified) and $SubmissionResult->TimeModified > 0
                ) {
                    $filerotimemodified = $SubmissionResult->TimeModified;
                    $filerotimecreated = $SubmissionResult->TimeCreated;
                    $filerovalidated = 1;
                } else {
                    $filerotimecreated = $filerotimemodified = $filerovalidated = 0;
                }
                if (isset($SubmissionResult->Files->FileInfo)) {
                    $cnt = 0;
                    if (!is_array($SubmissionResult->Files->FileInfo)) {
                        $tmp = $SubmissionResult->Files->FileInfo;
                        $SubmissionResult->Files->FileInfo = array();
                        $SubmissionResult->Files->FileInfo[0] = $tmp;
                    }
                    foreach ($SubmissionResult->Files->FileInfo as $rFile) {
                        $ContentHashSHA512 = $rFile->ContentHashSHA512;
                        $filename = $rFile->FileName;
                        if (!empty($filename) and
                                strtolower($ContentHashSHA512) == strtolower($Files->File[$cnt]->ContentHashSha512)) {
                            $this->output .= "Validation passed: Content hashes of sent and archived file '$filename' are identical!\n";
                            $filerovalidated = 1;
                            $validated_files[] = array(
                                    "filename" => $filename,
                                    "filesize" => $Files->File[$cnt]->Filesize,
                                    "filesid" => $Files->File[$cnt]->FileId,
                                    "filearea" => $Files->File[$cnt]->FileArea,
                                    "contenthashsha1" => $Files->File[$cnt]->ContentHash,
                                    "contenthashsha512" => $ContentHashSHA512,
                                    "timecreated" => $Files->File[$cnt]->TimeCreated,
                                    "timemodified" => $Files->File[$cnt]->TimeModified,

                            );
                        } else {
                            $this->output .= "\nError: SHA512 Content hashes sent and retrieved archived file '$filename' are NOT identical!\n"
                                    . "Archived: " . $Files->File[$cnt]->ContentHashSha512 . " - Retrieved: $ContentHashSHA512\n";
                            $filerovalidated = 0;
                        }
                        $cnt++;
                    }
                } else {
                    $filerovalidated = 1;
                    $this->output .= "\n<h2><b>No files submitted!</b></h2>\n\n";
                }
            } else {
                $this->output .= "\n<h2><b>Filero API Server doesn't know a function PutMoodleAssignmentSubmission. Aborted!</b></h2>\n\n";
            }
            if ($this->debug and isset($filerocode) and $filerocode == 1 and !empty($fileroid)) {
                $AssignSubmissionWTicket = new stdClass();
                $AssignSubmissionWTicket->ticket = $ticket;
                $AssignSubmissionWTicket->submissionId = $response_param->PutMoodleAssignmentSubmissionResult->id;
                $AssignSubmissionWTicket->status = $this->status;
                $response_param = $this->client->GetMoodleAssignmentSubmission($AssignSubmissionWTicket);
                $this->output .= "\nGetMoodleAssignmentSubmission Response on Submission ID $fileroid: "
                        . $this->hideFileContent(var_export($response_param, true)) . "\n\n";
            }

            unset($Files, $File);
            if ($this->debug) {
                $this->SoapDebug($this->client);
            }

        } catch (Exception $e) {
            $fileromsg = "Exception Error: ";
            $fileromsg .= $e->getMessage();
            $filerovalidated = $filerocode = 0;
            $this->output .= $fileromsg . "\n\n";
            // unset($AssignSubmissionFile->Files, $Files, $File);
            // $this->output .= $this->SoapDebug($this->client);
        }

        unset($Files);
        $filerosubmission = new stdClass();
        if (isset($filerocode)) {
            $filerosubmission->filerocode = $filerocode;
            $filerosubmission->fileromsg = $fileromsg;
            $filerosubmission->fileroid = $fileroid;
            $filerosubmission->filerotimecreated = $filerotimecreated;
            $filerosubmission->filerotimemodified = $filerotimemodified;
            $filerosubmission->filerovalidated = $filerovalidated;
            $filerosubmission->validated_files = $validated_files;
        }

        $this->output .= "\nFilero submission created on "
                . date("D, d.m.Y H:i:s e", $filerotimecreated)
                . " and modified on " . date("D, d.m.Y H:i:s e", $filerotimemodified)
                . ":\n"
                . var_export($filerosubmission, true)
                . "\n\nTotal processing time: " . (time() - $starttime) . " seconds\n"
                . "Submission ID: " . trim($this->submission->id) . "\n";
        // echo "<script>ev_spinner_disable();</script>";
        $this->filero_log($this->submission->id);
        return $filerosubmission;
    }

    /*
     * Delete Filero Submission by Submission ID
     */
    public function DeleteMoodleAssignmentSubmission() {
        $starttime = time();
        $this->output = "\n\nSubmission ID: " . trim($this->submission->id) . "\n";
        $this->output .= "Date: " . date("D, d.m.Y H:i:s e")
                . " (UTC offset: " . $this->utcOffset . "s)\n"
                . "Deleting FILERO archive of submission from: User id " . $this->submission->userid
                . " stored in assign_submission with id:" . $this->submission->id . "\n";

        if (!$this->LoginToFilero()) {

            return $this->status;
        }
        $AssignSubmissionWTicket = new stdClass();
        $AssignSubmissionWTicket->ticket = $_SESSION['ticket'];
        $AssignSubmissionWTicket->submissionId = $this->submission->id;
        $AssignSubmissionWTicket->status = $this->status;

        $response_param = $this->client->DeleteMoodleAssignmentSubmission($AssignSubmissionWTicket);
        /*$this->output .= "\nDeleteMoodleAssignmentSubmission Response: " .
                $this->hideFileContent(var_export($response_param, true)) .
                "\n\n";
        */
        $SubmissionResult = $response_param->DeleteMoodleAssignmentSubmissionResult;
        $deletionresult = "Fehler beim Löschen des FILERO Archivs der lezten Abgabe mit submisssion ID ".$this->submission->id;
        if ( isset($SubmissionResult->msg)){
                if ( stristr($SubmissionResult->msg, " null") ) {
                    $deletionresult = "Das FILERO Archiv der letzten Abgabe mit submisssion ID "
                            . $this->submission->id . " wurde erfolgreich gelöscht.";
                }
                else{
                    $deletionresult .= "\nFILERO Fehlermeldung bei Archivierung der der lezten Abgabe mit Submisssion ID "
                            .$this->submission->id . ": ". $SubmissionResult->msg;
                }
        }

        $this->output .= "\n$deletionresult. Datum: "
                . date("D, d.m.Y H:i:s e")
                . ":\n"
                . "\nTotal processing time: " . (time() - $starttime) . " seconds\n"
                . "Submission ID: " . trim($this->submission->id) . "\n";
        $this->filero_log($this->submission->id);
    }

    /**
     * Archive Submission including submitted files with Filero
     *
     * @return stdClass with Filero Response objects on success
     *
     */
    public function PutMoodleAssignmentGrade() {
        global $DB;
        $starttime = time();
        $filerotimecreated = $filerotimemodified = 0;
        $validated_files = array();
        $assign = $DB->get_record("assign", array("id" => $this->submission->assignment));
        $grade = $this->grade;
        $assignfeedback_file = $DB->get_record('assignfeedback_file',
                array('grade' => $grade->id, "assignment" => $assign->id));
        // id assignment grade commenttext commentformat
        $assignfeedback_comments = $DB->get_record('assignfeedback_comments',
                array('grade' => $grade->id, "assignment" => $assign->id));

        // $this->showSpinner();  // showing only after page load!
        $this->output = "\n\nSubmission ID: " . trim($this->submission->id) . "\n";
        $this->output .= "Date: " . date("D, d.m.Y H:i:s e")
                . " (UTC offset: " . $this->utcOffset . "s). Memory used: "
                . (round(memory_get_peak_usage(true) / 1024 / 1024)) . "M of " . ini_get('memory_limit') . "\n"
                . "Feedback and grading from: Grader id " . $grade->grader . " stored in grade with id:" . $grade->id . "\n";

        if (!$this->LoginToFilero()) {
            return $this->status;
        }

        set_time_limit(1800);
        ini_set("memory_limit", "1500M");
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course);
        $context = context_module::instance($cm->id);
        $course = $DB->get_record("course", array("id" => $assign->course));
        $user = $DB->get_record("user", array("id" => $this->submission->userid));

        $query = "SELECT * FROM {files} WHERE contextid = " . $context->id
                . " AND itemid = " . $grade->id
                . " AND CHAR_LENGTH(filename)>2 AND filesize>0"
                . " AND filearea IN('" . $this->filearea . "','combined')"
                . " AND component like 'assignfeedback_%' ORDER by id ASC";
        $AssignFiles = new stdClass();
        list($Files, $submittedFiles, $totalfilesize) = $this->create_files_obj($query, $this->filearea);
        if (!empty($submittedFiles)) {
            $AssignFeedbackFile = new stdClass();
            $AssignFeedbackFile->AssignFeedbackFileId = (isset($assignfeedback_file->id) ?$assignfeedback_file->id :0);
            $AssignFeedbackFile->AssignId = $assign->id;
            $AssignFeedbackFile->AssignGradeId = $grade->id;
            $AssignFeedbackFile->Numfiles = (is_countable($submittedFiles) ? count($submittedFiles) : $this->submission->numfiles);
            $AssignFeedbackFile->Files = $Files;
            /*if (isset($Files->File[0])) {
                foreach ($Files->File as $key => $file) {
                    if ( isset($Files->File[$key]->Source)) {
                        $Files->File[$key]->Source = "<base64_encoded>";
                    }
                    else{
                        $this->output .= "Error in $Files->File[$key]->filename\n";
                    }
                }
            }*/
            $AssignFiles->AssignFeedbackFile = $AssignFeedbackFile;
            unset($AssignFeedbackFile);
        }
        $FeedbackComments = new stdClass();
        if (!empty($assignfeedback_comments)) {
            $FeedbackComments->AssignFeedbackCommentId = $assignfeedback_comments->id;
            $FeedbackComments->commenttext = $assignfeedback_comments->commenttext;
            $FeedbackComments->AssignGradeId = $assignfeedback_comments->grade;
            $FeedbackComments->Assignment = $assignfeedback_comments->assignment;
            $FeedbackComments->CommentFormat = $assignfeedback_comments->commentformat;
        }

        $Assign = new stdClass();
        $Assign->AssignId = $assign->id;
        $Assign->Course = $assign->course;
        $Assign->CourseIDNumber = $course->idnumber;
        $Assign->Intro = $assign->intro;
        $Assign->Name = $assign->name;

        $AssignGrades = new stdClass();
        $AssignGrades->AssignGradeId = $grade->id;
        $AssignGrades->Assign = $Assign;
        $AssignGrades->GradeId = $grade->id;
        $AssignGrades->AssignGradeUserId = $grade->userid;
        $AssignGrades->StatementAccepted = $this->show_statement_accepted($this->submission);
        // $AssignGrades->SubmissionId = $this->submission->id;
        $AssignGrades->Grader = $grade->grader;
        $AssignGrades->Grade = $grade->grade;
        $AssignGrades->TimeModified = $grade->timemodified;
        $AssignGrades->TimeCreated = $grade->timecreated;
        $AssignGrades->AttemptNumber = $grade->attemptnumber;
        $AssignGrades->FeedbackFiles = $AssignFiles;
        $AssignGrades->FeedbackComments = $FeedbackComments;
        unset($AssignFiles);

        $this->output .= "Total file size: " . number_format(($totalfilesize / 1024), 0)
                . "KB - Memory used after processing files: " . round(memory_get_peak_usage(true) / 1024 / 1024) . "M\n";

        $AssignGradesWTicket = new stdClass();
        $AssignGradesWTicket->ticket = $_SESSION['ticket'];
        $AssignGradesWTicket->grade = $AssignGrades;
        $AssignGradesWTicket->status = $this->status;
        unset($AssignGrades);
        $this->output .= "\n\n" . "AssignGrades with ticket data: (Memory used: " .
                round(memory_get_peak_usage(true) / 1024 / 1024) . "M)\n"
                . $this->hideFileContent(var_export($AssignGradesWTicket, true)) . "\n";

        try {
            if (strstr(implode("\n", $this->client->__getFunctions()), "PutMoodleAssignmentGrade")) {
                $response_param = $this->client->PutMoodleAssignmentGrade($AssignGradesWTicket);
                unset($AssignGradesWTicket);

                $this->output .= "\nPutMoodleAssignmentGrade Response: " .
                        $this->hideFileContent(var_export($response_param, true)) .
                        "\n\n";
                $GradeResult = $response_param->PutMoodleAssignmentGradeResult;
                $filerocode = $GradeResult->code;
                $fileroid = $GradeResult->id;
                $filerovalidated = 0;
                if (isset($GradeResult->TimeCreated) and $GradeResult->TimeCreated > 0
                        and isset($GradeResult->TimeModified) and $GradeResult->TimeModified > 0
                ) {
                    $filerotimemodified = $GradeResult->TimeModified;
                    $filerotimecreated = $GradeResult->TimeCreated;
                } else {
                    $filerotimecreated = 0;
                    $filerotimemodified = 0;
                }
                if (isset($GradeResult->Files->FileInfo)) {
                    $cnt = 0;
                    if (!is_array($GradeResult->Files->FileInfo)) {
                        $tmp = $GradeResult->Files->FileInfo;
                        $GradeResult->Files->FileInfo = array();
                        $GradeResult->Files->FileInfo[0] = $tmp;
                    }
                    foreach ($GradeResult->Files->FileInfo as $rFile) {
                        $ContentHashSHA512 = $rFile->ContentHashSHA512;
                        $filename = $rFile->FileName;
                        if (!empty($filename) and
                                strtolower($ContentHashSHA512) == strtolower($Files->File[$cnt]->ContentHashSha512)
                        ) {
                            $this->output .= "Validation passed: Content hashes of sent and archived file '$filename' are identical!\n";
                            $filerovalidated = 1;
                            $validated_files[] = array(
                                    "filename" => $filename,
                                    "filesize" => $Files->File[$cnt]->Filesize,
                                    "filesid" => $Files->File[$cnt]->FileId,
                                    "filearea" => $Files->File[$cnt]->FileArea,
                                    "contenthashsha1" => $Files->File[$cnt]->ContentHash,
                                    "contenthashsha512" => $ContentHashSHA512,
                                    "timecreated" => $Files->File[$cnt]->TimeCreated,
                                    "timemodified" => $Files->File[$cnt]->TimeModified,

                            );
                        } else {
                            $this->output .= "\nError: SHA512 Content hashes sent and retrieved archived file '$filename' are NOT identical!\n"
                                    . "Archived: " . $Files->File[$cnt]->ContentHashSha512 . " - Retrieved: $ContentHashSHA512\n";
                            $filerovalidated = 0;
                        }
                        $cnt++;
                    }
                } else {
                    $filerovalidated = 1;
                    $this->output .= "\n<h2><b>No feedback files!</b></h2>\n\n";
                }
            } else {
                $this->output .= "\n<h2><b>Filero API Server doesn't know a function PutMoodleAssignmentGrade. Aborted!</b></h2>\n\n";
            }

            if ($this->debug and isset($filerocode) and $filerocode == 1 and !empty($fileroid)) {
                $AssignGradesWTicket = new stdClass();
                $AssignGradesWTicket->ticket = $ticket;
                $AssignGradesWTicket->submissionId = $response_param->PutMoodleAssignmentGradeResult->id;
                $AssignGradesWTicket->status = $this->status;
                $response_param = $this->client->GetMoodleAssignmentGrade($AssignGradesWTicket);
                $this->output .= "\nGetMoodleAssignmentGrade Response on Submission ID $fileroid: "
                        . $this->hideFileContent(var_export($response_param, true)) . "\n\n";
            }

            unset($Files, $File);
            if ($this->debug) {
                $this->SoapDebug($this->client);
            }

        } catch (Exception $e) {
            $this->output .= "\n<h2><b>Exception Error</b></h2>";
            $this->output .= $e->getMessage() . "\n\n";
            // unset($AssignFeedbackFile->Files, $Files, $File);
            $this->output .= $this->SoapDebug($this->client);
        }

        unset($AssignFeedbackFile->Files, $Files);
        $filerograding = new stdClass();
        if (isset($filerocode)) {
            $filerograding->filerocode = $filerocode;
            $filerograding->fileroid = $fileroid;
            $filerograding->filerotimecreated = $filerotimecreated;
            $filerograding->filerotimemodified = $filerotimemodified;
            $filerograding->filerovalidated = $filerovalidated;
            $filerograding->validated_files = $validated_files;
        }

        $this->output .= "\nFilero grading created on "
                . date("D, d.m.Y H:i:s e", $filerotimecreated)
                . " and modified on " . date("D, d.m.Y H:i:s e", $filerotimemodified)
                . ":\n"
                . var_export($filerograding, true)
                . "\n\nTotal processing time: " . (time() - $starttime) . " seconds\n"
                . "Submission ID: " . trim($this->submission->id) . "\n";
        // echo "<script>ev_spinner_disable();</script>";
        $this->filero_log($this->submission->id);
        return $filerograding;
    }

    /*
     * Delete Filero Submission by Submission ID
     */
    public function DeleteMoodleAssignmentGrade() {
        $starttime = time();
        $this->output = "\n\nSubmission ID: " . trim($this->submission->id) . "\n";
        $this->output .= "Date: " . date("D, d.m.Y H:i:s e")
                . " (UTC offset: " . $this->utcOffset . "s)\n";

        if (!$this->LoginToFilero()) {
            return $this->status;
        }

        $AssignGradesWTicket = new stdClass();
        $AssignGradesWTicket->ticket = $_SESSION['ticket'];
        $AssignGradesWTicket->submissionId = $this->submission->id;
        $AssignGradesWTicket->status = $this->status;

        $response_param = $this->client->PutMoodleAssignmentGradeResult($AssignGradesWTicket);
        $this->output .= "\nDeleteMoodleAssignmentSubmission Response: " .
                $this->hideFileContent(var_export($response_param, true)) .
                "\n\n";
        $GradeResult = $response_param->DeleteMoodleAssignmentGradeResult;
        if (isset($GradeResult->TimeModified) and $GradeResult->TimeModified > 0) {
            $filerotimemodified = $GradeResult->TimeModified;
        } else {
            $filerotimemodified = 0;
        }
        $this->output .= "\nFilero Feedback files and data deleted on "
                . date("D, d.m.Y H:i:s e", $filerotimemodified)
                . ":\n"
                . "\nTotal processing time: " . (time() - $starttime) . " seconds\n"
                . "Submission ID: " . trim($this->submission->id) . "\n";
        $this->filero_log($this->submission->id);
    }

    /*
     * create files object
     * @RETURN: array( $Files, $$submittedFiles)
     *
     */
    function create_files_obj($query, $filearea) {
        global $DB;
        $fileRecs = $DB->get_records_sql($query);
        $Files = new stdClass();
        $submittedFiles = array();
        $totalfilesize = 0;
        // assignsubmission_filero_observer::observer_log("Query for files:\n'$query'");
        /*if (empty($fileRecs)) {
            assignsubmission_filero_observer::observer_log("No file record with filearea '$filearea' and query\n'$query'");
        }*/
        foreach ($fileRecs as $fileRec) {
            $submittedFiles[] = $filepath = $this->filero_get_filepath($fileRec, $filearea);
            $Source = "";
            if (!empty($filepath) and is_readable($filepath)) {
                $Source = file_get_contents($filepath);
            } else {
                $msg = "File '$fileRec->filename' can't be retrieved from '$filepath'";
                $this->output .= "\n\n" . $msg;
                assignsubmission_filero_observer::observer_log($msg);
            }
            $fileSpecs = new stdClass();
            $fileSpecs->Author = $fileRec->author;
            $fileSpecs->License = $fileRec->license;
            $fileSpecs->UserId = $fileRec->userid;
            $fileSpecs->ContextId = $fileRec->contextid;
            $fileSpecs->Component = $fileRec->component;
            $fileSpecs->Status = $fileRec->status;
            $fileSpecs->ContentHashSha512 = hash("sha512", $Source);
            $fileSpecs->ContentHash = $fileRec->contenthash;
            $fileSpecs->PathnameHash = $fileRec->pathnamehash;
            $fileSpecs->FileArea = $fileRec->filearea;
            $fileSpecs->FileId = $fileRec->id;
            $fileSpecs->ReferenceFileId = $fileRec->referencefileid;
            $fileSpecs->Filesize = $fileRec->filesize;
            $fileSpecs->Filename = $fileRec->filename;
            // rename commented submission file combined.pdf
            // by default Moodle builds download file names from Scheme:
            // <filename of first submitted file>_user_enrolments->id_user_enrolments->status.
            // Makes no sense to me, thus the custom rename.
            if ($fileRec->filename == "combined.pdf" and $fileRec->filearea == "combined") {
                $grader = core_user::get_user($this->grade->grader);
                $student = core_user::get_user($this->submission->userid);
                if ($grader and $student) {
                    $gfullname = $grader->firstname . " " . $grader->lastname;
                    $sfullname = $student->firstname . " " . $student->lastname;
                    $fileSpecs->Filename = str_replace(" " , "_", "Kommentierte PDF Abgabedatei"
                            . (count($fileRecs)>1 ?"en" :"")
                            .". Bewerter in ist "
                            . $gfullname . ". Student_in ist_"
                            . $sfullname . ".pdf");
                }
            }

            $fileSpecs->Filepath = $fileRec->filepath;
            $fileSpecs->Source = base64_encode($Source);
            unset($Source);
            $fileSpecs->TimeCreated = $fileRec->timecreated;
            $fileSpecs->TimeModified = $fileRec->timemodified;
            $Files->File[] = $fileSpecs;
            $totalfilesize += $fileRec->filesize;

            // $this->output .= "\n\n" ."Sample fileSpecs Data: " .rmFileContent(var_export($fileSpecs,true))."\n";
        }
        /*if (!isset($Files->File)){
            $Files = array();
        }*/
        return array($Files, $submittedFiles, $totalfilesize);
    }

    function SoapDebug($client) {
        $response_param = $client->__getFunctions();
        $this->output .= "\nFilero API Functions:  " . var_export($response_param, true) . "\n\n";
        //$response_param = $client->__getTypes();
        $response_param = $client->__getLastRequestHeaders();
        $this->output .= "\n<b>__getLastRequestHeaders</b>: " . var_export($response_param, true) . "\n\n";
        $getLastRequest = $client->__getLastRequest();
        if (!empty($getLastRequest)) {
            $response_param =
                    preg_replace("|<ns1:Source>.*?</ns1:Source>|", "'<ns1:Source><removed></ns1:Source>',", $getLastRequest);
        }
        $this->output .= "\n<b>__getLastRequest</b>: " . htmlentities(var_export($response_param, true), ENT_XML1) . "\n\n";
        $response_param = $client->__getLastResponseHeaders();
        $this->output .= "\n<b>__getLastResponseHeaders</b>: " . $this->hideFileContent(var_export($response_param, true)) . "\n\n";
        $response_param = $client->__getLastResponse();
        $this->output .= "\n<b>__getLastResponse</b>: " .
                htmlentities($this->hideFileContent(var_export($response_param, true)), ENT_XML1) . "\n\n";
        return true;
    }

    /*
    * extract filepath from $file object
    */
    private function filero_get_filepath($file, $filearea) {
        global $CFG;
        if (isset($file->contenthash) and !empty($file->contenthash) and $file->filesize > 420
                and !empty($file->filename)) {
            $cHash = $file->contenthash;
        } else {
            return "";
        }
        //  $file->filesystem->file_system_filedir["filedir"]
        $filepath = $CFG->dataroot . "/filedir/" . substr($cHash, 0, 2) . "/" . substr($cHash, 2, 2) . "/" . $cHash;
        if (is_readable($filepath)) {
            return $filepath;
        }
        return "";
    }

    /*
    * remove file source from displayed and logged values
    */
    private function hideFileContent($string) {
        return preg_replace("|'Source' => '.*?',|i", "'Source' => 'verborgen - base64_encoded -',", $string);
    }

    // check if log file exists
    static public function LogfilePath($submissionid) {
        $logfile = assignsubmission_filero_LOG_FOLDER . "/submission_" . $submissionid . ".log";
        $isReadable = is_readable($logfile);
        if ( !$isReadable) {
            return ""; // "$logfile für Abgabe id $submissionid kann nicht gelesen werden!";
        }
        return $logfile;
    }

            // show log
    public function showLog($submissionid, $filerotimemodified = false) {
        $title = "FILERO Log of submissions and grades for submission #" . $submissionid;
        $logfile = assignsubmission_filero_LOG_FOLDER . "/submission_" . $submissionid . ".log";
        $needle = "\nSubmission ID: " . trim($submissionid) . "\n";
        $needlet = $needle . "Date: ";
        if ( is_file($logfile) AND is_writable($logfile) AND (filesize($logfile)/1024)>600 ){
            $tmp = file_get_contents($logfile);
            $tmp = substr($tmp,0,120000);
            $spos = strpos($tmp,$needlet);
            if ($spos>0){
                $tmp = substr($tmp,$spos);
                $saved_bytes = file_put_contents($logfile, $tmp) ?: 0;
            }
        }
        if (is_readable($logfile)) {
            $this->output = file_get_contents($logfile);
            /*

            if ( !empty($filerotimemodified)){
                $needlet .= $needlet .  date("D, d.m.Y ", $filerotimemodified);
            }
            if (!strstr($this->output, $needlet)) {
                print "\n<hr><b>$needlet not found in Log file " . $logfile . "!<br>\n";
                return false;
            }
            $this->output = substr($this->output, strrpos($this->output, $needlet));
            $pos = strpos(substr($this->output, strlen($needle)), "seconds" . $needle) - strlen("seconds" . $needle);
            $this->output = substr($this->output, 0, $pos . strlen($needle));
            */
            // $this->filero_log($submissionid);
        } else {
            $this->output = "Datei $logfile für Abgabe id $submissionid kann nicht gelesen werden!";
            // return false;
        }
        print nl2br("<head><title>$title</title></head><html><body><h2 title='Log file: $logfile'><b>$title</b></h2>"
                        . preg_replace("|\n(.*?):|", "\n<b>$1</b>:", strip_tags($this->output))) . "\n</body></html>";
        return true;
    }

    private function filero_log($submissionid) {
        $logfile = assignsubmission_filero_LOG_FOLDER . "/submission_" . $submissionid . ".log";
        $pluginfo = assign_submission_filero::get_plugin_version();
        $info = "Filero Plugin für Moodle. Plugin Version: ".$pluginfo->version." - Release: "
                .$pluginfo->release;
        $padding = round(81-(strlen($info)/2),0);
        if (!strstr($this->output, $info)) {
            $this->output .= str_repeat("_", $padding) . $info . str_repeat("_", $padding) . "\n";
        }
        else{
            $this->output .= str_repeat("_", 81) . "\n";
        }
        $saved_bytes = file_put_contents($logfile, $this->output, FILE_APPEND) ?: 0;
        assignsubmission_filero_observer::observer_log("Saved " . number_format(($saved_bytes / 1024), 0)
                . "KB data to Filero submission log $logfile\n");
        if ($this->debug) {
            print nl2br(preg_replace("|\n(.*?):|", "\n<b>$1</b>,", $this->output));
        }
        return true;
    }

    public function show_statement_accepted($submission) {
        global $DB;
        $statement_accepted = "";
        if ($filerorec = $DB->get_record('assignsubmission_filero', array('submission' => $submission->id))) {
            $statement_accepted = $filerorec->statement_accepted;
        }
        return $statement_accepted;
    }

    function safeCount($count) {
        if (is_countable($count)) {
            return count($count);
        }
        if (is_numeric($count)) {
            return $count;
        }
        return 0;
    }

    // show loading spinner icon / font awesome required / unused on July 4, 2023
    function showSpinner() {
        @ob_flush();
        @flush();
        print str_repeat("", 6000);
        echo "
        <style>
        .spinner, #spinner {
        position: fixed;
        left: 0px;
        top: 0px;
        width: 100%;
        height: 100%;
        z-index: 9999; 
        opacity: 0.4;
        }
        </style>
        ";
        echo '<div id="spinner" class="d-print-none" style="display:block;float:center;text-align:center;font-weeigt:bold;font-size:12em;">			
                <i style="color:blue;" class="d-print-none fa fa-spinner fa-pulse fa-6x fa-fw"></i></div>';
        echo "\n<script>
        function ev_spinner_disable()
        {	if ( document.getElementById('spinner')  !== null ) 
            {	document.getElementById('spinner').style.display='none'; }}
        }
        </script>\n";
        @ob_flush();
        @flush();
    }

    // convert-unix-timestamp-to-net-int64-datetime, unused at 20230528
    private function timeToTicks($timestamp = false) {
        if (!$timestamp) {
            $timestamp = time();
        }
        return ($timestamp * 10000000) + 621355968000000000;
    }

    private function ticksToTime($ticks = false) {
        if (!$ticks) {
            $ticks = timeToTicks();
        }
        return floor(($ticks - 621355968000000000) / 10000000);
    }

} // end class filero
