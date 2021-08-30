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
 * Plugin internal classes, functions and constants are defined here.
 *
 * @package   mod_mattermost
 * @copyright 2020 Manoj <manoj@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\tools;

defined('MOODLE_INTERNAL') || die();

use context_course;
use lang_string;
use stdClass;
use \mod_mattermost\api\manager\mattermost_api_manager;
use moodle_exception;

/**
 * Class for some tool functions which provide various functionality.
 */
class mattermost_tools
{
    /**
     * Display new window
    */
    const DISPLAY_NEW = 1;
    /**
     * Display in curent window
    */
    const DISPLAY_CURRENT = 2;
    /**
     * Display in popup
    */
    const DISPLAY_POPUP = 3;

    /**
     * Construct display options.
     *
     * @return array
     * @throws coding_exception
     */
    public static function get_display_options() {
        $options = array();
        $options[self::DISPLAY_NEW] = get_string('displaynew', 'mod_mattermost');
        $options[self::DISPLAY_CURRENT] = get_string('displaycurrent', 'mod_mattermost');
        $options[self::DISPLAY_POPUP] = get_string('displaypopup', 'mod_mattermost');
        return $options;
    }

    /**
     * Get mattermost channel name corresponding to the moodle course.
     *
     * @param int $cmid
     * @param course $course
     * @return string|string[]|null
     */
    public static function get_mattermost_channel_name_for_instance($cmid, $course) {
        global $CFG, $SITE;
        $formatarguments = new stdClass();
        $formatarguments->moodleshortname = $SITE->shortname;
        $formatarguments->moodlefullname = $SITE->fullname;
        $formatarguments->moodleid = sha1($CFG->wwwroot);
        $formatarguments->moduleid = $cmid;
        $formatarguments->modulemoodleid = sha1($SITE->shortname . '_' . $cmid);
        $formatarguments->courseid = $course->id;
        $formatarguments->courseshortname = $course->shortname;
        $formatarguments->coursefullname = $course->fullname;
        $channelnametoformat = get_config('mod_mattermost', 'channelnametoformat');
        $channelnametoformat = is_null($channelnametoformat) ?
            '{$a->moodleid}_{$a->courseshortname}_{$a->moduleid}' :
            $channelnametoformat;
        $channelname = self::format_string($channelnametoformat, $formatarguments);
        return self::sanitize_channelname($channelname);
    }

    /**
     * Get mattermost channel name corresponding to the moodle group inside a course.
     *
     * @param course $course
     * @param group $group
     * @return string|string[]|null
     */
    public static function get_mattermost_channel_name_for_group($course, $group) {
        $formatarguments = new stdClass();
        $formatarguments->courseid = $course->id;
        $formatarguments->courseshortname = $course->shortname;
        $formatarguments->coursefullname = $course->fullname;
        $formatarguments->groupid = $group->id;
        $formatarguments->groupname = $group->name;
        $channelgroupnametoformat = get_config('mod_mattermost', 'channelgroupnametoformat');
        $channelgroupnametoformat = is_null($channelgroupnametoformat) ?
            '{$a->courseshortname}_{$a->groupname}' :
            $channelgroupnametoformat;
        $channelname = self::format_string($channelgroupnametoformat, $formatarguments);
        return self::sanitize_channelname($channelname);
    }

    /**
     * Formats the string according to the format arguments provided
     * by replacing the arguments' value in the given string.
     *
     * @param string $string
     * @param object $a Format arguments
     * @return string|string[]
     */
    private static function format_string($string, $a) {
        if ($a !== null) {
            // Process array's and objects (except lang_strings).
            if (is_array($a) or (is_object($a) && !($a instanceof lang_string))) {
                $a = (array)$a;
                $search = array();
                $replace = array();
                foreach ($a as $key => $value) {
                    if (is_int($key)) {
                        // We do not support numeric keys - sorry!
                        continue;
                    }
                    if (is_array($value) or (is_object($value) && !($value instanceof lang_string))) {
                        // We support just string or lang_string as value.
                        continue;
                    }
                    $search[]  = '{$a->' . $key . '}';
                    $replace[] = (string)$value;
                }
                if ($search) {
                    $string = str_replace($search, $replace, $string);
                }
            } else {
                $string = str_replace('{$a}', (string)$a, $string);
            }
        }
        return strtolower($string);
    }

