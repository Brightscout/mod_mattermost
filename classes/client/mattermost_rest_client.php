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
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\client;

defined('MOODLE_INTERNAL') || die();

class mattermost_rest_client {

    const MATTERMOST_PLUGIN_ID = 'com.mattermost.moodle-sync';
    private $baseurl;
    private $pluginurl;
    private $pluginapiurl;
    private $secret;
    private $teamslugname;

    public function __construct($instanceurl, $secret, $teamslugname) {
        $this->baseurl = $instanceurl;
        $this->pluginurl = $this->baseurl . '/plugins/' . static::MATTERMOST_PLUGIN_ID;
        $this->pluginapiurl = $this->pluginurl . '/api/v1';
        $this->secret = $secret;
        $this->teamslugname = $teamslugname;
    }

    public function test_connection() {
        return $this->doPost($this->pluginapiurl . '/test');
    }

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

    private function do_get($url, $headers = []) {
        $curl = new \curl();

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPHEADER' => [
                'Content-Type: application/json',
                ...$headers
            ],
        ];

        $params = ['secret' => $this->secret];
        $response = $curl->get($url, $params, $options);
        $info = $curl->get_info();

        if (!$this->success($info)) {
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new mattermost_exception($response, $info['http_code']);
        }

        return $response;
    }

    /**
     * @return mixed
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

    private function add_secret_to_url($url) {
        $url .= (stripos($url, '?') !== false) ? '&' : '?';
        $url .= http_build_query(['secret' => $this->secret], '', '&');
        return $url;
    }

    private function success($info) {
        return !empty($info['http_code']) && $info['http_code'] >= 200 && $info['http_code'] < 300;
    }
}
