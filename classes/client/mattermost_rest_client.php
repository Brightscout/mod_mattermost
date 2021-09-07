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
 * mattermost rest-client class
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\client;

defined('MOODLE_INTERNAL') || die();

/**
 * A class for calling the Mattermost  APIs
 */
class mattermost_rest_client
{

    /**
     * Id of the Mattermost plugin
     */
    const MATTERMOST_PLUGIN_ID = 'com.mattermost.moodle-sync';

    /**
     * @var string
     */
    private $baseurl;

    /**
     * @var string
     */
    private $pluginurl;

    /**
     * @var string
     */
    private $pluginapiurl;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $teamslugname;

    /**
     * Constructor for the mattermost_rest_client class
     *
     * @param string $instanceurl - Mattermost instance url from the plugin config
     * @param string $secret - Mattermost secret from the plugin config
     * @param string $teamslugname - Mattermost team slug name from the plugin config
     */
    public function __construct($instanceurl, $secret, $teamslugname) {
        $this->baseurl = $instanceurl;
        $this->pluginurl = $this->baseurl . '/plugins/' . static::MATTERMOST_PLUGIN_ID;
        $this->pluginapiurl = $this->pluginurl . '/api/v1';
        $this->secret = $secret;
        $this->teamslugname = $teamslugname;
    }

    /**
     * Client function to test the connection to Mattermost
     *
     * @throws Exception
     */
    public function test_connection() {
        return $this->do_post($this->pluginapiurl . '/test');
    }

    /**
     * Client function to create a channel in Mattermost
     *
     * @param string $channelname - Name of the channel to be created in Mattermost
     * @return string $id - Id of the channel created in Mattermost
     * @throws Exception
     */
    public function create_channel($channelname) {
        $channel = $this->do_post(
            $this->pluginapiurl . '/channels',
            array(
                'name' => $channelname,
                'team_name' => $this->teamslugname
            )
        );
        return $channel['id'];
    }

    /**
     * Client function to get or create a user
     *
     * @param array $user - Details of the user to be created on Mattermost.
     * Can contain id, email, username, first_name, last_name, nickname, team_name, auth_service, auth_data
     * @throws Exception
     */
    public function get_or_create_user($user) {
        return $this->do_post(
            $this->pluginapiurl . '/users',
            $user
        );
    }

    /**
     * Archives/deletes the channel at Mattermost
     *
     * @param string $channelid
     */
    public function archive_channel($channelid) {
        return $this->do_delete($this->pluginapiurl . '/channels/' . $channelid);
    }

    /**
     * Client function to get a user by email from Mattermost
     *
     * @param string $email
     * @throws Exception
     */
    public function get_user_by_email($email) {
        return $this->do_get($this->pluginapiurl . '/users/' . $email);
    }

    /**
     * Client function to add a user to a channel
     *
     * @param string $channelid - Mattermost channel id
     * @param array $payload - contains user_id, role(optional)
     * @throws Exception
     */
    public function add_user_to_channel($channelid, $payload) {
        return $this->do_post($this->pluginapiurl . '/channels/' . $channelid . '/members', $payload);
    }

    /**
     * Client function to update a member's role in a channel in Mattermost
     *
     * @param string $channelid - Mattermost channel id
     * @param array $payload - contains user_id, role
     * @throws Exception
     */
    public function update_channel_member_roles($channelid, $payload) {
        return $this->do_patch($this->pluginapiurl . '/channels/' . $channelid . '/members/roles', $payload);
    }

    /**
     * Client function to remove a user from a Mattermost channel
     *
     * @param string $channelid - Mattermost channel id
     * @param string $userid - Id of the user in Mattermost
     * @throws Exception
     */
    public function remove_user_from_channel($channelid, $userid) {
        return $this->do_delete($this->pluginapiurl . '/channels/' . $channelid . '/members/'. $userid);
    }

    /**
     * Client function to get members of a channel in Mattermost
     *
     * @param string $channelid - Mattermost channel id
     * @param int $page - page number
     * @param int $perpage - how much members on one page
     * @throws Exception
     */
    public function get_channel_members($channelid, $page, $perpage) {
        return $this->do_get(
            $this->pluginapiurl . '/channels/' . $channelid . '/members',
            array(
                'page' => $page,
                'per_page' => $perpage
            )
        );
    }

