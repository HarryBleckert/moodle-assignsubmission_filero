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
 * This file contains the class for backup of this submission plugin
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Provides the information to backup filero submissions
 *
 * This just adds its filearea to the annotations and records the submissiontext and format
 *
 * @package assignsubmission_filero
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */
class backup_assignsubmission_filero_subplugin extends backup_subplugin {

    /**
     * Returns the subplugin information to attach to submission element
     *
     * @return backup_subplugin_element
     */
    protected function define_submission_subplugin_structure() {

        // Create XML elements.
        $subplugin = $this->get_subplugin_element();
        $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
        $subpluginelement = new backup_nested_element('submission_filero',
                null,
                array('submission'));

        // Connect XML elements into the tree.
        $subplugin->add_child($subpluginwrapper);
        $subpluginwrapper->add_child($subpluginelement);

        // Set source to populate the data.
        $subpluginelement->set_source_table('assignsubmission_filero',
                array('submission' => backup::VAR_PARENTID));

        /*
         $subpluginelement->annotate_files('assignsubmission_filero',
                'submission_filero',
                'submission');
        */
        return $subplugin;
    }

}
