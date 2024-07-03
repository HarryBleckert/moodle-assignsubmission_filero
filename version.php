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
 * This file contains the version information for the filero submission plugin
 *
 * @package    assignsubmission_filero
 * @copyright 2023 DHBW {@link https://DHBW.de/}
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH {@link https://www.LIB-IT.de/}
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2024070300;
$plugin->release = "v1.2-rc";
$plugin->component = 'assignsubmission_filero';
$plugin->requires = 2021051700;

// The plugin is a pre-release version.
// $plugin->maturity = MATURITY_ALPHA;

// The plugin is a beta version.
// $plugin->maturity = MATURITY_BETA;

// The plugin is a release candidate version.
$plugin->maturity = MATURITY_RC;

// The plugin is a stable version.
// $plugin->maturity = MATURITY_STABLE;
