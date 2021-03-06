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
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\api\manager;

use Exception;
use mod_mattermost\client\mattermost_rest_client;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Mattermost API magager class for calling the rest_client functions.
 */
class mattermost_api_manager
{
    /**
     * The auth services used for creating user in Mattermost
     */
    const AUTHSERVICES = array('ldap', 'saml');

    /**
     * The auth data used for creating a user in Mattermost
     */
    const AUTHDATA = array('email', 'username');

    /**
     * The string used to denote a channel admin role in Mattermost
     */
    const MATTERMOST_CHANNEL_ADMIN_ROLE = "channel_admin";

    /**
     * The string used to denote a channel member role in Mattermost
     */
    const MATTERMOST_CHANNEL_MEMBER_ROLE = "channel_user";

    /**
     * An object of the mattermost_api_config class for getting configuration
     * @var mattermost_api_config
     */
    private $mattermostapiconfig;

    /**
     * An object of the mattermost_rest_client class for calling the APIs
     * @var mattermost_rest_client
     */
    private $client;

    /**
     * Function for getting the Mattermost instance url
     * @return string
     */
    public function get_instance_url() {
        return $this->mattermostapiconfig->get_instanceurl();
    }

    /**
     * Function for getting the Mattermost rest api client
     * @return mattermost_rest_client
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Function for getting the Mattermost team slug name
     * @return string
     */
    public function get_team_slugname() {
        return $this->mattermostapiconfig->get_teamslugname();
    }

    /**
     * Constructor for the mattermost_api_manager class
     *
     * @param mattermost_rest_client $client
     */
    public function __construct($client = null) {
        $this->mattermostapiconfig = new mattermost_api_config();
        if ($client) {
            $this->client = $client;
        } else {
            $this->client = new mattermost_rest_client(
                $this->mattermostapiconfig->get_instanceurl(),
                $this->mattermostapiconfig->get_secret(),
                $this->mattermostapiconfig->get_teamslugname()
            );
        }
    }

    /**
     * API manager function for testing the connection to Mattermost.
     */
    public function test_connection() {
        return $this->client->test_connection();
    }

    /**
     * Function for creating a Mattermost channel
     *
     * @param string $name
     * @return string $channelid - Id of the mattermost channel created
     * @throws Exception
     */
    public function create_mattermost_channel($name) {
        try {
            return $this->client->create_channel($name);
        } catch (Exception $e) {
            self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
            if (!PHPUNIT_TEST) {
                throw new moodle_exception('mmchannelcreationerror', 'mod_mattermost', '', $e->getMessage());
            }
        }
    }

    /**
     * Returns error message based on error code
     *
     * @param Exception $error
     * @param string $message
     */
    public static function get_error_message($error, $message = null) {
        if ($error->getCode() == 404) {
            return "Not found ".$error->getCode()." : ".$error->getMessage();
        }

        if ($message) {
            return $message."\n"."Mattermost api Error ".$error->getCode()." : ".$error->getMessage();
        }

        return "Mattermost api Error ".$error->getCode()." : ".$error->getMessage();
    }

    /**
     * A function for debugging
     *
     * @param string $message
     * @param Exception $e
     * @param mixed $level - Debug level
     */
    public static function moodle_debugging_message($message, $e, $level = DEBUG_DEVELOPER) {
        debugging(self::get_error_message($e, $message), $level);
    }

    /**
     * Debug and returns error message and code
     *
     * @param Exception $error
     */
    public function get_error_message_and_code($error) {
        if (!$error) {
            return null;
        }
        // Debug error.
        self::moodle_debugging_message('', $error);

        // Return error message and code to be used at required place.
        $message = self::get_error_message($error);
        return array(
            'message' => $message,
            'code' => $error->getCode()
        );
    }

    /**
     * TODO: Modify this for all other Mattermost API calls
     *
     * Function to call Mattermost API
     * Returns response and error separately in an array
     *
     * @param callable $apifunction - API function to be called
     * @param array $params - params to be passed
     */
    public function call_mattermost_api(callable $apifunction, $params) {
        $result = null;
        $error = null;

        try {
            $result = $apifunction(...array_values($params));
        } catch (Exception $e) {
            $error = $e;
        }

        return array(
            $result,
            $this->get_error_message_and_code($error)
        );
    }

    /**
     * Archives Mattermost channel
     * also triggers archiving of all Mattermost channels corresponding to the groups inside the course
     *
     * @param string $id - Mattermost channel id
     * @param int $courseid - Course id
     *  exists only if groups are to be deleted along with parent channel
     */
    public function archive_mattermost_channel($id, $courseid = null) {
        if ($courseid) {
            $this->archive_mattermost_group_channels($courseid);
        }

        try {
            $this->client->archive_channel($id);
        } catch (Exception $e) {
            self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
        }
    }

