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
 * upgrade file
 *
 * @package  mod_mattermost
 * @category upgrade
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author   Manoj <manoj@brightscout.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to upgrade the database if the plugin version is updated
 *
 * @param int $oldversion
 */
function xmldb_mattermost_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2021081800) {
        // Define table mattermostxusers to be created.
        $table = new xmldb_table('mattermostxusers');

        // Adding fields to table mattermostxusers.
        $table->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mattermostuserid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mattermostxusers.
        $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['moodleuserid'], 'user', ['id']);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['mattermostuserid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Mattermost savepoint reached.
        upgrade_mod_savepoint(true, 2021081800, 'mattermost');
    }

    if ($oldversion < 2021082000) {

        // Define table mattermostxgroups to be created.
        $table = new xmldb_table('mattermostxgroups');

        // Adding fields to table mattermostxgroups.
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('channelid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mattermostxgroups.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['channelid']);
        $table->add_key('fk_group', XMLDB_KEY_FOREIGN, ['groupid'], 'groups', ['id']);

        // Conditionally launch create table for mattermostxgroups.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Mattermost savepoint reached.
        upgrade_mod_savepoint(true, 2021082000, 'mattermost');
    }

    if ($oldversion < 2021082500) {

        // Define field courseid to be added to mattermostxgroups.
        $table = new xmldb_table('mattermostxgroups');
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'channelid');

        // Conditionally launch add field courseid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Mattermost savepoint reached.
        upgrade_mod_savepoint(true, 2021082500, 'mattermost');
    }

    if ($oldversion < 2021083100) {

        // Define table mattermostxusers to be dropped.
        $table = new xmldb_table('mattermostxusers');

        // Conditionally launch drop table for mattermostxusers.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

         // Adding fields to table mattermostxusers.
         $table->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
         $table->add_field('mattermostuserid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
         $table->add_field('mattermostinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

         // Adding keys to table mattermostxusers.
         $table->add_key('fk_user', XMLDB_KEY_FOREIGN, ['moodleuserid'], 'user', ['id']);
         $table->add_key('fk_instance', XMLDB_KEY_FOREIGN, ['mattermostinstanceid'], 'mattermost', ['id']);
         $table->add_key('primary', XMLDB_KEY_PRIMARY, ['moodleuserid', 'mattermostinstanceid']);

         // Conditionally launch create table for mattermostxusers.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Mattermost savepoint reached.
        upgrade_mod_savepoint(true, 2021083100, 'mattermost');
    }

    if ($oldversion < 2021090901) {

        // Define table mattermostxusers to be dropped.
        $usertable = new xmldb_table('mattermostxusers');

        // Conditionally launch drop table for mattermostxusers.
        if ($dbman->table_exists($usertable)) {
            $dbman->drop_table($usertable);
        }

         // Adding fields to table mattermostxusers.
        $usertable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $usertable->add_field('moodleuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $usertable->add_field('mattermostuserid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $usertable->add_field('mattermostinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table mattermostxusers.
        $usertable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $usertable->add_key('fk_user', XMLDB_KEY_FOREIGN, ['moodleuserid'], 'user', ['id']);
        $usertable->add_key('fk_instance', XMLDB_KEY_FOREIGN, ['mattermostinstanceid'], 'mattermost', ['id']);
        $usertable->add_key('unique', XMLDB_KEY_UNIQUE, ['moodleuserid', 'mattermostinstanceid']);

        // Conditionally launch create table for mattermostxusers.
        if (!$dbman->table_exists($usertable)) {
            $dbman->create_table($usertable);
        }

        // Define table mattermostxgroups to be dropped.
        $groupstable = new xmldb_table('mattermostxgroups');

        // Conditionally launch drop table for mattermostxgroups.
        if ($dbman->table_exists($groupstable)) {
            $dbman->drop_table($groupstable);
        }

        // Adding fields to table mattermostxgroups.
        $groupstable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $groupstable->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $groupstable->add_field('channelid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $groupstable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $groupstable->add_field('name', XMLDB_TYPE_CHAR, '254', null, XMLDB_NOTNULL);
        $groupstable->add_field('categorybinid', XMLDB_TYPE_INTEGER, '10');
        $groupstable->add_field('binid', XMLDB_TYPE_INTEGER, '10');

        // Adding keys to table mattermostxgroups.
        $groupstable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $groupstable->add_key('unique', XMLDB_KEY_UNIQUE, ['channelid']);
        $groupstable->add_key('fk_group', XMLDB_KEY_FOREIGN_UNIQUE, ['groupid'], 'groups', ['id']);

        // Conditionally launch create table for mattermostxgroups.
        if (!$dbman->table_exists($groupstable)) {
            $dbman->create_table($groupstable);
        }

        // Mattermost savepoint reached.
        upgrade_mod_savepoint(true, 2021090901, 'mattermost');
    }

    return true;
}
