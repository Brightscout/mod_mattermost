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
use Exception;

defined('MOODLE_INTERNAL') || die();

class mattermost_rest_client {
    
    const MATTERMOST_PLUGIN_ID = 'com.mattermost.moodle-sync';
    private $base_url;
    private $plugin_url;
    private $plugin_api_url;
    private $secret;

    public function __construct($instance_url, $secret) {
        $this->base_url = $instance_url;
        $this->plugin_url = $this->base_url.'/plugins/'.static::MATTERMOST_PLUGIN_ID;
        $this->plugin_api_url = $this->plugin_url.'/api/v1';
        $this->secret = $secret;
    }

    public function test_connection() {
        return $this->doPost($this->plugin_api_url.'/test');
    }

    private function doPost($url, $headers = []) {
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
        $response = $curl->post($url, $options);
        $info = $curl->get_info();

        if(!empty($info['http_code']) && $info['http_code'] != 200){
            $json_response = json_decode($response);
            debugging('Unexpected response from the Mattermost server, HTTP code:' . $info['http_code'], DEBUG_DEVELOPER);
            throw new Exception($json_response ? $json_response['error'] : $response, $info['http_code']);
        }
        return true;
    }

    private function add_secret_to_url($url) {
        return $url.'?secret='.$this->secret;
    }

 }
