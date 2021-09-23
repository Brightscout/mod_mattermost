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
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_mattermost;

use mod_mattermost\tools\mattermost_tools;
use mod_mattermost\api\manager\mattermost_api_manager;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * A class for the observers (event listeners)
 */
class observers
{
    /**
     * Event handler function to handle the role_assigned event
     *
     * @param \core\event\role_assigned $event
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();
            $userid = $event->relateduserid;
            $moodleuser = $DB->get_record('user', array('id' => $userid));
            $roleid = $event->objectid;
            if (($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_MODULE)
                && is_enrolled($context, $moodleuser->id)
            ) {
                $coursecontext = null;
                if ($context->contextlevel == CONTEXT_COURSE) {
                    $coursecontext = $context;
                } else {
                    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
                    $coursecontext = \context_course::instance($cm->course);
                }
                if (($context->contextlevel == CONTEXT_COURSE
                    && mattermost_tools::course_has_mattermost_module_instance($coursecontext->instanceid))
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

    /**
     * Event handler function to handle the role_unassigned event
     *
     * @param \core\event\role_unassigned $event
     */
    public static function role_unassigned(\core\event\role_unassigned $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();
            $userid = $event->relateduserid;
            $moodleuser = $DB->get_record('user', array('id' => $userid));
            $roleid = $event->objectid;
            $cm = null;
            if (($context->contextlevel == CONTEXT_COURSE || $context->contextlevel == CONTEXT_MODULE)) {
                $coursecontext = null;
                if ($context->contextlevel == CONTEXT_COURSE) {
                    $coursecontext = $context;
                } else {
                    $cm = $DB->get_record('course_modules', array('id' => $context->instanceid));
                    $coursecontext = \context_course::instance($cm->course);
                }
                if (($context->contextlevel == CONTEXT_COURSE
                    && mattermost_tools::course_has_mattermost_module_instance($coursecontext->instanceid))
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

    /**
     * Event handler function to handle the user_updated event
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $event->objectid));
        if (!$user) {
            throw new moodle_exception('usernotfoundonupdationerror', 'mod_mattermost');
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
                // TODO: Add call for update user here.
                mattermost_tools::synchronize_user_enrolments($user->id);
            }
        }
    }

    /**
     * Event handler function to handle the group_created event
     *
     * @param \core\event\group_created $event
     */
    public static function group_created(\core\event\group_created $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();

            if ($context->contextlevel == CONTEXT_COURSE &&
                mattermost_tools::course_has_mattermost_module_instance($context->instanceid)) {
                $course = $DB->get_record('course', array('id' => $event->courseid));
                $group = $DB->get_record('groups', array('id' => $event->objectid));
                $channelname = mattermost_tools::get_mattermost_channel_name_for_group($course, $group);
                $mattermostapimanager = new mattermost_api_manager();

                [$result, $error] = $mattermostapimanager->call_mattermost_api(
                    array($mattermostapimanager, 'create_mattermost_channel'),
                    [$channelname]
                );

                if ($error) {
                    $errormessage = $error['message'];
                    if (!strpos($errormessage, 'A channel with that name already exists on the same team.')) {
                        throw new moodle_exception('mmchannelcreationerror', 'mod_mattermost', '', $errormessage);
                    }
                    // If a group with same name already existed before on Mattermost, then add a timestamp.
                    // At end of the channel name.
                    $mattermostchannelid =
                        $mattermostapimanager->create_mattermost_channel($channelname . '_' . time());
                } else {
                    $mattermostchannelid = $result;
                }

                $DB->insert_record('mattermostxgroups', array(
                    'groupid' => $group->id,
                    'channelid' => $mattermostchannelid,
                    'courseid' => $course->id,
                    'name' => $group->name
                ));
            }
        }
    }

    /**
     * Event handler function to handle the group_deleted event
     *
     * @param \core\event\group_deleted $event
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $group = $DB->get_record('mattermostxgroups', array('groupid' => $event->objectid));

            if ($group) {
                $mattermostapimanager = new mattermost_api_manager();
                $mattermostapimanager->archive_mattermost_channel($group->channelid);
                // Delete group record only if moodle group is permanently deleted and not recycled.
                $DB->delete_records('mattermostxgroups', array('groupid' => $event->objectid, 'categorybinid' => null));
            }
        }
    }

    /**
     * Event handler function to handle the course_bin_item_created event
     * Adds record of recycled instance into 'mattermostxrecyclebin' table
     *
     * @param \tool_recyclebin\event\course_bin_item_created $event
     */
    public static function course_bin_item_created(\tool_recyclebin\event\course_bin_item_created $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            $courseinfo = $event->other;

            // Check that this is a mattermost module instance.
            $mattermostmodule =
                mattermost_tools::get_mattermost_module_instance_from_course_module(
                    $courseinfo['cmid']
                );

            if ($mattermostmodule) {
                $mattermost = $DB->get_record('mattermost', array('id' => $courseinfo['instanceid']));
                // Insert item into association table.
                $record = new \stdClass();
                $record->cmid = $courseinfo['cmid'];
                $record->mattermostid = $mattermost->mattermostid;
                $record->binid = $event->objectid;
                $DB->insert_record('mattermostxrecyclebin', $record);
            }

            // Update bin id for mattermost groups to be uniquely identified during instance deletion/restoration.
            mattermost_tools::update_mattermost_group_record($event->objectid, $mattermost->course);
        }
    }

    /**
     * Event handler function to handle the course_bin_item_deleted event
     * Deletes record of instances present in course recyclebin from 'mattermostxrecyclebin' table
     *
     * @param \tool_recyclebin\event\course_bin_item_deleted $event
     */
    public static function course_bin_item_deleted(\tool_recyclebin\event\course_bin_item_deleted $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            $mattermostrecyclebin = $DB->get_record('mattermostxrecyclebin', array('binid' => $event->objectid));
            $mattermostgroups = $DB->get_records('mattermostxgroups', array('binid' => $event->objectid));

            if ($mattermostrecyclebin) {
                $DB->delete_records('mattermostxrecyclebin', array('id' => $mattermostrecyclebin->id));
            }

            // Delete record from database when group is permanently deleted.
            if (!empty($mattermostgroups)) {
                foreach ($mattermostgroups as $mattermostgroup) {
                    if (!empty($mattermostgroup)) {
                        $DB->delete_records('mattermostxgroups', array('channelid' => $mattermostgroup->channelid));
                    }
                }
            }
        }
    }

