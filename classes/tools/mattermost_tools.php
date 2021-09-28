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
 * @copyright 2021 Brightscout <hello@brightscout.com>
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
     * @var mattermost_api_manager
     */
    private static $mattermostapimanager;

    /**
     * Constructor for mattermost_tools
     *
     * @param mattermost_api_manager $apimanager
     */
    public function __construct($apimanager) {
        $this::$mattermostapimanager = $apimanager;
    }

    /**
     * Function to initialize mattermost api manager if not initialized yet
     */
    public static function initialize_api_manager() {
        if (!self::$mattermostapimanager) {
            self::$mattermostapimanager = new mattermost_api_manager();
        }
    }

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
            $string = preg_replace('/{.*}/', '', $string);
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
    public static function sanitize_channelname($channelname) {
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
        self::initialize_api_manager();
        return self::$mattermostapimanager->get_instance_url() . '/'. self::$mattermostapimanager->get_team_slugname() .
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
     * Checks if recycle bin patch is installed in Moodle
     */
    public static function is_patch_installed() {
        return get_config('mod_mattermost', 'recyclebin_patch');
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
        $moduleinstance = $DB->get_record_sql($sql , array('courseid' => $courseid, 'mattermost' => 'mattermost'));
        return $moduleinstance;
    }

    /**
     * Fetches mattermost module instance from database with given course module id
     *
     * @param int $cmid Id of the course module
     * @return object mattermost module instance in the course
     */
    public static function get_mattermost_module_instance_from_course_module($cmid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.id=:cmid';
        $moduleinstance = $DB->get_record_sql($sql , array('cmid' => $cmid, 'mattermost' => 'mattermost'));
        return $moduleinstance;
    }

    /**
     * updates course bin id for mattermost group record
     *
     * @param int $binid - Bin Id of the deleted course module
     * @param int $courseid - Id of the course
     */
    public static function update_mattermost_group_record($binid, $courseid) {
        global $DB;
        $DB->execute("UPDATE {mattermostxgroups} SET binid=? WHERE courseid=?", array($binid, $courseid));
    }

    /**
     * updates category bin id of mattermost group record
     *
     * @param int $binid - Bin Id of the deleted course module
     * @param int $courseid - Id of the course
     */
    public static function update_category_bin_id_mattermost_group($binid, $courseid) {
        global $DB;
        $DB->execute("UPDATE {mattermostxgroups} SET categorybinid=? WHERE courseid=?", array($binid, $courseid));
    }

    /**
     * Fetches single mattermost module instance from database with given courseid
     *
     * @param object $courseid course info
     * @return object of mattermost module instance in the course
     */
    public static function get_mattermost_module_instance_from_course_module_using_course_id($courseid) {
        global $DB;

        $sql = 'select cm.id, cm.instance from {course_modules} cm inner join {modules} m on m.id=cm.module '
            .'where cm.course=:courseid and m.name=:modname';
        $mattermostmodule = $DB->get_record_sql($sql,
            array('courseid' => $courseid, 'modname' => 'mattermost'));
        return $mattermostmodule;
    }

    /**
     * Fetches mattermost channel ids corresponding to the moodle groups
     * inside a course which contain given user id as a group member
     *
     * @param int $courseid Id of the course
     * @param int $userid Id of the user which is a member in the groups
     * @return array all mattermost channel ids corresponding to the groups
     */
    public static function get_mattermost_channelids_for_groups_with_given_member($courseid, $userid) {
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
     * Fetches the group members' information.
     *
     * @param int $groupid - Id of the group
     * @return array all user infos which are members in given group
     */
    public static function get_group_members($groupid) {
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
        $users = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);
        foreach ($users as $user) {
            if (!$background || ($forcecreator && $user->id == $USER->id && !\core\session\manager::is_loggedinas())) {
                self::enrol_user_to_mattermost_channel(
                    $mattermostmoduleinstance->mattermostid,
                    $mattermostmoduleinstance->channeladminroles,
                    $mattermostmoduleinstance->userroles,
                    $user->id,
                    $coursecontext->id,
                    $mattermostmoduleinstance->id
                );
            } else {
                $taskenrolment = new \mod_mattermost\task\enrol_user_to_mattermost_channel();
                $taskenrolment->set_custom_data(
                    array(
                        'mattermostid' => $mattermostmoduleinstance->mattermostid,
                        'channeladminroles' => $mattermostmoduleinstance->channeladminroles,
                        'userroles' => $mattermostmoduleinstance->userroles,
                        'userid' => $user->id,
                        'coursecontextid' => $coursecontext->id,
                        'mattermostinstanceid' => $mattermostmoduleinstance->id,
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
                            'coursecontextid' => $coursecontext->id,
                            'mattermostinstanceid' => $mattermostmoduleinstance->id,
                        )
                    );
                    \core\task\manager::queue_adhoc_task($taskenrolment);
                } else {
                    self::enrol_user_to_mattermost_channel(
                        $group->channelid,
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
     * Enrols a moodle user to a mattermost channel
     *
     * @param string $channelid - Mattermost channel id
     * @param string $channeladminroles
     * @param string $userroles
     * @param int $userid - Id of the moodle user
     * @param int $coursecontextid
     * @param int $mattermostinstanceid
     */
    public static function enrol_user_to_mattermost_channel(
        $channelid,
        $channeladminroles,
        $userroles,
        $userid,
        $coursecontextid,
        $mattermostinstanceid
    ) {
        global $DB;
        self::initialize_api_manager();
        $user = $DB->get_record('user', array('id' => $userid));
        if ($user) {
            $channeladminroleids = array_filter(explode(',', $channeladminroles));
            $userroleids = array_filter(explode(',', $userroles));

            if (self::has_mattermost_channeladmin_role($channeladminroleids, $user, $coursecontextid)) {
                self::$mattermostapimanager->enrol_user_to_channel($channelid, $user, $mattermostinstanceid, true);
            } else if (self::has_mattermost_user_role($userroleids, $user, $coursecontextid)) {
                self::$mattermostapimanager->enrol_user_to_channel($channelid, $user, $mattermostinstanceid);
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
        $mattermostmoduleinstance = null;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course($courseid);
        } else {
            $mattermostmoduleinstance = self::get_mattermost_module_instance_from_course_module($context->instanceid);
        }
        if (empty($mattermostmoduleinstance)) {
            return;
        }
        self::initialize_api_manager();

        $channelids = self::get_mattermost_channelids_for_groups_with_given_member(
            $mattermostmoduleinstance->course, $moodleuser->id
        );
        array_push($channelids, $mattermostmoduleinstance->mattermostid);

        foreach ($channelids as $channelid) {
            if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->channeladminroles)))) {
                self::$mattermostapimanager->enrol_user_to_channel(
                    $channelid, $moodleuser, $mattermostmoduleinstance->instance, true
                );
            } else if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->userroles)))) {
                self::$mattermostapimanager->enrol_user_to_channel($channelid, $moodleuser, $mattermostmoduleinstance->instance);
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
        if (empty($mattermostmoduleinstance)) {
            return;
        }

        $channeladminroles = explode(',', $mattermostmoduleinstance->channeladminroles);
        $userroles = explode(',', $mattermostmoduleinstance->userroles);
        $hasotherchanneladminrole = false;
        $hasotheruserrole = false;
        $waschanneladmin = in_array($roleid, array_filter($channeladminroles));

        $channelids = self::get_mattermost_channelids_for_groups_with_given_member(
            $mattermostmoduleinstance->course, $moodleuser->id
        );
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

        self::initialize_api_manager();
        foreach ($channelids as $channelid) {
            if (in_array($roleid, array_filter($channeladminroles))) {
                if (!$hasotherchanneladminrole) {
                    self::$mattermostapimanager->update_role_in_channel(
                        $channelid, $moodleuser, false, $mattermostmoduleinstance->instance
                    );
                }
            }

            if (!$hasotherchanneladminrole) {
                if (in_array($roleid, array_filter($userroles))) {
                    if (!$hasotheruserrole) {
                        self::$mattermostapimanager->unenrol_user_from_channel(
                            $channelid, $moodleuser, $mattermostmoduleinstance->instance
                        );
                    }
                } else if ($waschanneladmin && !$hasotheruserrole) {
                    self::$mattermostapimanager->unenrol_user_from_channel(
                        $channelid, $moodleuser, $mattermostmoduleinstance->instance
                    );
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
        $moodlemembers = get_enrolled_users($coursecontext, '', 0, 'u.*', null, 0, 0, true);
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
                    'coursecontextid' => $coursecontext->id,
                    'mattermostinstanceid' => $mattermostmoduleinstance->id
                )
            );
            \core\task\manager::queue_adhoc_task($tasksynchronize);
        } else {
            self::synchronize_channel(
                $mattermostid, $moodlemembers, $channeladminroleids, $userroleids, $coursecontext, $mattermostmoduleinstance->id
            );
        }

        $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseid));
        if (!$groups || !is_array($groups) || count($groups) == 0) {
            return;
        }

        foreach ($groups as $group) {
            $groupmembers = self::get_group_members($group->groupid);
            $groupmembers = array_filter($groupmembers, function($groupmember) use ($moodlemembers) {
                return array_key_exists($groupmember->id, $moodlemembers);
            });
            if ($background) {
                $tasksynchronize = new \mod_mattermost\task\synchronize_channel();
                $tasksynchronize->set_custom_data(
                    array(
                        'mattermostid' => $group->channelid,
                        'moodlemembers' => $groupmembers,
                        'channeladminroleids' => $channeladminroleids,
                        'userroleids' => $userroleids,
                        'coursecontextid' => $coursecontext->id,
                        'mattermostinstanceid' => $mattermostmoduleinstance->id
                    )
                );
                \core\task\manager::queue_adhoc_task($tasksynchronize);
            } else {
                self::synchronize_channel($group->channelid,
                    $groupmembers, $channeladminroleids, $userroleids, $coursecontext, $mattermostmoduleinstance->id);
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
     * @param int $mattermostinstanceid
     * @throws dml_exception
     */
    public static function synchronize_channel($mattermostid, $moodlemembers,
        $channeladminroleids, $userroleids, context_course $coursecontext, $mattermostinstanceid
    ): void {
        self::initialize_api_manager();
        $mattermostmembers = self::$mattermostapimanager->get_enriched_channel_members(
            $mattermostid
        );

        foreach ($moodlemembers as $moodlemember) {
            $mattermostuser = self::synchronize_mattermost_user(
                $mattermostid,
                $coursecontext,
                $moodlemember,
                $channeladminroleids,
                $userroleids,
                $mattermostmembers,
                $mattermostinstanceid
            );

            if (!empty($mattermostuser)) {
                unset($mattermostmembers[$mattermostuser['email']]);
            }
        }
        // Remove remaining Mattermost members no more enrolled in course.
        foreach ($mattermostmembers as $mattermostmember) {
            self::$mattermostapimanager->unenrol_user_from_channel($mattermostid, null, $mattermostinstanceid, $mattermostmember);
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
     * @param int $mattermostinstanceid
     * @return array $mattermostuser
     */
    private static function synchronize_mattermost_user($mattermostid, $coursecontext, $moodleuser, $channeladminroleids,
        $userroleids, $mattermostmembers, $mattermostinstanceid
    ) {
        self::initialize_api_manager();
        $moodleemail = $moodleuser->email;
        $mattermostuser = null;

        $haschanneladminrole = self::has_mattermost_channeladmin_role($channeladminroleids, $moodleuser, $coursecontext->id);
        $hasuserrole = self::has_mattermost_user_role($userroleids, $moodleuser, $coursecontext->id);
        if (array_key_exists($moodleemail, $mattermostmembers)) {
            $mattermostuser = $mattermostmembers[$moodleemail];
            if ($haschanneladminrole != $mattermostuser['is_channel_admin']) {
                if ($haschanneladminrole) {
                    self::$mattermostapimanager->update_role_in_channel($mattermostid, $moodleuser, true, $mattermostinstanceid);
                } else {
                    self::$mattermostapimanager->update_role_in_channel($mattermostid, $moodleuser, false, $mattermostinstanceid);
                }
            }
            if (!$haschanneladminrole && !$hasuserrole) {
                // Unenrol.
                self::$mattermostapimanager->unenrol_user_from_channel($mattermostid, $moodleuser, $mattermostinstanceid);
            }
        } else {
            if ($haschanneladminrole) {
                $mattermostuser = self::$mattermostapimanager->enrol_user_to_channel(
                    $mattermostid, $moodleuser, $mattermostinstanceid, true
                );
            } else if ($hasuserrole) {
                $mattermostuser = self::$mattermostapimanager->enrol_user_to_channel(
                    $mattermostid, $moodleuser, $mattermostinstanceid
                );
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
        if (!$user) {
            throw new moodle_exception('moodleusernotfounderror', 'mod_mattermost', '', $userid);
        }
        $courseenrolments = self::course_enrolments($userid);
        self::initialize_api_manager();
        foreach ($courseenrolments as $courseenrolment) {
            if ($DB->record_exists('mattermostxusers', array(
                'moodleuserid' => $userid,
                'mattermostinstanceid' => $courseenrolment->id
            ))) {
                self::$mattermostapimanager->unenrol_user_from_channel($courseenrolment->mattermostid, $user, $courseenrolment->id);

                $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseenrolment->courseid));
                if (!$groups || !is_array($groups) || count($groups) == 0) {
                    continue;
                }

                foreach ($groups as $group) {
                    self::$mattermostapimanager->unenrol_user_from_channel($group->channelid, $user, $courseenrolment->id);
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
        if (!$user) {
            throw new moodle_exception('moodleusernotfounderror', 'mod_mattermost', '', $userid);
        }

        $courseenrolments = self::course_enrolments($userid);
        self::initialize_api_manager();
        foreach ($courseenrolments as $courseenrolment) {
            $channeladminrolesids = array_filter(explode(',', $courseenrolment->channeladminroles));
            $userrolesids = array_filter(explode(',', $courseenrolment->userroles));
            $mattermostmembers = self::$mattermostapimanager->get_enriched_channel_members($courseenrolment->mattermostid);

            $coursecontext = context_course::instance($courseenrolment->courseid);
            self::synchronize_mattermost_user($courseenrolment->mattermostid,
                $coursecontext,
                $user, $channeladminrolesids, $userrolesids, $mattermostmembers, $courseenrolment->id);

            $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseenrolment->courseid));
            if (!$groups || !is_array($groups) || count($groups) == 0) {
                continue;
            }

            foreach ($groups as $group) {
                $mattermostmembers = self::$mattermostapimanager->get_enriched_channel_members($group->channelid);
                if (count($mattermostmembers) == 0) {
                    continue;
                }

                self::synchronize_mattermost_user($group->channelid,
                    $coursecontext,
                    $user, $channeladminrolesids, $userrolesids, $mattermostmembers, $courseenrolment->id);
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
        self::initialize_api_manager();
        self::$mattermostapimanager->update_user($user, $mattermostuser);
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
        $sql = 'select distinct mat.id, mat.mattermostid, mat.channeladminroles, mat.userroles,'
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
     * @param int $mattermostinstanceid
     */
    public static function unenrol_user_from_mattermost_channel($channelid, $userid, $mattermostinstanceid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        self::initialize_api_manager();
        self::$mattermostapimanager->unenrol_user_from_channel($channelid, $user, $mattermostinstanceid);
    }

    /**
     * Synchronise course's group channel
     * to create Mattermost channel for groups that are newly created in a course
     * when course's Mattermost instance was in recyclebin
     *
     * @param int $courseid - Id of course
     */
    public static function synchronize_groups($courseid) {
        global $DB;
        $course = $DB->get_record('course', array('id' => $courseid));
        $groups = $DB->get_records('groups', array('courseid' => $courseid));

        self::initialize_api_manager();
        foreach ($groups as $group) {
            $mattermostgroup = $DB->get_record('mattermostxgroups', array('groupid' => $group->id));

            if (empty($mattermostgroup)) {
                $channelname = self::get_mattermost_channel_name_for_group($course, $group);
                [$result, $error] = self::$mattermostapimanager->call_mattermost_api(
                    array(self::$mattermostapimanager, 'create_mattermost_channel'),
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
                        self::$mattermostapimanager->create_mattermost_channel($channelname . '_' . time());
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
}
