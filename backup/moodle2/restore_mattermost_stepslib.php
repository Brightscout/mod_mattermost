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
 * All the steps to restore mod_mattermost are defined here.
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Abhishek Verma <abhishek.verma@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the structure step to restore one mod_mattermost activity.
 */
class restore_mattermost_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines the structure to be restored.
     *
     * @return restore_path_element[].
     */
    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('mattermost', '/activity/mattermost');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes the mattermost restore data.
     *
     * @param array $data Parsed element data.
     */
    protected function process_mattermost($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $modulename = $this->task->get_modulename();
        $data->course = $this->get_courseid();
        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }
        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }
        $newitemid = $DB->insert_record('mattermost', $data);
        $this->apply_activity_instance($newitemid);
    }
}
