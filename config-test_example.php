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
 * config unit test file
 *
 * @package   mod_mattermost
 * @category    config test file
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

set_config('instanceurl', 'https://mattermost-server_url', 'mod_mattermost');
set_config('secret', 'secret_from_the_mattermost_plugin', 'mod_mattermost');
set_config('teamslugname', 'your_team_on_mattermost', 'mod_mattermost');
set_config('authservice', '0', 'mod_mattermost');
set_config('authdata', '0', 'mod_mattermost');
// Fake config test to avoid email domain troubles.
set_config('domainmail', 'your_domain_mail_if_necessary', 'mod_mattermost'); // Optional argument line.
