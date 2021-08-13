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
 * observers file
 * @package     mod_mattermost
 * @category    observer
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_mattermost;

// use mod_mattermost\api\manager\mattermost_api_manager;
use mod_mattermost\tools\mattermost_tools;

defined('MOODLE_INTERNAL') || die();

class observers {

    public static function role_assigned(\core\event\role_assigned $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();
            $userid = $event->relateduserid;
            $moodleuser = $DB->get_record('user', array('id' => $userid));
            $roleid = $event->objectid;
            if (($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_MODULE)
                && is_enrolled($context, $moodleuser->id)) {
                $coursecontext = null;
                if ($context->contextlevel == CONTEXT_COURSE) {
                    $coursecontext = $context;
                } else {
                    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
                    $coursecontext = \context_course::instance($cm->course);
                }
                if (
                    ($context->contextlevel == CONTEXT_COURSE
                        && mattermost_tools::course_has_mattermost_module_instances($coursecontext->instanceid))
                    || ($context->contextlevel == CONTEXT_MODULE
                        && mattermost_tools::is_module_a_mattermost_instance($cm->id))
                ) {
                    mattermost_tools::role_assign($coursecontext->instanceid, $roleid, $moodleuser, $context);
                }
            }
        }
    }

    public static function role_unassigned(\core\event\role_unassigned $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();
            $userid = $event->relateduserid;
            $moodleuser = $DB->get_record('user', array('id' => $userid));
            $roleid = $event->objectid;
            $cm = null;
            if ( ($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_MODULE)) {
                $coursecontext = null;
                if ($context->contextlevel == CONTEXT_COURSE) {
                    $coursecontext = $context;
                } else {
                    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
                    $coursecontext = \context_course::instance($cm->course);
                }
                if (
                    ($context->contextlevel == CONTEXT_COURSE
                        && mattermost_tools::course_has_mattermost_module_instances($coursecontext->instanceid))
                    || ($context->contextlevel == CONTEXT_MODULE && mattermost_tools::is_module_a_mattermost_instance($cm->id))
                ) {
                    mattermost_tools::role_unassign($coursecontext->instanceid, $roleid, $moodleuser, $context);
                }
            }
        }
    }
}
