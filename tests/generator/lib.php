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
 * mod_mattermost data generator
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mattermost module data generator
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_mattermost_generator extends testing_module_generator {

    /**
     * Creates an instance of mattermost for testing purposes.
     *
     * @param array|stdClass $record data for module being generated.
     * @param null|array $options general options for course module.
     * @return stdClass record from module-defined table with additional field cmid
     */
    public function create_instance($record = null, array $options = null) {
        global $DB;
        $name = $record['name'];
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $editingteacher = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $now = time();
        $defaults = array(
            "mattermostid" => sha1(rand()),
            "name" => $name,
            "displaytype" => 1,
            "popupheight" => 700,
            "popupwidth" => 700,
            "channeladminroles" => "$editingteacher->id",
            "userroles" => "$student->id",
            "timecreated" => $now,
            "timemodified" => $now,
        );
        $record = (array)$record;
        foreach ($defaults as $key => $value) {
            if (!isset($record[$key])) {
                $record[$key] = $value;
            }
        }
        return parent::create_instance((object)$record, (array)$options);
    }
}
