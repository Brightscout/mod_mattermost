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
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

class mattermost_api_manager{
    const AUTHSERVICES = array('ldap', 'saml');
    const AUTHDATA = array('email', 'username');
    const MATTERMOST_CHANNEL_ADMIN_ROLE = "channel_admin";
    const MATTERMOST_CHANNEL_MEMBER_ROLE = "channel_user";

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
        global $DB;

        $mattermostuser = $DB->get_record('mattermostxusers', array('moodleuserid' => $moodleuser->id));
        // TODO: Look into adding this setting in the future
        // $createusermode = get_config('mod_mattermost', 'create_user_account_if_not_exists');
        
        try {
            $user = $this->get_or_create_user($moodleuser, $mattermostuser);
            file_put_contents('/var/www/html/moodle/log.txt', 'enrolled user '.print_r($user, true).PHP_EOL, FILE_APPEND);
            $DB->insert_record('mattermostxusers', array(
                'moodleuserid' => $moodleuser->id,
                'mattermostuserid' => $user['id'],
            ));
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "User $moodleuser->username was not succesfully created.",
                $e);
        }

        try {
            $payload = array('user_id' => $user['id']);
            if ($ischanneladmin) {
                $payload['role'] = static::MATTERMOST_CHANNEL_ADMIN_ROLE;
            }
            $this->client->add_user_to_channel($channelid, $payload);
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username not added to remote Mattermost channel", $e);
        }
        return $user;
    }

    public function get_or_create_user($moodleuser, $mattermostuser) {
        $mattermostuserinfo = array();
        
        $authservice = get_config('mod_mattermost', 'authservice');
        $mattermostuserinfo['auth_service'] = static::AUTHSERVICES[$authservice];

        $authdata = get_config('mod_mattermost', 'authdata');
        if ($authdata == 0) {
            $mattermostuserinfo['auth_data'] = $moodleuser->email;
        } else if ($authdata == 1) {
            $mattermostuserinfo['auth_data'] =  $moodleuser->username;
        }

        if($mattermostuser) {
            $mattermostuserinfo['id'] = $mattermostuser->mattermostuserid;
        }
        $mattermostuserinfo['nickname'] = get_string('mattermost_nickname', 'mod_mattermost', $moodleuser);
        $mattermostuserinfo['email'] = $moodleuser->email;
        $mattermostuserinfo['username'] = $moodleuser->username;
        $mattermostuserinfo['first_name'] = $moodleuser->firstname;
        $mattermostuserinfo['last_name'] = $moodleuser->lastname;
        $mattermostuserinfo['team_name'] = $this->get_team_slugname();


        return $this->client->get_or_create_user($mattermostuserinfo);
    }

    public function update_user($moodleuser, $mattermostuser) {
        if (!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost', '');
        }

        $mattermostuserinfo = array();
        $mattermostuserinfo['nickname'] = get_string('mattermost_nickname', 'mod_mattermost', $moodleuser);
        $mattermostuserinfo['email'] = $moodleuser->email;
        $mattermostuserinfo['username'] = $moodleuser->username;
        $mattermostuserinfo['first_name'] = $moodleuser->firstname;
        $mattermostuserinfo['last_name'] = $moodleuser->lastname;
        try {
            $this->client->update_mattermost_user($mattermostuser->mattermostuserid, $mattermostuserinfo);
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username could not be updated. Error: " . $e->getMessage(), $e, DEBUG_DEVELOPER);
        }
    }

    /** 
     * @param $channelid
     * @param $moodleuser
     * @param bool $updatetochanneladmin
    */
    public function update_role_in_channel($channelid, $moodleuser, $updatetochanneladmin) {
        global $DB;
    
        $mattermostuser = $DB->get_record('mattermostxusers', array('moodleuserid' => $moodleuser->id));
        if(!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost', '');
        }

        try {
            $payload = array(
                'user_id' => $mattermostuser->mattermostuserid,
                'role' => $updatetochanneladmin ? static::MATTERMOST_CHANNEL_ADMIN_ROLE : static::MATTERMOST_CHANNEL_MEMBER_ROLE,
            );
            $this->client->update_channel_member_roles($channelid, $payload);
        } catch (Exception $e) {
            self::moodle_debugging_message("Role not updated for user $moodleuser->username in remote Mattermost channel", $e);
        }
        return false;
    }

    public function unenrol_user_from_channel($channelid, $moodleuser, $mattermostchannelmember = null) {
        global $DB;
    
        if($moodleuser) {
            $mattermostuser = $DB->get_record('mattermostxusers', array('moodleuserid' => $moodleuser->id));
        }else if($mattermostchannelmember) {
            $mattermostuser = $DB->get_record('mattermostxusers', array(
                'mattermostuserid' => $mattermostchannelmember['user_id'] ?? $mattermostchannelmember['id']
            ));
        }

        if(!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost', '');
        }

        try {
            $this->client->remove_user_from_channel($channelid, $mattermostuser->mattermostuserid);
        } catch (Exception $e) {
            $username = $moodleuser ? $moodleuser->username : $mattermostchannelmember['username'];
            self::moodle_debugging_message("User $username not added to remote Mattermost channel", $e);
        }
        return false;
    }

    public function get_enriched_channel_members($channelid) {
        $enrichedmembers = array();
        $page = 0;
        $perpage = 60;
        
        try {
            do {
                $members = $this->client->get_channel_members($channelid, $page, $perpage);
                if ($members && is_array($members)) {
                    foreach ($members as $member) {
                        $enrichedmembers[$member['email']] = $member;
                    }
                }
                $page += 1;
            } while($members && is_array($members) && count($members) == $perpage);
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "Error while retrieving channel members",
                $e);
        }
        
        return $enrichedmembers;
    }
}
