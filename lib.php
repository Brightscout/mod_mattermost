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
 * Library of interface functions and constants.
 *
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Return if the plugin supports $feature.
 *
 * @param string $feature Constant representing theadd feature.
 * @return true | null True if the feature is supported, null otherwise.
 */
function mattermost_supports($feature) {
    if (!$feature) {
        return null;
    }
    $features = array(
        (string) FEATURE_IDNUMBER => true,
        (string) FEATURE_GROUPS => true,
        (string) FEATURE_GROUPINGS => true,
        (string) FEATURE_SHOW_DESCRIPTION => true,
        (string) FEATURE_MOD_INTRO => true,
        (string) FEATURE_COMPLETION_TRACKS_VIEWS => true,
        (string) FEATURE_GRADE_HAS_GRADE => false,
        (string) FEATURE_GRADE_OUTCOMES => false,
    );
    if (isset($features[(string) $feature])) {
        return $features[$feature];
    }
    return null;
}

/**
 * Saves a new instance of the mod_mattermost into the database.
 *
 * Given an object containing all the necessary data, (defined by the form
 * in mod_form.php) this function will create a new instance and return the id
 * number of the instance.
 *
 * @param object $moduleinstance An object from the form.
 * @param mod_mattermost_mod_form $mform The form.
 * @return int The id of the newly inserted record.
 */
function mattermost_add_instance($moduleinstance, $mform = null) {
    global $DB, $USER;
    $moduleinstance->timecreated = time();
    $moduleinstance->timemodified = $moduleinstance->timecreated;
    $cmid       = $moduleinstance->coursemodule;
    $course = $DB->get_record('course', array('id' => $moduleinstance->course));
    $channelname = mattermost_tools::mattermost_channel_name($cmid, $course);
    $mattermostapimanager = new mattermost_api_manager();
    try {
        $moduleinstance->mattermostid = $mattermostapimanager->create_mattermost_channel($channelname);
        if (is_null($moduleinstance->mattermostid)) {
            print_error('an error occured while creating Mattermost channel');
        }
        $id = $DB->insert_record('mattermost', $moduleinstance);
        // TODO: Add user enrolment logic.
        return $id;
    } catch(Exception $e) {
        print_error($e->getMessage());
    }
    
}

/**
 * Updates an instance of the mod_mattermost in the database.
 *
 * Given an object containing all the necessary data (defined in mod_form.php),
 * this function will update an existing instance with new data.
 *
 * @param object $moduleinstance An object from the form in mod_form.php.
 * @param mod_mattermost_mod_form $mform The form.
 * @return bool True if successful, false otherwise.
 */
function mattermost_update_instance($moduleinstance, $mform = null) {
    global $DB;
    $moduleinstance->timemodified = time();
    $moduleinstance->id = property_exists($moduleinstance, 'id') ? $moduleinstance->id : $moduleinstance->instance;
    $mattermost = $DB->get_record('mattermost', array('id' => $moduleinstance->id));
    $return = $DB->update_record('mattermost', $moduleinstance);
    if ($return) {
        $mattermostapimanager = new mattermost_api_manager();
        // TODO: Add synchronize channel members logic.
    }
    return $return;
}

/**
 * Removes an instance of the mod_mattermost from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function mattermost_delete_instance($id) {
    file_put_contents('/var/www/html/moodle/log.txt', $id.PHP_EOL, FILE_APPEND);
    global $DB;
    $mattermost = $DB->get_record('mattermost', array('id' => $id));
    if (!$mattermost) {
        return false;
    }
    // TODO: Add delete mattermost channel logic.
    $DB->delete_records('mattermost', array('id' => $id));

    return true;
}