    /**
     * Client function to update a user in Mattermost
     *
     * @param string $userid - Id of the user in Mattermost
     * @param array $payload - can contain email, username, first_name, last_name, nickname
     * @throws Exception
     */
    public function update_mattermost_user($userid, $payload) {
        return $this->do_patch($this->pluginapiurl . '/users/' . $userid, $payload);
    }

    /**
     * Client function to delete/deactivate a user in Mattermost
     *
     * @param string $userid - Id of the user in Mattermost
     * @throws Exception
     */
    public function delete_mattermost_user($userid) {
        return $this->do_delete($this->pluginapiurl . '/users/' . $userid);
    }

    /**
     * Client function which calls the endpoint with a GET method using curl
     *
     * @param string $url - The url of the API endpoint
     * @param array $params - The query params
     * @param array $headers - The request headers
     * @return mixed
     * @throws Exception
     */
    private function do_get($url, $params = [], $headers = []) {
        $curl = new \curl();

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                ...$headers
            ],
        ];

        $params['secret'] = $this->secret;
        $response = $curl->get($url, $params, $options);
        $info = $curl->get_info();

        if (!$this->success($info)) {
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new mattermost_exception($response, $info['http_code']);
        }

        $jsonresponse = json_decode($response, true);
        return $jsonresponse ? $jsonresponse : $response;
    }

    /**
     * Client function which calls the endpoint with a POST method using curl
     *
     * @param string $url - The url of the API endpoint
     * @param array $payload - The body of the request
     * @param array $headers - The request headers
     * @return mixed
     * @throws Exception
     */
    private function do_post($url, $payload = null, $headers = []) {
        $curl = new \curl();

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => array(
                'Content-Type: application/json',
                ...$headers
            ),
        ];

        $url = $this->add_secret_to_url($url);
        if ($payload) {
            $payload = json_encode($payload);
        }
        $response = $curl->post($url, $payload, $options);
        $info = $curl->get_info();

        if (!$this->success($info)) {
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new mattermost_exception($response, $info['http_code']);
        }

        $jsonresponse = json_decode($response, true);
        return $jsonresponse ? $jsonresponse : $response;
    }

    /**
     * Client function which calls the endpoint with a PATCH method using curl
     *
     * @param string $url - The url of the API endpoint
     * @param array $payload - The body of the request
     * @param array $headers - The request headers
     * @return mixed
     * @throws Exception
     */
    private function do_patch($url, $payload = null, $headers = []) {
        $curl = new \curl();

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => array(
                'Content-Type: application/json',
                ...$headers
            ),
        ];

        $url = $this->add_secret_to_url($url);
        if ($payload) {
            $payload = json_encode($payload);
        }
        $response = $curl->patch($url, $payload, $options);
        $info = $curl->get_info();

        if (!$this->success($info)) {
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new mattermost_exception($response, $info['http_code']);
        }

        $jsonresponse = json_decode($response, true);
        return $jsonresponse ? $jsonresponse : $response;
    }

    /**
     * Client function which calls the endpoint with a DELETE method using curl
     *
     * @param string $url - The url of the API endpoint
     * @param array $headers - The request headers
     * @return mixed
     * @throws Exception
     */
    private function do_delete($url, $headers = []) {
        $curl = new \curl();

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                ...$headers
            ],
        ];

        $url = $this->add_secret_to_url($url);
        $response = $curl->delete($url, null, $options);
        $info = $curl->get_info();

        if (!$this->success($info)) {
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new mattermost_exception($response, $info['http_code']);
        }

        $jsonresponse = json_decode($response, true);
        return $jsonresponse ? $jsonresponse : $response;
    }

    /**
     * Adds a query param 'secret' to the url. The value of the secret is the class variable $secret
     *
     * @param string $url - the url to which secret is to be added
     * @return string $url - the url after adding secret
     */
    private function add_secret_to_url($url) {
        $url .= (stripos($url, '?') !== false) ? '&' : '?';
        $url .= http_build_query(['secret' => $this->secret], '', '&');
        return $url;
    }

    /**
     * Determines if the request was successful or not
     *
     * @param mixed $info
     * @return bool true if http_code is between 200 and 300, false otherwise
     */
    private function success($info) {
        return !empty($info['http_code']) && $info['http_code'] >= 200 && $info['http_code'] < 300;
    }
}
