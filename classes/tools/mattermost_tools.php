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
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\tools;

defined('MOODLE_INTERNAL') || die();

use context_course;
use lang_string;
use stdClass;
use \mod_mattermost\api\manager\mattermost_api_manager;

class mattermost_tools {
    /** Display new window */
    const DISPLAY_NEW = 1;
    /** Display in curent window */
    const DISPLAY_CURRENT = 2;
    /** Display in popup */
    const DISPLAY_POPUP = 3;

    /**
     * Construct display options.
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

    public static function get_mattermost_channel_name($cmid, $course) {
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

    public static function format_string($string, $a) {
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
        return $string;
    }

    /**
     * @param string $channelname
     * @return string|string[]|null
     * @throws dml_exception
     */
    public static function sanitize_channelname($channelname) {
        // Replace white spaces anyway.
        $channelname = preg_replace('/\/s/', '_', $channelname);
        $channelname =
            preg_replace(get_config('mod_mattermost', 'validationchannelnameregex'), '_', $channelname);
        if (empty($channelname)) {
            print_error('sanitized Mattermost channelname can\'t be empty');
        }
        return $channelname;
    }

    public static function get_channel_link($mattermostchannelid) {
        $mattermostmanager = new mattermost_api_manager();
        return $mattermostmanager->get_instance_url() . '/'. $mattermostmanager->get_team_slugname() .
            '/channels/' . $mattermostchannelid;
    }

    public static function mattermost_enabled() {

        global $DB;
        $module = $DB->get_record('modules', array('name' => 'mattermost'));
        if (!empty($module->visible)) {
            $config = get_config('mod_mattermost');
            if (!empty($config->instanceurl) && !empty($config->secret)
                && !empty($config->teamslugname)) {
                return true;
            }
        }
        return false;
    }

    public static function course_has_mattermost_module_instances($courseid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.course=:courseid';
        return $DB->record_exists_sql($sql , array('courseid' => $courseid, 'mattermost' => 'mattermost'));
    }

    public static function is_module_a_mattermost_instance($cmid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.id=:cmid';
        return $DB->record_exists_sql($sql , array('cmid' => $cmid, 'mattermost' => 'mattermost'));
    }

    public static function get_mattermost_module_instances_from_course($courseid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.course=:courseid';
        $moduleinstances = $DB->get_records_sql($sql , array('courseid' => $courseid, 'mattermost' => 'mattermost'));
        return $moduleinstances;
    }

    public static function get_mattermost_module_instances_from_course_module($cmid) {
        global $DB;
        $sql = 'select cm.*, mat.mattermostid, mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module inner join {mattermost} mat on mat.id=cm.instance '
            .'where m.name=:mattermost and cm.id=:cmid';
        $moduleinstances = $DB->get_records_sql($sql , array('cmid' => $cmid, 'mattermost' => 'mattermost'));
        return $moduleinstances;
    }

    /**
     * @param array $userroleids
     * @param $moodlemember
     * @param int $coursecontextid
     * @return array
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
     * @param array $channeladminroleids
     * @param $moodlemember
     * @param int $coursecontextid
     * @return array
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

    public static function enrol_all_concerned_users_to_mattermost_channel(
        $mattermostmoduleinstance,
        $background = false,
        $forcecreator = false
    ) {
        global $USER;
        $courseid = $mattermostmoduleinstance->course;
        $coursecontext = context_course::instance($courseid);
        $users = get_enrolled_users($coursecontext);
        foreach ($users as $user) {
            if (!$background || ($forcecreator && !\core\session\manager::is_loggedinas())) {
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
                \core\task\manager::clear_static_caches();
                \core\task\manager::queue_adhoc_task($taskenrolment);
            }
        }
    }

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
            } else if(self::has_mattermost_user_role($userroleids, $user, $coursecontextid)) {
                $mattermostapimanager->enrol_user_to_channel($channelid, $user);
            }
        } else {
            debugging("enrol_user_to_mattermost_channel user $userid not exists");
        }
    }

    /**
     * @param int $courseid
     * @param int $roleid
     * @param $moodleuser
     * @param \context $context
     */
    public static function role_assign($courseid, int $roleid, $moodleuser, $context) {
        $mattermostapimanager = array();
        $mattermostmoduleinstances = null;
        if ($context->contextlevel == CONTEXT_COURSE) {
            $mattermostmoduleinstances = self::get_mattermost_module_instances_from_course($courseid);
        } else {
            $mattermostmoduleinstances = self::get_mattermost_module_instances_from_course_module($context->instanceid);
        }
        if (!empty($mattermostmoduleinstances)) {
            $mattermostapimanager = new mattermost_api_manager();
        }
        foreach ($mattermostmoduleinstances as $mattermostmoduleinstance) {
            if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->channeladminroles)))) {
                $mattermostapimanager->enrol_user_to_channel($mattermostmoduleinstance->mattermostid,
                        $moodleuser, true);
            } else if (in_array($roleid, array_filter(explode(',', $mattermostmoduleinstance->userroles)))) {
                    $mattermostapimanager->enrol_user_to_channel($mattermostmoduleinstance->mattermostid, $moodleuser);
            }
        }
    }

    /**
     * @param int $courseid
     * @param int $roleid
     * @param $moodleuser
     * @param \context $context
     */
    public static function role_unassign($courseid, int $roleid, $moodleuser, $context) {
        $mattermostmoduleinstances = array();
        if ($context->contextlevel == CONTEXT_COURSE) {
            $mattermostmoduleinstances = self::get_mattermost_module_instances_from_course($courseid);
        } else {
            $mattermostmoduleinstances = self::get_mattermost_module_instances_from_course_module($context->instanceid);
        }
        if (!empty($mattermostmoduleinstances)) {
            $mattermostapimanager = new mattermost_api_manager();
        }
        foreach ($mattermostmoduleinstances as $mattermostmoduleinstance) {
            $channeladminroles = explode(',', $mattermostmoduleinstance->channeladminroles);
            $userroles = explode(',', $mattermostmoduleinstance->userroles);
            $hasotherchanneladminrole = false;
            $hasotheruserrole = false;
            $waschanneladmin = false;
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
            if (in_array($roleid, array_filter($channeladminroles))) {
                $waschanneladmin = true;
                if (!$hasotherchanneladminrole) {
                    $mattermostapimanager->revoke_channeladmin_role_in_channel($mattermostmoduleinstance->mattermostid, $moodleuser);
                }
            }
            
            if (!$hasotherchanneladminrole) {
                if (in_array($roleid, array_filter($userroles))) {
                    if (!$hasotheruserrole) {
                        $mattermostapimanager->unenrol_user_from_channel($mattermostmoduleinstance->mattermostid, $moodleuser);
                    }
                } else if ($waschanneladmin && !$hasotheruserrole) {
                    $mattermostapimanager->unenrol_user_from_channel($mattermostmoduleinstance->mattermostid, $moodleuser);
                }
            }
        }
    }
}
