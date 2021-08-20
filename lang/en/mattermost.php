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
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mattermost';

$string['description'] = 'Module interfacing Mattermost and Moodle';
$string['modulename'] = 'Mattermost';
$string['modulenameplural'] = 'Mattermost';
$string['modulename_help'] = 'Adding this activity to a Moodle course will create a private channel in Mattermost and push Moodle users associated to this activity as members of this newly created channel.

The list of members will then be kept up to date.

It will be possible to access to this Mattermost channel directly from Moodle or through any Mattermost client.

Module restrictions through avaibility  are not available at the moment.';
$string['modulename_link'] = 'mod/mattermost';
$string['name'] = 'Instance name (in the course)';

$string['settings'] = 'Mattermost plugin settings';
$string['testconnection'] = 'Test connection to Mattermost';

$string['instanceurl'] = 'Mattermost instance url';
$string['instanceurl_desc'] = 'Mattermost instance URL (ex: https://mm.moodle.com)';
$string['secret'] = 'Mattermost webhook secret';
$string['secret_desc'] = 'Mattermost associated webhook secret';
$string['teamslugname'] = 'Mattermost team slug name';
$string['teamslugname_desc'] = 'Slug name for the Mattermost team with which Moodle will be integrated';
$string['authservice'] = 'Mattermost auth service';
$string['authservice_desc'] = 'Auth service used in Mattermost (one of "ldap" or "saml")';
$string['authdata'] = 'Mattermost auth data';
$string['authdata_desc'] = 'Service-specific authentication data, such as email address.';
$string['channelnametoformat'] = 'Formatted channel name';
$string['channelnametoformat_desc'] = 'String format {$a->parameter} is possible with the following parameters : moodleid, moodleshortname, moodlefullname, moduleid, modulemoodleid (unique whitin all your possible moodle), courseid, courseshortname, coursefullname';
$string['channelgroupnametoformat'] = 'Formatted channel group name';
$string['channelgroupnametoformat_desc'] = 'String format {$a->parameter} is possible with the following parameters : channelname, groupname';
$string['defaultchanneladminroles'] = 'Mattermost channel admins';
$string['channeladminroles'] = 'Moodle roles in course that will be Mattermost channel admins';
$string['defaultchanneladminroles_desc'] = 'Moodle roles in course that will be Mattermost channel admins';
$string['defaultuserroles'] = 'Mattermost users.';
$string['userroles'] = 'Moodle roles in course that will be Mattermost users (with normal user rights)';
$string['defaultuserroles_desc'] = 'Moodle roles in course that will be Mattermost users (with normal user rights)';
$string['create_user_account_if_not_exists'] = 'Create Mattermost user account';
$string['create_user_account_if_not_exists_desc'] = 'While enrolling user, create Mattermost corresponding user account(username), if it does not exist';
$string['validationchannelnameregex'] = 'Mattermost channel validation name regular expression to remove invalid characters';
$string['validationchannelnameregex_desc'] = 'Moodle will replace every unauthorized characters by _. This regexp is the exact negation of the Mattermost server one concerning channel name validation';
$string['background_enrolment_task'] = 'Pass user enrolment/unenrolments to remote Mattermost as background tasks.';
$string['background_enrolment_task_desc'] = 'This is to solve performance issue while enrolling large amounts of users.</br>This will prevent the enroller to wait on course enrolment page while enrolling/unenrolling large amount of users.</br>Choice of enrol cohort and flatfile, if enabled, are strongly advised';
$string['background_add_instance'] = 'Pass Mattermost enrolments to background task while creating a new module instance';
$string['background_add_instance_desc'] = 'This will prevent waiting of the module creation page';
$string['background_restore'] = 'Pass Mattermost enrolments to background task while duplicating a mattermost modules';
$string['background_restore_desc'] = 'This will prevent waiting while duplicating a Mattermost module';
$string['background_synchronize'] = 'Pass Mattermost enrolments to background task while synchronizing enrollees.';
$string['background_synchronize_desc'] = 'This occurs after a course or a Mattermost module is restored from recyclebin.';
$string['background_user_update'] = 'Pass Mattermost enrolments to background task while updating user informations such as activation/deactivation.';
$string['background_user_update_desc'] = 'Pass Mattermost enrolments to background task while updating user informations such as activation/deactivation.';

$string['errorintestwhileconnection'] = 'Error while testing connection';
$string['testerrorcode'] = 'Error code : {$a}';
$string['testerrormessage'] = 'Error message :</br>{$a}';
$string['connectiontestresult'] = 'Connection test result';
$string['connection-success'] = 'Connection succesfully established';

$string['displaysection'] = 'Display settings';
$string['rolessection'] = 'Roles definition settings';
$string['displaytype'] = 'Display type';
$string['displaynew'] = 'Display in new tab';
$string['displaypopup'] = 'Display in popup window';
$string['displaycurrent'] = 'Display in current tab';
$string['popupheight'] = 'Pop-up height';
$string['popupwidth'] = 'Pop-up width';

$string['channelcreationerror'] = "Error while creating Mattermost remote channel";
$string['mattermost:candefineroles'] = 'Can define roles to apply in Mattermost\'s private channels';
$string['joinmattermost'] = 'Join Mattermost session';
$string['mmchannelerror'] = 'Remote Mattermost channel can\'t be retrieved. Please contact your system administrator. Error code {$a}.';
$string['mattermost_nickname'] = '{$a->firstname} {$a->lastname}';
$string['mmusernotfounderror'] = 'Mattermost user not found in db.';
