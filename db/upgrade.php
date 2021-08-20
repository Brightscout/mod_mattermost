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
 * @package     mod_mattermost
 * @category    upgrade
 * @author      Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
    
    return true;
}
