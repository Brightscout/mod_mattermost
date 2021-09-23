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
 * Generates a random string of specified length.
 *
 * @param int $length
 * @return string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $characterslength = strlen($characters);
    $randomstring = '';
    for ($i = 0; $i < $length; $i++) {
        $randomstring .= $characters[rand(0, $characterslength - 1)];
    }
    return $randomstring;
}

/**
 * Generates a random email.
 *
 * @return string random email
 */
function get_random_email() {
    return generate_random_string(3).'@gmail.com';
}

/**
 * Function to get a list of mattermost channel members
 * with only email field
 *
 * @param int $dummycount - Count of the dummy members required
 * @param array $emails to be included in the return value
 * @param bool $isrolechanneladmin - Role for the given emails
 * @return array
 */
function get_mattermost_channel_members($dummycount, $emails = array(), $isrolechanneladmin = false) {
    $members = array();
    foreach ($emails as $email) {
        $members[] = array(
            'email' => $email,
            'is_channel_admin' => $isrolechanneladmin,
        );
    }
    for ($i = 0; $i < $dummycount; $i++) {
        $members[] = array(
            'email' => get_random_email(),
            'is_channel_admin' => false
        );
    }

    return $members;
}

/**
 * Returns an array with email as key and mattermost member as value.
 *
 * @param array $mattermostmembers - Mattermost channel members
 * @return array - Array with email as key and mattermost member as value
 */
function get_enriched_mattermost_channel_members($mattermostmembers) {
    $enriched = array();
    foreach ($mattermostmembers as $mattermostmember) {
        $enriched[$mattermostmember['email']] = $mattermostmember;
    }
    return $enriched;
}
