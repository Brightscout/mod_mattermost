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
 * Plugin administration pages are defined here.
 *
 * @package     mod_mattermost
 * @category    admin
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/enrollib.php');
// Make sure core is loaded.

// Redefine the H5P admin menu entry to be expandable.
$modmattermostfolder = new admin_category(
    'modmattermostfolder',
    new lang_string('pluginname', 'mod_mattermost'),
    $module->is_enabled() === false
);
// Add the Settings admin menu entry.
$ADMIN->add('modsettings', $modmattermostfolder);
$settings->visiblename = new lang_string('settings', 'mod_mattermost');
// Add the Libraries admin menu entry.
$ADMIN->add('modmattermostfolder', $settings);
$ADMIN->add('modmattermostfolder', new admin_externalpage(
    'mattermostconnectiontest',
    new lang_string('testconnection', 'mod_mattermost'),
    new moodle_url('/mod/mattermost/test_connection.php')
));

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_configtext('mod_mattermost/instanceurl',
            get_string('instanceurl', 'mod_mattermost'),
            get_string('instanceurl_desc', 'mod_mattermost'),
            null,
            PARAM_URL
        )
    );
    $settings->add(
        new admin_setting_configpasswordunmask('mod_mattermost/secret',
            get_string('secret', 'mod_mattermost'),
            get_string('secret_desc', 'mod_mattermost'),
            ''
        )
    );

    $authservices = array('LDAP' => 'ldap', 'SAML' => 'saml');
    $settings->add(
        new admin_setting_configselect('mod_mattermost/authservice',
            get_string('authservice', 'mod_mattermost'),
            get_string('authservice_desc', 'mod_mattermost'),
            $authservices['LDAP'],
            array_keys($authservices)
        )
    );

    $authdata = array('Email' => 'email', 'Username' => 'username');
    $settings->add(
        new admin_setting_configselect('mod_mattermost/authdata',
            get_string('authdata', 'mod_mattermost'),
            get_string('authdata_desc', 'mod_mattermost'),
            $authdata['Email'],
            array_keys($authdata)
        )
    );
    $settings->add(
        new admin_setting_configtext('mod_mattermost/channelnametoformat',
            get_string('channelnametoformat', 'mod_mattermost'),
            get_string('channelnametoformat_desc', 'mod_mattermost'),
            '{$a->moodleid}_{$a->courseshortname}_{$a->moduleid}'
        )
    );
    $settings->add(
        new admin_setting_configtext('mod_mattermost/channelgroupnametoformat',
            get_string('channelgroupnametoformat', 'mod_mattermost'),
            get_string('channelgroupnametoformat_desc', 'mod_mattermost'),
            '{$a->courseshortname}_{$a->groupname}'
        )
    );


    $rolesoptions = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINALANDSHORT, true);
    $editingteachers = get_archetype_roles('editingteacher');
    $student = get_archetype_roles('student');
    $settings->add(
        new admin_setting_configmultiselect('mod_mattermost/defaultchanneladminroles',
            get_string('defaultchanneladminroles', 'mod_mattermost'),
            get_string('defaultchanneladminroles_desc', 'mod_mattermost'),
            array_keys($editingteachers),
            $rolesoptions
        )
    );

    $settings->add(
        new admin_setting_configmultiselect('mod_mattermost/defaultuserroles',
            get_string('defaultuserroles', 'mod_mattermost'),
            get_string('defaultuserroles_desc', 'mod_mattermost'),
            array_keys($student),
            $rolesoptions
        )
    );

    $settings->add(
        new admin_setting_configcheckbox('mod_mattermost/create_user_account_if_not_exists',
            get_string('create_user_account_if_not_exists', 'mod_mattermost'),
            get_string('create_user_account_if_not_exists_desc', 'mod_mattermost'),
            1
        )
    );

    $settings->add(
        new admin_setting_configtext('mod_mattermost/validationgroupnameregex',
            get_string('validationgroupnameregex', 'mod_mattermost'),
            get_string('validationgroupnameregex_desc', 'mod_mattermost'),
            '/[^0-9a-zA-Z-_.]/'
        )
    );

    $settings->add(new admin_setting_configcheckbox('mod_mattermost/background_add_instance',
        get_string('background_add_instance', 'mod_mattermost'),
        get_string('background_add_instance_desc', 'mod_mattermost'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox('mod_mattermost/background_restore',
        get_string('background_restore', 'mod_mattermost'),
        get_string('background_restore_desc', 'mod_mattermost'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox('mod_mattermost/background_synchronize',
        get_string('background_synchronize', 'mod_mattermost'),
        get_string('background_synchronize_desc', 'mod_mattermost'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox('mod_mattermost/background_user_update',
        get_string('background_user_update', 'mod_mattermost'),
        get_string('background_user_update_desc', 'mod_mattermost'),
        1
    ));
}
// Prevent Moodle from adding settings block in standard location.
$settings = null;
