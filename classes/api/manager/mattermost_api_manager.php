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
 * Mattermost API manager class
 *
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\api\manager;

use Exception;
use mod_mattermost\client\mattermost_rest_client;

defined('MOODLE_INTERNAL') || die();

class mattermost_api_manager{
    const AUTHSERVICES = array('ldap', 'saml');
    const AUTHDATA = array('email', 'username');

    private $mattermostapiconfig;
    private $client;

    public function get_instance_url() {
        return $this->mattermostapiconfig->get_instanceurl();
    }

    public function get_team_slugname() {
        return $this->mattermostapiconfig->get_teamslugname();
    }

    public function __construct($instanceurl = null, $secret = null) {
        $this->mattermostapiconfig = new mattermost_api_config();
        $this->client = new mattermost_rest_client(
            is_null($instanceurl) ? $this->mattermostapiconfig->get_instanceurl() : $instanceurl,
            is_null($secret) ? $this->mattermostapiconfig->get_secret() : $secret,
            $this->mattermostapiconfig->get_teamslugname()
        );
    }

    public function test_connection() {
        return $this->client->test_connection();
    }

    public function create_mattermost_channel($name) {
        try {
            return $this->client->create_channel($name);
        } catch (Exception $e) {
            self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
            // TODO: Find alternative for print_error
            print_error($e->getMessage());
        }
    }

    /**
     * @param $moodleuser
     * @param $e
     */
    public static function moodle_debugging_message($message, $e, $level = DEBUG_DEVELOPER) {
        if (!empty($message)) {
            debugging($message."\n"."Mattermost api Error ".$e->getCode()." : ".$e->getMessage(), $level);
        } else {
            debugging("Mattermost api Error ".$e->getCode()." : ".$e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    public function enrol_user_to_channel($channelid, $moodleuser, $ischanneladmin = false, &$user=null) {
        $createusermode = get_config('mod_mattermost', 'create_user_account_if_not_exists');
        try {
            $user = $this->client->get_user_by_email($moodleuser->email);
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "User $moodleuser->username doesn't already exist.",
                $e);
        }

        if ($createusermode && !$user) {
            try {
                $user = $this->create_user($moodleuser);
            } catch (Exception $e) {
                self::moodle_debugging_message(
                    "User $moodleuser->username was not succesfully created.",
                    $e);
            }
        }

        if (!$user) {
            return false;
        }

        try {
            $payload = array('user_id' => $user['id']);
            if ($ischanneladmin) {
                $payload['role'] = 'channel_admin';
            }
            $this->client->add_user_to_channel($channelid, $payload);
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username not added to remote Mattermost channel", $e);
        }
        return true;
    }

    public function create_user($moodleuser) {
        $mattermostuserinfos = array();
        
        $authservice = get_config('mod_mattermost', 'authservice');
        $mattermostuserinfos['auth_service'] = static::AUTHSERVICES[$authservice];

        $authdata = get_config('mod_mattermost', 'authdata');
        if ($authdata == 0) {
            $mattermostuserinfos['auth_data'] = $moodleuser->email;
        } else if ($authdata == 1) {
            $mattermostuserinfos['auth_data'] =  $moodleuser->username;
        }

        $mattermostuserinfos['nickname'] = get_string('mattermost_nickname', 'mod_mattermost', $moodleuser);
        $mattermostuserinfos['email'] = $moodleuser->email;
        $mattermostuserinfos['username'] = $moodleuser->username;
        $mattermostuserinfos['first_name'] = $moodleuser->firstname;
        $mattermostuserinfos['last_name'] = $moodleuser->lastname;
        $mattermostuserinfos['team_name'] = $this->get_team_slugname();


        return $this->client->create_user($mattermostuserinfos);
    }

    public function revoke_channeladmin_role_in_channel($channelid, $moodleuser) {
        try {
            $user = $this->client->get_user_by_email($moodleuser->email);
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "User $moodleuser->username doesn't already exist.",
                $e);
        }

        try {
            $payload = array(
                'user_id' => $user['id'],
                'role' => 'channel_user'
            );
            $this->client->update_channel_member_roles($channelid, $payload);
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username not added to remote Mattermost channel", $e);
        }
        return false;
    }

    public function unenrol_user_from_channel($channelid, $moodleuser) {
        try {
            $user = $this->client->get_user_by_email($moodleuser->email);
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "User $moodleuser->username doesn't already exist.",
                $e);
        }

        try {
            $this->client->remove_user_from_channel($channelid, $user['id']);
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username not added to remote Mattermost channel", $e);
        }
        return false;
    }
}
