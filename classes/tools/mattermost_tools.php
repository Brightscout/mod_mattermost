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

use lang_string;
use stdClass;
use \mod_mattermost\api\manager\mattermost_api_manager;
use mod_mattermost\client\mattermost_exception;

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

    public static function mattermost_channel_name($cmid, $course) {
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
}
