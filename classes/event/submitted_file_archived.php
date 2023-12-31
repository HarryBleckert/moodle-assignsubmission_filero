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
 * The assignsubmission_filero archiving event.
 *
 * @package    assignsubmission_filero
 * @since      Moodle 4.1 * @copyright  2013 Frédéric Massart
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

namespace assignsubmission_filero\event;

use mod_assign\event\submission_updated;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The assignsubmission_filero archived after  event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int filesubmissioncount: The number of files uploaded.
 *      - string content: currently empty.
 *      - int submissionid: ID of Submission.
 *      - int submissionattempt: Number of submission attempts
 *      - string submissionstatus: Status of submission (Must be submittt).
 *      - int filesubmissioncount: Number of submitted files.
 *      - int groupid: ID of group if submitted by group.
 *      - string groupname: Name of group if submitted by group.
 *      - pathnamehashes: hashes of names of submitted files.
 *      - fileroresults: Array of filero archiving results stored in table assignsubmission_filero
 * }
 *
 * @package   assignsubmission_filero
 * @since     Moodle 4.1
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */
class submitted_file_archived extends submission_updated { // \core\event\assessable_submitted {

    /**
     * Legacy event files.
     *
     * @var array
     */
    protected $legacyfiles = array();

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        if (false) { // !isset($this->assign)) {
            // debugging('assign property should be initialised in each event', DEBUG_DEVELOPER);
            global $CFG;
            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            $cm = get_coursemodule_from_id('assign', $this->contextinstanceid, 0, false, MUST_EXIST);
            $course = get_course($cm->course);
            $this->assign = new \assign($this->get_context(), $cm, $course);
        }
        parent::init();
        $this->data['objecttable'] = 'assign_submission';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $descriptionstring =
                "Filero archiving of submitted data and files has been completed: The user with id '$this->userid' submitted " .
                "'{$this->other['filesubmissioncount']}' file/s for grading in the assignment with course module id " .
                "'$this->contextinstanceid'";
        if (!empty($this->other['groupid'])) {
            $descriptionstring .= " for the group with id '{$this->other['groupid']}'.";
        } else {
            $descriptionstring .= ".";
        }

        return $descriptionstring;
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return stdClass
     */
    protected function get_legacy_eventdata() {
        $eventdata = new stdClass();
        $eventdata->modulename = 'assign';
        $eventdata->cmid = $this->contextinstanceid;
        $eventdata->itemid = $this->objectid;
        $eventdata->courseid = $this->courseid;
        $eventdata->userid = $this->userid;
        if (count($this->legacyfiles) > 1) {
            $eventdata->files = $this->legacyfiles;
        }
        $eventdata->file = $this->legacyfiles;
        $eventdata->pathnamehashes = array_keys($this->legacyfiles);
        return $eventdata;
    }

    /**
     * Return the legacy event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'submitted_file_archived';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventfileroarchived', 'assignsubmission_filero');
    }

    /**
     * Get URL related to the action.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url('/mod/assign/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Sets the legacy event data.
     *
     * @param stdClass $legacyfiles legacy event data.
     * @return void
     */
    public function set_legacy_files($legacyfiles) {
        $this->legacyfiles = $legacyfiles;
    }

    public static function get_objectid_mapping() {
        return array('db' => 'assign_submission', 'restore' => 'submission');
    }
}
