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
 * @author    Harry@Bleckert.com für LIB-IT DMS GmbH
 */

$string['location'] = 'FILERO SOAP URL';
$string['location_help'] = 'Hier muss die vollständige FILERO SOAP URL angegeben werden';
$string['username'] = 'FILERO Anmeldename';
$string['username_help'] = 'Der Anmeldename für das FILERO Konto';
$string['username'] = 'FILERO Anmeldename';
$string['password'] = 'FILERO Passwort';
$string['password_help'] = 'Passwort des FILERO Kontos';
$string['productkey'] = 'FILERO product key';
$string['productkey_help'] = 'Der sogenannte "product key" wird von FILERO vergeben';
$string['multiple_graders'] = 'Mehrere Gutachter_innen';
$string['multiple_graders_help'] = 'Wenn gesetzt, dann wird aus der jeweils ersten Aufgabe eine Kurses heraus,
                                    eine eigene Aufgabe für alle weiteren Gutacher_innen/Bewerter_innen automatisch angelegt,
                                    sobald die erste Abgabe erfolgt. Alle weiteren Abgaben werden automatisch synchronisiert.<br>
                                    In den so erzeugten Aufgaben sind keine Abgaben möglich.';
$string['title_tag'] = 'Bitte geben Sie den Basis Titel der Aufgabe an';
$string['title_tag_help'] = 'Der gewählte Basis Titel wird automatisch gesetzt. Endend auf die Namen Gutachter/Bewerter im Kurs';
$string['grader_roles'] = 'Zulässige Rollen für Gutachter/Bewerter';
$string['grader_roles_help'] = 'Es sind die numerischen Werte der jeweiligen Rollen, getrennt durch Komma, einzugeben<br>'
        . 'Standard: 3 = Dozent_in (editingteacher), 4 = non editing teacher, 5 = Student_in';

$string['countfiles'] = '{$a} Dateien';
$string['default'] = 'Standardmäßig aktiviert';
$string['default_help'] = 'Wenn festgelegt, wird diese Abgabemethode standardmäßig für alle neuen Aufgaben aktiviert.';
$string['defaultacceptedfiletypes'] = 'Standardmäßig akzeptierte Dateitypen';
$string['enabled'] = 'FILERO Archivierung';
$string['enabled_help'] = 'Wenn aktiviert werden Daten und Dateien von Abgaben und Feedbacks rechtssicher mit FILERO archiviert.';
$string['eventfileroarchived'] = 'Eine Datei wurde mit FILERO archiviert.';
$string['filero'] = 'FILERO Archivierung';
$string['fileroenabled'] = 'FILERO Archivierung aktivieren';
$string['fileroenabled_help'] = 'Auswählen um rechtssichere Archivierung mit FILERO zu aktivieren';
$string['filerofilename'] = 'FILERO.html';
$string['numfilesforlog'] = 'Anzahl der Dateien: {$a}.';
$string['pluginname'] = 'FILERO Archivierung';
$string['privacy:metadata:filepurpose'] = 'Die für diese Aufgabe geladenen Dateien';
$string['siteuploadlimit'] = 'Site-Upload-Limit';
$string['submissionfilearea'] = 'Zur Abgabe hochgeladene Dateien';
