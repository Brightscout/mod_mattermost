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
 * mattermost api config class
 *
 * @package   mod_mattermost
 * @copyright 2020 Manoj <manoj@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\api\manager;

use moodle_exception;

class mattermost_api_config
{
    private $instanceurl;
    private $secret;
    private $teamslugname;
    private $authservice;
    private $authdata;

    /**
     * @return string
     */
    public function get_instanceurl() {
        return $this->instanceurl;
    }

    /**
     * @return string
     */
    public function get_secret() {
        return $this->secret;
    }

    /**
     * @return string
     */
    public function get_teamslugname() {
        return $this->teamslugname;
    }

    /**
     * @return string
     */
    public function get_authservice() {
        return $this->authservice;
    }

    /**
     * @return string
     */
    public function get_authdata() {
        return $this->authdata;
    }

    public function __construct() {
        if (is_null($this->instanceurl)) {
            $config = get_config('mod_mattermost');
            if (empty($config->instanceurl)) {
                throw new moodle_exception('mminstanceurlmissingerror', 'mod_mattermost');
            }
            if (empty($config->secret)) {
                throw new moodle_exception('mmsecretmissingerror', 'mod_mattermost');
            }
            if (empty($config->teamslugname)) {
                throw new moodle_exception('mmteamslugnamemissingerror', 'mod_mattermost');
            }
            // TODO : Add checks for authservice and authdata.
            $this->instanceurl = $config->instanceurl;
            $this->secret = $config->secret;
            $this->teamslugname = $config->teamslugname;
            $this->authservice = $config->authservice;
            $this->authdata = $config->authdata;
        }
    }
}