    /**
     * Sanitize channelname matches the channel name with the regular expression
     * for the mattermost channel name defined in the plugin settings and replaces
     * any invalid character with underscore.
     *
     * @param  string $channelname
     * @return string|string[]|null
     * @throws moodle_exception
     */
    private static function sanitize_channelname($channelname) {
        // Replace white spaces anyway.
        $channelname = preg_replace('/\/s/', '_', $channelname);
        $channelname =
            preg_replace(get_config('mod_mattermost', 'validationchannelnameregex'), '_', $channelname);
        if (empty($channelname)) {
            throw new moodle_exception('mmchannelnameerror', 'mod_mattermost');
        }
        return $channelname;
    }

    /**
     * Returns the link for the mattermost channel by taking the channel id as param.
     *
     * @param string $mattermostchannelid
     * @return string url for the mattermost channel
     */
    public static function get_channel_link($mattermostchannelid) {
        $mattermostmanager = new mattermost_api_manager();
        return $mattermostmanager->get_instance_url() . '/'. $mattermostmanager->get_team_slugname() .
            '/channels/' . $mattermostchannelid;
    }

    /**
     * Returns true if module mattermost is visible and basic plugin settings
     * like instanceurl, secret and teamslugname are not empty otherwise returns false.
     *
     * @return bool
     */
    public static function mattermost_enabled() {

        global $DB;
        $module = $DB->get_record('modules', array('name' => 'mattermost'));
        if (!empty($module->visible)) {
            $config = get_config('mod_mattermost');
            if (!empty($config->instanceurl) && !empty($config->secret)
                && !empty($config->teamslugname)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Finds if a course has mattermost module instance.
     *
     * @param int $courseid
     * @return bool true if course has a mattermost module instance, false otherwise
     */
    public static function course_has_mattermost_module_instance($courseid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.course=:courseid';
        return $DB->record_exists_sql($sql, array('courseid' => $courseid, 'mattermost' => 'mattermost'));
    }

    /**
     * Finds if a course module is a mattermost instance.
     *
     * @param int $cmid - Course module id
     * @return bool true if course module is a mattermost instance, false otherwise
     */
    public static function is_module_a_mattermost_instance($cmid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.id=:cmid';
        return $DB->record_exists_sql($sql, array('cmid' => $cmid, 'mattermost' => 'mattermost'));
    }

    /**
     * Fetches mattermost module instances from database with given course id
     *
     * @param int $courseid
     * @return array all mattermost module instances in the course
     */
    public static function get_mattermost_module_instance_from_course($courseid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.course=:courseid';
        $moduleinstances = $DB->get_record_sql($sql , array('courseid' => $courseid, 'mattermost' => 'mattermost'));
        return $moduleinstances;
    }

    /**
     * Fetches mattermost module instances from database with given course module id
     *
     * @param int $cmid Id of the course module
     * @return array all mattermost module instances in the course
     */
    public static function get_mattermost_module_instance_from_course_module($cmid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.id=:cmid';
        $moduleinstances = $DB->get_record_sql($sql , array('cmid' => $cmid, 'mattermost' => 'mattermost'));
        return $moduleinstances;
    }

    /**
     * Fetches mattermost channel ids corresponding to the moodle groups 
     * inside a course which contain given user id as a member
     *
     * @param int $courseid Id of the course
     * @param int $userid Id of the user which is a member in the groups
     * @return array all mattermost channel ids corresponding to the groups
     */
    public static function get_mattermost_channelids_for_groups_with_user($courseid, $userid) {
        global $DB;
        $sql = 'select mg.channelid from {mattermostxgroups} mg inner join'
        . ' {groups_members} gm on gm.groupid = mg.groupid'
        . ' where mg.courseid = :courseid and gm.userid = :userid';
        $channelids = $DB->get_records_sql($sql, array(
            'courseid' => $courseid,
            'userid' => $userid
        ));

        return array_keys($channelids);
    }

    /**
     * Fetches the user information for all the users
     * which are group members for the given group id
     * 
     * @param int $groupid - Id of the group
     * @return array all user infos which are members in given group
     */
    public static function get_group_members_for_group($groupid) {
        global $DB;
        $sql = 'select u.* from {groups_members} gm inner join'
        . ' {user} u on gm.userid = u.id'
        . ' where gm.groupid = :groupid';
        return $DB->get_records_sql($sql, array('groupid' => $groupid));
    }

    /**
     * Finds if the moodlemember has a user role in Mattermost.
     *
     * @param  array $userroleids
     * @param  object $moodlemember
     * @param  int $coursecontextid
     * @return bool true if moodleuser has a role which is a user role in Mattermost, false otherwise
     */
    public static function has_mattermost_user_role(array $userroleids, $moodlemember, $coursecontextid) {
        $isuser = false;
        foreach ($userroleids as $userroleid) {
            if (user_has_role_assignment($moodlemember->id, $userroleid, $coursecontextid)) {
                $isuser = true;
                break;
            }
        }
        return $isuser;
    }

    /**
     * Finds if the moodlemember has a channel admin role in Mattermost.
     *
     * @param  array $channeladminroleids
     * @param  object $moodlemember
     * @param  int $coursecontextid
     * @return bool true if moodleuser has a role which is a channel admin role in Mattermost, false otherwise
     */
    public static function has_mattermost_channeladmin_role(
        array $channeladminroleids,
        $moodlemember,
        $coursecontextid
    ) {
        $ischanneladmin = false;
        foreach ($channeladminroleids as $channeladminroleid) {
            if (user_has_role_assignment($moodlemember->id, $channeladminroleid, $coursecontextid)) {
                $ischanneladmin = true;
                break;
            }
        }
        return $ischanneladmin;
    }

    /**
     * Enrols all the users in a course to the respective mattermost channel
     *
     * @param object $mattermostmoduleinstance
     * @param bool $background - whether the enrolment should be done with the use of adhoc tasks
     * @param bool $forcecreator - whether the currently logged in user should be created synchronously
     */
    public static function enrol_all_concerned_users_to_mattermost_channel_for_course(
        $mattermostmoduleinstance,
        $background = false,
        $forcecreator = false
    ) {
        global $USER;
        $courseid = $mattermostmoduleinstance->course;
        $coursecontext = context_course::instance($courseid);
        $users = get_enrolled_users($coursecontext);
        foreach ($users as $user) {
            if (!$background || ($forcecreator && $user->id == $USER->id && !\core\session\manager::is_loggedinas())) {
                self::enrol_user_to_mattermost_channel(
                    $mattermostmoduleinstance->mattermostid,
                    $mattermostmoduleinstance->channeladminroles,
                    $mattermostmoduleinstance->userroles,
                    $user->id,
                    $coursecontext->id
                );
            } else {
                $taskenrolment = new \mod_mattermost\task\enrol_user_to_mattermost_channel();
                $taskenrolment->set_custom_data(
                    array(
                        'mattermostid' => $mattermostmoduleinstance->mattermostid,
                        'channeladminroles' => $mattermostmoduleinstance->channeladminroles,
                        'userroles' => $mattermostmoduleinstance->userroles,
                        'userid' => $user->id,
                        'coursecontextid' => $coursecontext->id
                    )
                );
                \core\task\manager::queue_adhoc_task($taskenrolment);
            }
        }
    }

    /**
     * Enrols all the users in all the groups inside a course to their
     * respective mattermost channels
     *
     * @param array $groups - Array of the groups inside the course
     * @param object $mattermostmoduleinstance
     * @param context_course $coursecontext - Context of the course
     * @param bool $background - whether the enrolment should be done with the use of adhoc tasks
     */
    public static function enrol_all_concerned_users_to_mattermost_channels_for_groups(
        $groups, $mattermostmoduleinstance, context_course $coursecontext, $background = false
    ) {
        global $DB;
        foreach ($groups as $group) {

            $groupmembers = $DB->get_records('groups_members', array('groupid' => $group->id), '', 'userid');
            $userids = array_keys($groupmembers);
            foreach ($userids as $userid) {
                if ($background) {
                    $taskenrolment = new \mod_mattermost\task\enrol_user_to_mattermost_channel();
                    $taskenrolment->set_custom_data(
                        array(
                            'mattermostid' => $group->channelid,
                            'channeladminroles' => $mattermostmoduleinstance->channeladminroles,
                            'userroles' => $mattermostmoduleinstance->userroles,
                            'userid' => $userid,
                            'coursecontextid' => $coursecontext->id
                        )
                    );
                    \core\task\manager::queue_adhoc_task($taskenrolment);
                } else {
                    self::enrol_user_to_mattermost_channel(
                        $group->channelid,
                        $mattermostmoduleinstance->channeladminroles,
                        $mattermostmoduleinstance->userroles,
                        $userid,
                        $coursecontext->id
                    );
                }
            }
        }
    }

    /**
     * Enrols a moodle user to a mattermost channel
     *
     * @param string $channelid - Mattermost channel id
     * @param string $channeladminroles
     * @param string $userroles
     * @param int $userid - Id of the moodle user
     * @param int $coursecontextid
     */
    public static function enrol_user_to_mattermost_channel(
        $channelid,
        $channeladminroles,
        $userroles,
        $userid,
        $coursecontextid
    ) {
        global $DB;
        $mattermostapimanager = new mattermost_api_manager();
        $user = $DB->get_record('user', array('id' => $userid));
        if ($user) {
            $channeladminroleids = array_filter(explode(',', $channeladminroles));
            $userroleids = array_filter(explode(',', $userroles));

            if (self::has_mattermost_channeladmin_role($channeladminroleids, $user, $coursecontextid)) {
                $mattermostapimanager->enrol_user_to_channel($channelid, $user, true);
            } else if (self::has_mattermost_user_role($userroleids, $user, $coursecontextid)) {
                $mattermostapimanager->enrol_user_to_channel($channelid, $user);
            }
        } else {
            debugging("enrol_user_to_mattermost_channel user $userid not exists");
        }
    }

    /**
     * Handles the role assignment for a user in a course
     *
     * @param int $courseid
     * @param int $roleid
     * @param object $moodleuser
     * @param \context $context
     */
    public static function role_assign($courseid, int $roleid, $moodleuser, $context) {
        $mattermostapimanager = array();
        $mattermostmoduleinstance = null;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course($courseid);
        } else {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course_module($context->instanceid);
        }
        if (!empty($mattermostmoduleinstance)) {
            $mattermostapimanager = new mattermost_api_manager();
        } else {
            return;
        }

        $channelids = self::get_mattermost_channelids_for_groups_with_user($mattermostmoduleinstance->course, $moodleuser->id);
        array_push($channelids, $mattermostmoduleinstance->mattermostid);

        foreach ($channelids as $channelid) {
            if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->channeladminroles)))) {
                $mattermostapimanager->enrol_user_to_channel($channelid, $moodleuser, true);
            } else if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->userroles)))) {
                $mattermostapimanager->enrol_user_to_channel($channelid, $moodleuser);
            }
        }
    }

    /**
     * Handles the role unassignment for a user in a course
     *
     * @param int $courseid
     * @param int $roleid
     * @param object $moodleuser
     * @param \context $context
     */
    public static function role_unassign($courseid, int $roleid, $moodleuser, $context) {
        $mattermostmoduleinstance = array();
        if ($context->contextlevel == CONTEXT_COURSE) {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course($courseid);
        } else {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course_module($context->instanceid);
        }
        if (!empty($mattermostmoduleinstance)) {
            $mattermostapimanager = new mattermost_api_manager();
        } else {
            return;
        }

        $channeladminroles = explode(',', $mattermostmoduleinstance->channeladminroles);
        $userroles = explode(',', $mattermostmoduleinstance->userroles);
        $hasotherchanneladminrole = false;
        $hasotheruserrole = false;
        $waschanneladmin = in_array($roleid, array_filter($channeladminroles));

        $channelids = self::get_mattermost_channelids_for_groups_with_user($mattermostmoduleinstance->course, $moodleuser->id);
        array_push($channelids, $mattermostmoduleinstance->mattermostid);

        // Has other channeladmin moodle roles?
        foreach ($channeladminroles as $channeladminrole) {
            if ($channeladminrole != $roleid) {
                if (user_has_role_assignment($moodleuser->id, $channeladminrole, $context->id)) {
                    $hasotherchanneladminrole = true;
                    break;
                }
            }
        }
        // Has other user moodle roles?
        foreach ($userroles as $userrole) {
            if ($userrole != $roleid) {
                if (user_has_role_assignment($moodleuser->id, $userrole, $context->id)) {
                    $hasotheruserrole = true;
                    break;
                }
            }
        }

        foreach ($channelids as $channelid) {
            if (in_array($roleid, array_filter($channeladminroles))) {
                if (!$hasotherchanneladminrole) {
                    $mattermostapimanager->update_role_in_channel($channelid, $moodleuser, false);
                }
            }

            if (!$hasotherchanneladminrole) {
                if (in_array($roleid, array_filter($userroles))) {
                    if (!$hasotheruserrole) {
                        $mattermostapimanager->unenrol_user_from_channel($channelid, $moodleuser);
                    }
                } else if ($waschanneladmin && !$hasotheruserrole) {
                    $mattermostapimanager->unenrol_user_from_channel($channelid, $moodleuser);
                }
            }
        }
    }

    /**
     * Given a mattermost module instance, finds all the enrolled members in the course and
     * synchronizes all the members with the corresponding Mattermost channel.
     *
     * @param object $mattermostmoduleinstance
     * @param bool $background - whether the synchronization should be done with the use of adhoc tasks
     */
    public static function synchronize_channel_members($mattermostmoduleinstance, $background = false) {
        global $DB;
        if (!is_object($mattermostmoduleinstance)) {
            $mattermostmoduleinstanceid = $mattermostmoduleinstance;
            $mattermostmoduleinstance = $DB->get_record('mattermost', array('mattermostid' => $mattermostmoduleinstance));
            if (!$mattermostmoduleinstance) {
                throw new moodle_exception('mminstancenotfounderror', 'mod_mattermost', '', $mattermostmoduleinstanceid);
            }
        }
        $courseid = $mattermostmoduleinstance->course;
        $coursecontext = context_course::instance($courseid);
        $moodlemembers = get_enrolled_users($coursecontext);
        $mattermostid = $mattermostmoduleinstance->mattermostid;

        $channeladminroles = $mattermostmoduleinstance->channeladminroles;
        $channeladminroleids = array_filter(explode(',', $channeladminroles));

        $userroles = $mattermostmoduleinstance->userroles;
        $userroleids = array_filter(explode(',', $userroles));

        if ($background) {
            $tasksynchronize = new \mod_mattermost\task\synchronize_channel();
            $tasksynchronize->set_custom_data(
                array(
                    'mattermostid' => $mattermostmoduleinstance->mattermostid,
                    'moodlemembers' => $moodlemembers,
                    'channeladminroleids' => $channeladminroleids,
                    'userroleids' => $userroleids,
                    'coursecontextid' => $coursecontext->id
                )
            );
            \core\task\manager::queue_adhoc_task($tasksynchronize);
        } else {
            self::synchronize_channel(
                $mattermostid, $moodlemembers, $channeladminroleids, $userroleids, $coursecontext
            );
        }

        $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseid));
        if (!$groups || !is_array($groups) || count($groups) == 0) {
            return;
        }

        foreach ($groups as $group) {
            $groupmembers = self::get_group_members_for_group($group->groupid);
            if ($background) {
                $tasksynchronize = new \mod_mattermost\task\synchronize_channel();
                $tasksynchronize->set_custom_data(
                    array(
                        'mattermostid' => $group->channelid,
                        'moodlemembers' => $groupmembers,
                        'channeladminroleids' => $channeladminroleids,
                        'userroleids' => $userroleids,
                        'coursecontextid' => $coursecontext->id
                    )
                );
                \core\task\manager::queue_adhoc_task($tasksynchronize);
            } else {
                self::synchronize_channel($group->channelid,
                    $groupmembers, $channeladminroleids, $userroleids, $coursecontext);
            }
        }
    }

    /**
     * Synchronizes all the members of a mattermost channel with the given moodle members
     *
     * @param  string $mattermostid - Mattermost channel id
     * @param  array $moodlemembers
     * @param  array $channeladminroleids
     * @param  array $userroleids
     * @param  context_course $coursecontext
     * @throws dml_exception
     */
    public static function synchronize_channel($mattermostid, $moodlemembers,
        $channeladminroleids, $userroleids, context_course $coursecontext
    ): void {
        $mattermostapimanager = new mattermost_api_manager();
        $mattermostmembers = $mattermostapimanager->get_enriched_channel_members(
            $mattermostid
        );

        foreach ($moodlemembers as $moodlemember) {
            $mattermostuser = self::synchronize_mattermost_user(
                $mattermostid,
                $coursecontext,
                $moodlemember,
                $channeladminroleids,
                $userroleids,
                $mattermostmembers
            );

            if (!empty($mattermostuser)) {
                unset($mattermostmembers[$mattermostuser['email']]);
            }
        }
        // Remove remaining Mattermost members no more enrolled in course.
        foreach ($mattermostmembers as $mattermostmember) {
            $mattermostapimanager->unenrol_user_from_channel($mattermostid, null, $mattermostmember);
        }
    }

    /**
     * Synchronizes a moodle user with respective roles with the Mattermost channel members
     *
     * @param  string $mattermostid - Mattermost channel id
     * @param  context_course $coursecontext
     * @param  mixed $moodleuser
     * @param  array $channeladminroleids
     * @param  array $userroleids
     * @param  array $mattermostmembers
     * @return array $mattermostuser
     */
    private static function synchronize_mattermost_user($mattermostid, $coursecontext, $moodleuser, $channeladminroleids,
        $userroleids, $mattermostmembers
    ) {
        $mattermostapimanager = new mattermost_api_manager();
        $moodleemail = $moodleuser->email;
        $mattermostuser = null;

        $haschanneladminrole = self::has_mattermost_channeladmin_role($channeladminroleids, $moodleuser, $coursecontext->id);
        $hasuserrole = self::has_mattermost_user_role($userroleids, $moodleuser, $coursecontext->id);
        if (array_key_exists($moodleemail, $mattermostmembers)) {
            $mattermostuser = $mattermostmembers[$moodleemail];
            if ($haschanneladminrole != $mattermostuser['is_channel_admin']) {
                if ($haschanneladminrole) {
                    $mattermostapimanager->update_role_in_channel($mattermostid, $moodleuser, true);
                } else {
                    $mattermostapimanager->update_role_in_channel($mattermostid, $moodleuser, false);
                }
            }
            if (!$haschanneladminrole && !$hasuserrole) {
                // Unenrol.
                $mattermostapimanager->unenrol_user_from_channel($mattermostid, $moodleuser);
            }
        } else {
            if ($haschanneladminrole) {
                $mattermostuser = $mattermostapimanager->enrol_user_to_channel($mattermostid, $moodleuser, true);
            } else if ($hasuserrole) {
                $mattermostuser = $mattermostapimanager->enrol_user_to_channel($mattermostid, $moodleuser);
            }
        }

        return $mattermostuser;
    }

    /**
     * Handles the logic for unenrolling a moodle user from all the Mattermost channels
     * that he/she is a member of.
     *
     * @param int $userid
     */
    public static function unenrol_user_everywhere($userid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        $mattermostapimanager = new mattermost_api_manager();
        if (!$user) {
            throw new moodle_exception('moodleusernotfounderror', 'mod_mattermost', '', $userid);
        }
        $courseenrolments = self::course_enrolments($userid);
        if ($DB->get_record('mattermostxusers', array('moodleuserid' => $userid))) {
            foreach ($courseenrolments as $courseenrolment) {
                $mattermostapimanager->unenrol_user_from_channel($courseenrolment->mattermostid, $user);

                $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseenrolment->courseid));
                if (!$groups || !is_array($groups) || count($groups) == 0) {
                    continue;
                }

                foreach ($groups as $group) {
                    $mattermostapimanager->unenrol_user_from_channel($group->channelid, $user);
                }
            }
        }
    }

    /**
     * Synchronizes the user enrollments for a Moodle user in all the Mattermost channels
     *
     * @param int $userid
     */
    public static function synchronize_user_enrolments($userid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        $mattermostapimanager = new mattermost_api_manager();

        if (!$user) {
            throw new moodle_exception('moodleusernotfounderror', 'mod_mattermost', '', $userid);
        }

        $courseenrolments = self::course_enrolments($userid);
        foreach ($courseenrolments as $courseenrolment) {
            $channeladminrolesids = array_filter(explode(',', $courseenrolment->channeladminroles));
            $userrolesids = array_filter(explode(',', $courseenrolment->userroles));
            $mattermostmembers = $mattermostapimanager->get_enriched_channel_members($courseenrolment->mattermostid);

            $coursecontext = context_course::instance($courseenrolment->courseid);
            self::synchronize_mattermost_member($courseenrolment->mattermostid,
                $coursecontext,
                $user, $channeladminrolesids, $userrolesids, $mattermostmembers);

            $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseenrolment->courseid));
            if (!$groups || !is_array($groups) || count($groups) == 0) {
                continue;
            }

            foreach ($groups as $group) {
                $mattermostmembers = $mattermostapimanager->get_enriched_channel_members($group->channelid);
                if (count($mattermostmembers) == 0) {
                    continue;
                }

                self::synchronize_mattermost_member($group->channelid,
                    $coursecontext,
                    $user, $channeladminrolesids, $userrolesids, $mattermostmembers);
            }
        }
    }

    /**
     * Updates a moodle user in Mattermost
     *
     * @param int $userid
     */
    public static function update_user($userid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        $mattermostuser = $DB->get_record('mattermostxusers', array('moodleuserid' => $userid));
        $mattermostapimanager = new mattermost_api_manager();
        $mattermostapimanager->update_user($user, $mattermostuser);
    }

    /**
     * Finds all the course enrolments for a moodle user
     *
     * @param int $userid
     * @return false|array $courseenrolments
     * @throws dml_exception
     */
    private static function course_enrolments($userid) {
        global $DB;
        $sql = 'select distinct mat.mattermostid, mat.channeladminroles, mat.userroles,'
            . ' cm.course as courseid from {course_modules} cm'
            . ' inner join {mattermost} mat on cm.instance=mat.id'
            . ' inner join {modules} m on m.id=cm.module inner join {enrol} e on e.courseid=cm.course'
            . ' inner join {user_enrolments} ue on ue.enrolid=e.id'
            . ' where m.name=:modulename and m.visible=1 and ue.userid=:userid and cm.visible=1';
        $courseenrolments = $DB->get_records_sql(
            $sql, array('userid' => $userid,
                'modulename' => 'mattermost')
        );
        return $courseenrolments;
    }

    /**
     * Unenrols a user from a Mattermost channel
     * 
     * @param string $channelid - Mattermost channel id
     * @param int $userid - Moodle id of the user
     */
    public static function unenrol_user_from_mattermost_channel($channelid, $userid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        $mattermostapimanager = new mattermost_api_manager();
        $mattermostapimanager->unenrol_user_from_channel($channelid, $user);
    }
}
