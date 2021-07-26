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
 * Plugin strings are defined here.
 *
 * @package     mod_mattermost
 * @category    string
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mattermost';

$string['modulename_link'] = 'mod/mattermost';
$string['name'] = 'Instance name (in the course)';

$string['settings'] = 'Mattermost plugin settings';
$string['testconnection'] = 'Test connection to Mattermost';

$string['instanceurl'] = 'Mattermost instance url';
$string['instanceurl_desc'] = 'Mattermost instance URL (ex: https://mm.moodle.com)';
$string['secret'] = 'Mattermost webhook secret';
$string['secret_desc'] = 'Mattermost associated webhook secret';
$string['authservice'] = 'Mattermost Auth service';
$string['authservice_desc'] = 'Auth service used in Mattermost (one of "ldap" or "saml")';
$string['authdata'] = 'Mattermost Auth data';
$string['authdata_desc'] = 'Service-specific authentication data, such as email address.';
$string['groupnametoformat'] = 'Formatted group name';
$string['groupnametoformat_desc'] = 'String format {$a->parameter} is possible with the following parameters : moodleid, moodleshortname, moodlefullname, moduleid, modulemoodleid (unique whitin all your possible moodle), courseid, courseshortname, coursefullname';
$string['create_user_account_if_not_exists'] = 'Create Mattermost user account';
$string['create_user_account_if_not_exists_desc'] = 'While enrolling user, create Mattermost corresponding user account(username), if it does not exist';
$string['validationgroupnameregex'] = 'Mattermost group validation name regular expression to remove invalid characters';
$string['validationgroupnameregex_desc'] = 'Moodle will replace every unauthorized caracters by _. This regexp is the exact negation of the Mattermost server one concerning group name validation';
$string['background_add_instance'] = 'Pass Mattermost enrolments to background task while creating a new module instance';
$string['background_add_instance_desc'] = 'This will prevent waiting of the module creation page';
$string['background_restore'] = 'Pass Mattermost enrolments to background task while dupplicating a mattermost modules';
$string['background_restore_desc'] = 'This will prevent waiting while duplicating a Mattermost module';
$string['background_synchronize'] = 'Pass Mattermost enrolments to background task while synchronizing enrollees.';
$string['background_synchronize_desc'] = 'This occurs after a course or a Mattermost module is restored from recyclebin.';
$string['background_user_update'] = 'Pass Mattermost enrolments to background task while updating user informations such as activation/deactivation.';
$string['background_user_update_desc'] = 'Pass Mattermost enrolments to background task while updating user informations such as activation/deactivation.';