    /**
     * Event handler function to handle the group_member_added event
     *
     * @param \core\event\group_member_added $event
     */
    public static function group_member_added(\core\event\group_member_added $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();

            if ($context->contextlevel == CONTEXT_COURSE &&
                mattermost_tools::course_has_mattermost_module_instance($context->instanceid)) {
                $courseid = $event->courseid;
                $groupid = $event->objectid;
                $userid = $event->relateduserid;
                $mattermostgroup = $DB->get_record('mattermostxgroups', array('groupid' => $groupid));
                $mattermostmoduleinstance = $DB->get_record('mattermost', array('course' => $courseid));
                $coursecontext = \context_course::instance($courseid);

                $background = (boolean)get_config('mod_mattermost', 'background_synchronize');
                if ($background) {
                    $taskenrolment = new \mod_mattermost\task\enrol_user_to_mattermost_channel();
                    $taskenrolment->set_custom_data(
                        array(
                            'mattermostid' => $mattermostgroup->channelid,
                            'channeladminroles' => $mattermostmoduleinstance->channeladminroles,
                            'userroles' => $mattermostmoduleinstance->userroles,
                            'userid' => $userid,
                            'coursecontextid' => $coursecontext->id,
                            'mattermostinstanceid' => $mattermostmoduleinstance->id
                        )
                    );
                    \core\task\manager::queue_adhoc_task($taskenrolment);
                } else {
                    mattermost_tools::enrol_user_to_mattermost_channel(
                        $mattermostgroup->channelid,
                        $mattermostmoduleinstance->channeladminroles,
                        $mattermostmoduleinstance->userroles,
                        $userid,
                        $coursecontext->id,
                        $mattermostmoduleinstance->id
                    );
                }
            }
        }
    }

    /**
     * Restores mattermost instance, when instance is restored from
     * the course recycle bin
     *
     * @param \tool_recyclebin\event\course_bin_item_restored $event
     */
    public static function course_bin_item_restored(\tool_recyclebin\event\course_bin_item_restored $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            // Check that this is a mattermost module.
            $mattermostrecyclebin = $DB->get_record('mattermostxrecyclebin', array('binid' => $event->objectid));
            if ($mattermostrecyclebin) {
                $mattermostapimanager = new mattermost_api_manager();
                $mattermost = $DB->get_record('mattermost', array('mattermostid' => $mattermostrecyclebin->mattermostid));

                $coursemodule = $DB->get_record('course_modules', array('instance' => $mattermost->id));

                if (empty($mattermost)) {
                    return;
                }

                // Unarchive channel only if intance is not hidden.
                if ($coursemodule->visible) {
                    $mattermostapimanager->unarchive_mattermost_channel(
                        $mattermostrecyclebin->mattermostid,
                        $mattermost->course,
                        null
                    );

                    // After Mattermost instance restored from course recyclebin.
                    // Synchronise course's group channels, if any new one is created.
                    mattermost_tools::synchronize_groups($coursemodule->course);
                }

                // Update binid for mattermost groups after deleted instance is restored.
                $DB->execute("UPDATE {mattermostxgroups} SET binid=null WHERE binid=?",
                    array($event->objectid));

                // Synchronise course channel members.
                mattermost_tools::synchronize_channel_members($mattermostrecyclebin->mattermostid,
                    (boolean)get_config('mod_mattermost', 'background_synchronize'));

                // Delete record from recyclebin, when restored.
                $DB->delete_records('mattermostxrecyclebin', array('id' => $mattermostrecyclebin->id));

            }
        }
    }

    /**
     * Event handler function to handle the category_bin_item_created event
     * Adds record of recycled courses into 'mattermostxrecyclebin' table
     *
     * @param \tool_recyclebin\event\category_bin_item_created $event
     */
    public static function category_bin_item_created(\tool_recyclebin\event\category_bin_item_created $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            $courseinfo = $event->other;
            // Check that this is a mattermost module instance.
            $mattermostmodule = mattermost_tools::get_mattermost_module_instance_from_course_module_using_course_id(
                $courseinfo['courseid']
            );

            if ($mattermostmodule) {
                $mattermost = $DB->get_record('mattermost', array('id' => $mattermostmodule->instance));
                // Insert item into association table.
                $record = new \stdClass();
                $record->cmid = $mattermostmodule->id;
                $record->mattermostid = $mattermost->mattermostid;
                $record->binid = $event->objectid;
                $DB->insert_record('mattermostxrecyclebin', $record);

                // Update bin id for a group to be uniquely identified during course restoration.
                mattermost_tools::update_category_bin_id_mattermost_group($event->objectid, $courseinfo['courseid']);
            }
        }
    }

    /**
     * Event handler function to handle the group_member_removed event
     *
     * @param \core\event\group_member_removed $event
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled()) {
            $context = $event->get_context();

            if ($context->contextlevel == CONTEXT_COURSE &&
                mattermost_tools::course_has_mattermost_module_instance($context->instanceid)) {
                $groupid = $event->objectid;
                $userid = $event->relateduserid;
                $mattermostgroup = $DB->get_record('mattermostxgroups', array('groupid' => $groupid));
                $mattermostmoduleinstance = $DB->get_record('mattermost', array('course' => $mattermostgroup->courseid));

                $background = (boolean)get_config('mod_mattermost', 'background_synchronize');
                if ($background) {
                    $taskunenrolment = new \mod_mattermost\task\unenrol_user_from_mattermost_channel();
                    $taskunenrolment->set_custom_data(
                        array(
                            'channelid' => $mattermostgroup->channelid,
                            'userid' => $userid,
                            'mattermostinstanceid' => $mattermostmoduleinstance->id
                        )
                    );
                    \core\task\manager::queue_adhoc_task($taskunenrolment);
                } else {
                    mattermost_tools::unenrol_user_from_mattermost_channel(
                        $mattermostgroup->channelid, $userid, $mattermostmoduleinstance->id
                    );
                }
            }
        }
    }

    /**
     * Event handler function to handle the category_bin_item_deleted event
     * Deletes record of courses present in category recyclebin from 'mattermostxrecyclebin' table
     *
     * @param \tool_recyclebin\event\category_bin_item_deleted $event
     */
    public static function category_bin_item_deleted(\tool_recyclebin\event\category_bin_item_deleted $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            $mattermostrecyclebins = $DB->get_records('mattermostxrecyclebin', array('binid' => $event->objectid));
            $mattermostgroups = $DB->get_records('mattermostxgroups', array('categorybinid' => $event->objectid));

            if (!empty($mattermostrecyclebins)) {
                foreach ($mattermostrecyclebins as $mattermostrecyclebin) {
                    if (!empty($mattermostrecyclebin)) {
                        $DB->delete_records('mattermostxrecyclebin', array('id' => $mattermostrecyclebin->id,
                            'mattermostid' => $mattermostrecyclebin->mattermostid));
                    }
                }
            }

            // Delete record from database when group is permanently deleted.
            if (!empty($mattermostgroups)) {
                foreach ($mattermostgroups as $mattermostgroup) {
                    if (!empty($mattermostgroup)) {
                        $DB->delete_records('mattermostxgroups', array('channelid' => $mattermostgroup->channelid));
                    }
                }
            }
        }
    }

    /**
     * Restores mattermost instance when course is restored from
     * the category recycle bin
     *
     * @param tool_recyclebin\event\category_bin_item_restored $event
     */
    public static function category_bin_item_restored(\tool_recyclebin\event\category_bin_item_restored $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && mattermost_tools::is_patch_installed()) {
            $mattermostrecyclebins = $DB->get_records('mattermostxrecyclebin', array('binid' => $event->objectid));
            $mattermostapimanager = null;
            if (empty($mattermostrecyclebins)) {
                return;
            }
            $mattermostapimanager = new mattermost_api_manager();

            foreach ($mattermostrecyclebins as $mattermostrecyclebin) {
                $mattermostapimanager->unarchive_mattermost_channel($mattermostrecyclebin->mattermostid, null, $event->objectid);
                $mattermost = $DB->get_record('mattermost', array('mattermostid' => $mattermostrecyclebin->mattermostid));
                $groups = $DB->get_records('groups', array('courseid' => $mattermost->course));

                // Update groupid, courseid and binid in mattermostxgroups after deleted course is restored.
                foreach ($groups as $group) {
                    $DB->execute(
                        "UPDATE {mattermostxgroups} SET groupid=?, courseid=?, categorybinid=null WHERE categorybinid=? AND name=?",
                        array($group->id, $mattermost->course, $event->objectid, $group->name));
                }
                $DB->delete_records('mattermostxrecyclebin', array('id' => $mattermostrecyclebin->id,
                    'mattermostid' => $mattermostrecyclebin->mattermostid));
            }
        }
    }

    /**
     * Event handler function to handle the course_module_updated event
     * Archives/Unarchives the channel when instance visibility is changed.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        global $DB;
        if (mattermost_tools::mattermost_enabled() && $event->other['modulename'] == 'mattermost') {
            $coursemodule = $DB->get_record('course_modules', array('id' => $event->objectid));
            $mattermost = $DB->get_record('mattermost', array('id' => $event->other['instanceid']));
            if (!empty($mattermost)) {
                $mattermostapimanager = new mattermost_api_manager();
                if (!$coursemodule->visible or !$coursemodule->visibleoncoursepage) {
                    // It detects the change in instance visibility on course.
                    // Can't detect the change in course visibility here.
                    $mattermostapimanager->archive_mattermost_channel($mattermost->mattermostid, $mattermost->course);
                } else {
                    $mattermostapimanager->unarchive_mattermost_channel($mattermost->mattermostid, $coursemodule->course, null);
                }
            }
        }
    }

    /**
     * Event handler for the user enrolment updated event. It unenrols/enrols the user
     * from mattermost channels when the user is suspended or made active in the course
     *
     * @param \core\event\user_enrolment_updated $event
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event) {
        global $DB;
        $userenrolmentid = $event->objectid;
        $userid = $event->relateduserid;
        $courseid = $event->courseid;
        $userenrolment = $DB->get_record('user_enrolments', array('id' => $userenrolmentid));
        if (!$userenrolment) {
            throw new moodle_exception('userenrolmentnotfounderror', 'mod_mattermost');
        }

        $mattermostinstance = $DB->get_record('mattermost', array('course' => $courseid));
        if (!$mattermostinstance) {
            return;
        }

        $channelids = array($mattermostinstance->mattermostid);
        $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseid));
        foreach ($groups as $group) {
            array_push($channelids, $group->channelid);
        }

        $coursecontext = \context_course::instance($courseid);
        foreach ($channelids as $channelid) {
            if ($userenrolment->status == 1) {
                mattermost_tools::unenrol_user_from_mattermost_channel($channelid, $userid, $mattermostinstance->id);
            } else {
                mattermost_tools::enrol_user_to_mattermost_channel(
                    $channelid,
                    $mattermostinstance->channeladminroles,
                    $mattermostinstance->userroles,
                    $userid,
                    $coursecontext->id,
                    $mattermostinstance->id
                );
            }
        }
    }
}
