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
                    $backenrolmentsmethods = array_filter(
                        explode(',', get_config('mod_mattermost', 'background_enrolment_task'))
                    );
                    $component = empty($event->other['component']) ? 'enrol_manual' : $event->other['component'];
                    if (in_array($component, $backenrolmentsmethods)) {
                        $contextobject = new \stdClass();
                        $contextobject->contextlevel = $context->contextlevel;
                        $contextobject->id = $context->id;
                        $contextobject->instanceid = $context->instanceid;
                        $taskenrolment = new \mod_mattermost\task\enrol_role_assign();
                        $taskenrolment->set_custom_data(
                            array(
                                'courseid' => $coursecontext->instanceid,
                                'roleid' => $roleid,
                                'moodleuser' => $moodleuser,
                                'context' => $contextobject
                            )
                        );
                        \core\task\manager::queue_adhoc_task($taskenrolment);
                    } else {
                        mattermost_tools::role_assign($coursecontext->instanceid, $roleid, $moodleuser, $context);
                    }
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
                    $backenrolmentsmethods = array_filter(
                        explode(',', get_config('mod_mattermost', 'background_enrolment_task'))
                    );
                    $component = empty($event->other['component']) ? 'enrol_manual' : $event->other['component'];
                    if (in_array($component, $backenrolmentsmethods)) {
                        $contextobject = new \stdClass();
                        $contextobject->contextlevel = $context->contextlevel;
                        $contextobject->id = $context->id;
                        $contextobject->instanceid = $context->instanceid;
                        $taskunenrolment = new \mod_mattermost\task\enrol_role_unassign();
                        $taskunenrolment->set_custom_data(
                            array(
                                'courseid' => $coursecontext->instanceid,
                                'roleid' => $roleid,
                                'moodleuser' => $moodleuser,
                                'context' => $contextobject
                            )
                        );
                        \core\task\manager::queue_adhoc_task($taskunenrolment);
                    } else {
                        mattermost_tools::role_unassign($coursecontext->instanceid, $roleid, $moodleuser, $context);
                    }
                }
            }
        }
    }

    public static function user_updated(\core\event\user_updated $event) {
        global $DB;
        $user = $DB->get_record('user' , array('id' => $event->objectid));
        if (!$user) {
            print_error('user not found on user_updated event in mod_mattermost');
        }
        $backgrounduserupdate = get_config('mod_mattermost', 'background_user_update');
        if ($user->suspended || $user->deleted) {
            
            if ($backgrounduserupdate) {
                $taskunenrol = new \mod_mattermost\task\unenrol_user_everywhere();
                $taskunenrol->set_custom_data(
                    array('userid' => $user->id)
                );
                \core\task\manager::queue_adhoc_task($taskunenrol);
            } else {
                mattermost_tools::unenrol_user_everywhere($user->id);
            }
        } else {
            if ($backgrounduserupdate) {
                $taskenrol = new \mod_mattermost\task\synchronize_user_everywhere();
                $taskenrol->set_custom_data(
                    array('userid' => $user->id)
                );
                \core\task\manager::queue_adhoc_task($taskenrol);
            } else {
                // mattermost_tools::update_user($user->id);
                mattermost_tools::synchronize_user_enrolments($user->id);
            }
        }
    }
}
