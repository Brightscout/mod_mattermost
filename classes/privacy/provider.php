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
 * privacy provider file
 * @package   mod_mattermost
 * @category  event
 * @copyright 2020 Manoj <manoj@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_mattermost\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\context;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Provider class which implements the relevant metadata and request providers.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /**
     * Get the metadata to describe the type of data stored by the plugin.
     *
     * @param collection $collection
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_external_location_link(
            'mattermost.server',
            [
                'username' => 'privacy:metadata:mod_mattermost:mattermost_server:username',
                'firstname' => 'privacy:metadata:mod_mattermost:mattermost_server:firstname',
                'lastname' => 'privacy:metadata:mod_mattermost:mattermost_server:lastname',
                'email' => 'privacy:metadata:mod_mattermost:mattermost_server:email',
                'mattermostids' => 'privacy:metadata:mod_mattermost:mattermost_server:mattermostids',
            ],
            'privacy:metadata:mod_mattermost:mattermost_server'
        );

        $collection->add_database_table(
            'mattermostxusers',
             [
                'moodleuserid' => 'privacy:metadata:mattermostxusers:moodleuserid',
                'mattermostuserid' => 'privacy:metadata:mattermostxusers:mattermostuserid',
             ],
            'privacy:metadata:mattermostxusers'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param   int           $userid       The user to search.
     * @return  contextlist   $contextlist  The list of contexts used in this plugin.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        global $DB;
        // Module context.
        $contextlist = new contextlist();

        $sql = 'select cm.id,ctx.id as contextid,mat.channeladminroles, mat.userroles'
            .' from {course_modules} cm inner join {modules} m on m.id=cm.module'
            .' inner join {mattermost} mat on mat.id=cm.instance'
            .' inner join {context} ctx on ctx.instanceid=cm.id and ctx.contextlevel=:contextlevel'
            . ' inner join {enrol} e on e.courseid=cm.course inner join  {user_enrolments} ue on ue.enrolid=e.id'
            .' where m.name=:modname and ue.userid=:userid';

        $params = array(
            'modname' => 'mattermost',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid
        );
        $records = $DB->get_records_sql($sql, $params);
        // Filter depending on role.
        $ctxids = array();
        foreach ($records as $record) {
            $roles = array();
            $roles = array_merge($roles, array_filter(explode(',', $record->channeladminroles)));
            $roles = array_merge($roles, array_filter(explode(',', $record->userroles)));
            foreach ($roles as $roleid) {
                if (user_has_role_assignment($userid, $roleid, $record->contextid )) {
                    $ctxids[$record->contextid] = $record->contextid;
                }
            }
        }
        // Fake request.
        if (count($ctxids) > 0) {
            list($insql, $inparams) = $DB->get_in_or_equal(array_values($ctxids), SQL_PARAMS_NAMED);
            $contextlist->add_from_sql('select distinct id from {context} where id '.$insql, $inparams);
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context instanceof \context_module) {
            // Check this is mattermost module.
            $mattermost = $DB->get_record_sql('select mat.* from {mattermost} mat'
                .' inner join {course_modules} cm on cm.instance=mat.id'
                .' inner join {modules} m on m.id=cm.module where cm.id=:cmid', array('cmid' => $context->instanceid));
            if ($mattermost) {
                list($channeladminrolesinsql, $channeladminrolesinparams) =
                    $DB->get_in_or_equal(array_filter(explode(',', $mattermost->channeladminroles)), SQL_PARAMS_NAMED);
                list($userrolesinsql, $userrolesinparams) =
                    $DB->get_in_or_equal(array_filter(explode(',', $mattermost->userroles)), SQL_PARAMS_NAMED);
                $sql = 'select ra.userid,cm.id,ctx.id as contextid,mat.channeladminroles, mat.userroles'
                    .' from {course_modules} cm inner join {modules} m on m.id=cm.module'
                    .' inner join {mattermost} mat on mat.id=cm.instance'
                    .' inner join {context} ctx on ctx.instanceid=cm.course and ctx.contextlevel=:contextcourse'
                    . ' inner join {enrol} e on e.courseid=cm.course inner join  {user_enrolments} ue on ue.enrolid=e.id'
                    . ' inner join {role_assignments} ra'
                    .' on ra.contextid=ctx.id and ra.userid=ue.userid'
                    .' and (ra.roleid '.$channeladminrolesinsql.' or ra.roleid '.$userrolesinsql.')'
                    .' where m.name=:modname and cm.id=:cmid';
                $params = $channeladminrolesinparams + $userrolesinparams + [
                    'contextcourse' => CONTEXT_COURSE,
                    'modname' => 'mattermost',
                    'cmid' => $context->instanceid
                ];
                $userlist->add_from_sql('userid', $sql, $params);
            }
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param   approved_contextlist    $contextlist    The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $contextlist->get_user()->id;
        $contexts = $contextlist->get_contexts();
        foreach ($contexts as $context) {
            if ($context instanceof \context_module) {
                $mattermost = $DB->get_record_sql('select mat.* from {mattermost} mat'
                    .' inner join {course_modules} cm on cm.instance=mat.id'
                    .' inner join {modules} m on m.id=cm.module where cm.id=:cmid', array('cmid' => $context->instanceid));
                if ($mattermost) {
                    list($channeladminrolesinsql, $channeladminrolesinparams) =
                        $DB->get_in_or_equal(array_filter(explode(',', $mattermost->channeladminroles)), SQL_PARAMS_NAMED);
                    list($userrolesinsql, $userrolesinparams) =
                        $DB->get_in_or_equal(array_filter(explode(',', $mattermost->userroles)), SQL_PARAMS_NAMED);
                    $sql = 'select distinct mat.mattermostid'
                        .' from {course_modules} cm inner join {modules} m on m.id=cm.module'
                        .' inner join {mattermost} mat on mat.id=cm.instance'
                        .' inner join {context} ctx on ctx.instanceid=cm.course and ctx.contextlevel=:contextcourse'
                        .' inner join {enrol} e on e.courseid=cm.course inner join {user_enrolments} ue on ue.enrolid=e.id'
                        .' inner join {role_assignments} ra'
                        .' on ra.contextid=ctx.id and ra.userid=ue.userid'
                        .' and (ra.roleid '.$channeladminrolesinsql.' or ra.roleid '.$userrolesinsql.')'
                        .' where m.name=:modname and cm.id=:cmid and ue.userid=:userid';
                    $params = $channeladminrolesinparams + $userrolesinparams + [
                        'contextcourse' => CONTEXT_COURSE,
                        'modname' => 'mattermost',
                        'cmid' => $context->instanceid,
                        'userid' => $userid
                    ];
                    $entry = $DB->get_record_sql($sql, $params);
                    $data = new \stdClass();
                    $data->id = $user->id;
                    $data->username = $user->username;
                    $data->firstname = $user->firstname;
                    $data->lastname = $user->lastname;
                    $data->email = $user->email;
                    $data->mattermostid = $entry->mattermostid;
                    writer::with_context($context)->export_data(
                        [
                            get_string('pluginname', 'mod_mattermost'),
                            get_string('datatransmittedtomm', 'mod_mattermost')
                        ],
                        (object)['transmitted_to_mattermost' => $data]
                    );
                }
            }
        }
    }

    /**
     * Delete all personal data for all users in the specified context.
     *
     * @param context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('mattermost', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('mattermostxusers', ['mattermostinstanceid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param   approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
            $DB->delete_records('mattermostxusers', ['mattermostinstanceid' => $instanceid, 'moodleuserid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $cm = $DB->get_record('course_modules', ['id' => $context->instanceid]);
        $mattermost = $DB->get_record('mattermost', ['id' => $cm->instance]);

        list($userinsql, $userinparams) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['mattermostinstanceid' => $mattermost->id], $userinparams);
        $sql = "mattermostinstanceid = :mattermostinstanceid AND userid {$userinsql}";

        $DB->delete_records_select('mattermostxusers', $sql, $params);
    }
}
