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
 * mattermost api manager class
 *
 * @package     mod_mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\api\manager;

use mod_mattermost\client\mattermost_rest_client;

defined('MOODLE_INTERNAL') || die();

class mattermost_api_manager{
    private $mattermostapiconfig;
    private $client;

    public function get_instance_url() {
        return $this->mattermostapiconfig->get_instanceurl();
    }
    
    public function __construct($instanceurl = null, $secret = null) {
        $this->mattermostapiconfig = new mattermost_api_config();
        $this->client = new mattermost_rest_client(
            is_null($instanceurl) ? $this->mattermostapiconfig->get_instanceurl() : $instanceurl,
            is_null($secret) ? $this->mattermostapiconfig->get_secret() : $secret
        );
    }

    public function test_connection() {
        return $this->client->test_connection();
    }
}