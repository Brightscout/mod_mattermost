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
 * fixture file for some utilities
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to get a channel name for tests
 *
 * @return string
 */
function get_channel_name() {
    return 'moodletestchannel';
}

/**
 * Function to get a mattermost id which can be used
 * for both test channels or users
 *
 * @return string
 */
function get_mattermost_id() {
    return 'sfmq19kpztg5iy47ebe51hb31w';
}

/**
 * Function to get a mattermost user with id field
 *
 * @return array
 */
function get_mattermost_user() {
    return array(
        'id' => get_mattermost_id()
    );
}

/**
 * Function to get a list of mattermost channel members
 * with only email field
 *
 * @return array
 */
function get_mattermost_channel_members() {
    return [
        array('email' => 'abc@gmail.com'),
        array('email' => 'def@gmail.com'),
        array('email' => 'ghi@gmail.com'),
    ];
}
