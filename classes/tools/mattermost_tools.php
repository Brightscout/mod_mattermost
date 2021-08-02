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
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\tools;

defined('MOODLE_INTERNAL') || die();

class mod_mattermost_tools {
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
}