    /**
     * Archives all the Mattermost group channels of a course
     * Archiving of group channels are done in two ways:
     *  1. using courseid, when instace visibility changes or restored from course bin
     *  2. using binid, when course is deleted and restored from category bin
     *
     * @param int $courseid - id of Moodle course whose groups are to be deleted
     */
    public function archive_mattermost_group_channels($courseid) {
        global $DB;

        $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseid));
        if (!empty($groups)) {
            foreach ($groups as $group) {
                try {
                    $this->client->archive_channel($group->channelid);
                } catch (Exception $e) {
                    self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * Unarchives Mattermost channel
     * also triggers unarchiving of all Mattermost channels corresponding to the groups inside the course
     *
     * @param string $id - Mattermost channel id
     * @param int $courseid - Id of course
     * @param int $categorybinid - Category bin Id of recycled group/course
     */
    public function unarchive_mattermost_channel($id, $courseid, $categorybinid) {
        $this->unarchive_mattermost_group_channels($courseid, $categorybinid);

        try {
            $this->client->unarchive_channel($id);
        } catch (Exception $e) {
            self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
        }
    }

    /**
     * Unarchives all the Mattermost group channels of a course
     *
     * @param int $courseid - id of Moodle course whose groups are to be restored
     * @param int $categorybinid - Category bin id of Moodle deleted course whose groups are to be restored
     */
    public function unarchive_mattermost_group_channels($courseid, $categorybinid) {
        global $DB;

        $groups = null;
        if ($courseid) {
            $groups = $DB->get_records('mattermostxgroups', array('courseid' => $courseid));
        } else if ($categorybinid) {
            $groups = $DB->get_records('mattermostxgroups', array('categorybinid' => $categorybinid));
        } else {
            return;
        }

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group) {
            try {
                $this->client->unarchive_channel($group->channelid);
            } catch (Exception $e) {
                self::moodle_debugging_message('', $e, DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Function for enrolling a user to a channel
     *
     * @param string $channelid - Mattermost channel id
     * @param object $moodleuser
     * @param int $mattermostinstanceid
     * @param bool $ischanneladmin - Whether the user to be enrolled as a channel admin or not
     * @return array $user
     */
    public function enrol_user_to_channel($channelid, $moodleuser, $mattermostinstanceid, $ischanneladmin = false) {
        global $DB;
        $mattermostuser = $DB->get_record('mattermostxusers', array(
            'moodleuserid' => $moodleuser->id,
            'mattermostinstanceid' => $mattermostinstanceid,
        ));

        $user = array();
        try {
            $user = $this->get_or_create_user($moodleuser, $mattermostuser);
            if (!$mattermostuser) {
                $DB->insert_record(
                    'mattermostxusers', array(
                        'moodleuserid' => $moodleuser->id,
                        'mattermostuserid' => $user['id'],
                        'mattermostinstanceid' => $mattermostinstanceid,
                    )
                );
            }
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "User $moodleuser->username was not succesfully created.",
                $e
            );
        }

        try {
            if (array_key_exists('id', $user)) {
                $payload = array('user_id' => $user['id']);
                if ($ischanneladmin) {
                    $payload['role'] = static::MATTERMOST_CHANNEL_ADMIN_ROLE;
                }
                $this->client->add_user_to_channel($channelid, $payload);
            }
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username not added to remote Mattermost channel", $e);
        }
        return $user;
    }

    /**
     * Function to create a user in Mattermost or get a user if it already exists.
     *
     * @param object $moodleuser
     * @param false|object $mattermostuser
     * @return array $user
     */
    public function get_or_create_user($moodleuser, $mattermostuser = null) {
        $mattermostuserinfo = array();

        $authservice = $this->mattermostapiconfig->get_authservice();
        $mattermostuserinfo['auth_service'] = static::AUTHSERVICES[$authservice];

        $authdata = $this->mattermostapiconfig->get_authdata();
        if ($authdata == 0) {
            $mattermostuserinfo['auth_data'] = $moodleuser->email;
        } else if ($authdata == 1) {
            $mattermostuserinfo['auth_data'] = $moodleuser->username;
        }

        if ($mattermostuser) {
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

    /**
     * Function to update a Mattermost user
     *
     * @param object $moodleuser
     * @param false|object $mattermostuser
     * @throws Exception
     */
    public function update_user($moodleuser, $mattermostuser) {
        if (!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost');
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
            self::moodle_debugging_message(
                "User $moodleuser->username could not be updated. Error: " .
                $e->getMessage(), $e, DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Function to delete a mattermost user
     *
     * @param object $moodleuser
     * @param int $mattermostinstanceid
     */
    public function delete_mattermost_user($moodleuser, $mattermostinstanceid) {
        global $DB;
        $mattermostuser = $DB->get_record('mattermostxusers', array(
            'moodleuserid' => $moodleuser->id,
            'mattermostinstanceid' => $mattermostinstanceid
        ));

        if (!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost');
        }

        try {
            $this->client->delete_mattermost_user($mattermostuser->mattermostuserid);
            $DB->delete_records_select(
                'mattermostxusers', 'moodleuserid = :userid AND mattermostinstanceid = :instanceid', array(
                'userid' => $moodleuser->id,
                'instanceid' => $mattermostinstanceid
            ));
        } catch (Exception $e) {
            self::moodle_debugging_message("User $moodleuser->username was not successfully deleted.", $e);
        }
    }

    /**
     * Function to update a user's role in Mattermost channel
     *
     * @param string $channelid - Mattermost channel id
     * @param object $moodleuser
     * @param bool $updatetochanneladmin - true if role is updated to channel admin, false if updated to channel member
     * @param int $mattermostinstanceid
     * @throws Exception
     */
    public function update_role_in_channel($channelid, $moodleuser, $updatetochanneladmin, $mattermostinstanceid) {
        global $DB;

        $mattermostuser = $DB->get_record('mattermostxusers', array(
            'moodleuserid' => $moodleuser->id,
            'mattermostinstanceid' => $mattermostinstanceid
        ));

        if (!$mattermostuser) {
            throw new moodle_exception('mmusernotfounderror', 'mod_mattermost');
        }

        try {
            $payload = array(
                'user_id' => $mattermostuser->mattermostuserid,
                'role' => $updatetochanneladmin ? static::MATTERMOST_CHANNEL_ADMIN_ROLE : static::MATTERMOST_CHANNEL_MEMBER_ROLE,
            );
            $this->client->update_channel_member_roles($channelid, $payload);
        } catch (Exception $e) {
            self::moodle_debugging_message("Error updating role for user $moodleuser->username in remote Mattermost channel", $e);
        }
    }

    /**
     * Function to unenrol a user from a Mattermost channel
     *
     * @param string $channelid - Mattermost channel id
     * @param object $moodleuser
     * @param int $mattermostinstanceid
     * @param array $mattermostchannelmember returned from the get channel members API
     * @throws Exception
     */
    public function unenrol_user_from_channel($channelid, $moodleuser, $mattermostinstanceid, $mattermostchannelmember = null) {
        global $DB;

        if ($moodleuser) {
            $mattermostuser = $DB->get_record('mattermostxusers', array(
                'moodleuserid' => $moodleuser->id,
                'mattermostinstanceid' => $mattermostinstanceid
            ));
            if (!$mattermostuser) {
                throw new moodle_exception('mmusernotfounderror', 'mod_mattermost');
            }

            $userid = $mattermostuser->mattermostuserid;
        } else if ($mattermostchannelmember) {
            $userid = $mattermostchannelmember['user_id'] ?? $mattermostchannelmember['id'];
        }

        try {
            $this->client->remove_user_from_channel($channelid, $userid);
        } catch (Exception $e) {
            $username = $moodleuser ? $moodleuser->username : $mattermostchannelmember['username'];
            self::moodle_debugging_message("User $username not added to remote Mattermost channel", $e);
        }
    }

    /**
     * Function to get members of a channel in Mattermost in the form of an array
     * where key is member's email and value is the member object|array
     *
     * @param string $channelid - Mattermost channel id
     * @return array $enrichedmembers
     */
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
            } while ($members && is_array($members) && count($members) == $perpage);
        } catch (Exception $e) {
            self::moodle_debugging_message(
                "Error while retrieving channel members",
                $e
            );
        }

        return $enrichedmembers;
    }

    /**
     * Checks if user exists
     *
     * @param string $username - username of user
     * @return bool
     */
    public function user_exists($username) {
        [$result] = $this->call_mattermost_api(
            array($this->client, 'get_user_by_username'),
            [$username]
        );

        if ($result && !empty($result)) {
            return true;
        }
        return false;
    }

    /**
     * Checks if channel is deleted/archived
     *
     * @param string $channelid - id of channel
     * @return object of channel
     */
    public function is_channel_archived($channelid) {
        [$result] = $this->call_mattermost_api(
            array($this->client, 'get_channel'),
            [$channelid]
        );

        if (
            $result &&
            !empty($result) &&
            $result['delete_at'] != 0
        ) {
            return true;
        }
        return false;
    }

    /**
     * Checks if channel exists
     *
     * @param string $channelid - id of channel
     * @return object of channel
     */
    public function channel_exists($channelid) {
        [$result] = $this->call_mattermost_api(
            array($this->client, 'get_channel'),
            [$channelid]
        );

        if ($result && !empty($result)) {
            return true;
        }
        return false;
    }
}
