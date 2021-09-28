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
 * mod_mattermost rest api manager tests.
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/utils/testdata.php');
use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Class for mattermost api manager unit testcases
 */
class mod_mattermost_tools_unit_testcase extends advanced_testcase {
    /**
     * @var stdClass user record
     */
    private $student1;

    /**
     * @var stdClass user record
     */
    private $teacher1;

    /**
     * @var stdClass course record
     */
    private $course;

    /**
     * @var testing_data_generator
     */
    private $generator;

    /**
     * @var stdClass role record
     */
    private $studentrole;

    /**
     * @var stdClass role record
     */
    private $editingteacherrole;

    /**
     * A constant mattermost instance id to be used while enrolling/unenrolling users or other functions
     */
    const MATTERMOST_INSTANCE_ID = 1;

    /**
     * A function to setup the test environment and initialize the variables
     */
    public function setUp(): void {
        global $DB;
        parent::setUp();
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        $this->resetAfterTest();

        $domainmail = get_config('mod_mattermost', 'domainmail');
        if (!$this->generator) {
            $this->generator = $this->getDataGenerator();
            $this->course = $this->generator->create_course();
            $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
            $this->editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
            $studentusername1 = 'moodleuserteststudent1_'.time();
            $studentemail1 = $studentusername1 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $this->student1 = $this->generator->create_user(array('username' => $studentusername1,
                'firstname' => $studentusername1, 'lastname' => $studentusername1, 'email' => $studentemail1));
            $this->generator->enrol_user($this->student1->id, $this->course->id, $this->studentrole->id);
            $teacherusername1 = 'moodleusertestteachert1_'.time();
            $teacheremail1 = $teacherusername1 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $this->teacher1 = $this->generator->create_user(array('username' => $teacherusername1,
                'firstname' => $teacherusername1, 'lastname' => $teacherusername1, 'email' => $teacheremail1));
            $this->generator->enrol_user($this->teacher1->id, $this->course->id, $this->editingteacherrole->id);
        }
    }

    public function test_get_channel_link() {
        $apimanager = $this->createMock(mattermost_api_manager::class);
        $apimanager->method('get_instance_url')->willReturn('localhost');
        $apimanager->method('get_team_slugname')->willReturn('test');
        $mattermosttools = new mattermost_tools($apimanager);
        $link = $mattermosttools->get_channel_link(get_mattermost_id());
        $this->assertEquals('localhost/test/channels/'.get_mattermost_id(), $link);
    }

    /**
     * Function to get a mattermost module instance
     * containing this class property course info
     *
     * @return stdClass
     */
    public function get_mattermost_module_instance() {
        $mattermostmoduleinstance = new stdClass();
        $mattermostmoduleinstance->id = $this::MATTERMOST_INSTANCE_ID;
        $mattermostmoduleinstance->course = $this->course->id;
        $mattermostmoduleinstance->courseid = $this->course->id;
        $mattermostmoduleinstance->mattermostid = get_mattermost_id();
        $mattermostmoduleinstance->channeladminroles = strval($this->editingteacherrole->id);
        $mattermostmoduleinstance->userroles = strval($this->studentrole->id);
        $mattermostmoduleinstance->instance = $this::MATTERMOST_INSTANCE_ID;
        return $mattermostmoduleinstance;
    }

    public function test_enrol_all_concerned_users_to_mattermost_channel_for_course() {
        $apimanager = $this->createMock(mattermost_api_manager::class);

        $channelid = get_mattermost_id();
        $apimanager->expects($this->exactly(2))->method('enrol_user_to_channel')->withConsecutive([
            $this->equalTo($channelid),
            $this->equalTo($this->student1),
            $this->equalTo($this::MATTERMOST_INSTANCE_ID),
            $this->isEmpty()
        ], [
            $this->equalTo($channelid),
            $this->equalTo($this->teacher1),
            $this->equalTo($this::MATTERMOST_INSTANCE_ID),
            $this->isTrue()
        ]);

        $mattermosttools = new mattermost_tools($apimanager);
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        $mattermosttools->enrol_all_concerned_users_to_mattermost_channel_for_course($mattermostmoduleinstance);
        $this->assertDebuggingNotCalled();
    }

    /**
     * Data provider for role_assign which is being used without the @dataProvider
     * annotation because data providers can't access the class variables
     *
     * @return array
     */
    public function role_assign_provider(): array {
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        return [
            'role assigned is channel admin type' => [
                'user' => $this->student1,
                'roleid' => $this->editingteacherrole->id,
                'getapimanager' => function() use ($mattermostmoduleinstance) {
                    global $DB;
                    $DB = $this->createMock(get_class($DB));
                    $apimanager = $this->createMock(mattermost_api_manager::class);

                    $DB->expects($this->once())->method('get_record_sql')->with(
                        $this->isType('string'),
                        $this->equalTo(array('courseid' => $this->course->id, 'mattermost' => 'mattermost'))
                    )->willReturn($mattermostmoduleinstance);

                    $DB->expects($this->once())->method('get_records_sql')->with(
                        $this->isType('string'),
                        $this->equalTo(array(
                            'courseid' => $this->course->id,
                            'userid' => $this->student1->id,
                        ))
                    )->willReturn(array());

                    $channelid = get_mattermost_id();
                    $apimanager->expects($this->once())->method('enrol_user_to_channel')->with(
                        $this->equalTo($channelid),
                        $this->equalTo($this->student1),
                        $this->equalTo($mattermostmoduleinstance->id),
                        $this->isTrue()
                    );

                    return $apimanager;
                }
            ],
            'role assigned is student type' => [
                'user' => $this->teacher1,
                'roleid' => $this->studentrole->id,
                'getapimanager' => function() use ($mattermostmoduleinstance) {
                    global $DB;
                    $DB = $this->createMock(get_class($DB));
                    $apimanager = $this->createMock(mattermost_api_manager::class);

                    $DB->expects($this->once())->method('get_record_sql')->with(
                        $this->isType('string'),
                        $this->equalTo(array('courseid' => $this->course->id, 'mattermost' => 'mattermost'))
                    )->willReturn($mattermostmoduleinstance);

                    $DB->expects($this->once())->method('get_records_sql')->with(
                        $this->isType('string'),
                        $this->equalTo(array(
                        'courseid' => $this->course->id,
                        'userid' => $this->teacher1->id,
                        ))
                    )->willReturn(array());

                    $channelid = get_mattermost_id();
                    $apimanager->expects($this->once())->method('enrol_user_to_channel')->with(
                        $this->equalTo($channelid),
                        $this->equalTo($this->teacher1),
                        $this->equalTo($mattermostmoduleinstance->id),
                    );

                    return $apimanager;
                }
            ],
        ];
    }

    public function test_role_assign() {
        global $DB;
        $originaldb = $DB;
        $testdata = array_values($this->role_assign_provider());
        foreach ($testdata as $testcase) {
            $apimanager = $testcase['getapimanager']();
            $mattermosttools = new mattermost_tools($apimanager);
            $mattermosttools->role_assign($this->course->id, $testcase['roleid'],
                $testcase['user'], context_course::instance($this->course->id));
            $DB = $originaldb;
            $this->assertDebuggingNotCalled();
        }
    }

    /**
     * Data provider for synchronize_channel_members which is being used without the
     * @dataProvider annotation because data providers can't access the class variables
     *
     * @return array
     */
    public function synchronize_channel_members_provider(): array {
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        return [
            'no moodle user is present in Mattermost channel' => [
                'getapimanager' => function() use ($mattermostmoduleinstance) {
                    $apimanager = $this->createMock(mattermost_api_manager::class);
                    $channelid = get_mattermost_id();
                    $channelmembers = get_mattermost_channel_members(3);
                    $apimanager->method('get_enriched_channel_members')->with(
                        $this->equalTo($channelid)
                    )->willReturn(get_enriched_mattermost_channel_members($channelmembers));

                    $apimanager->expects($this->exactly(2))->method('enrol_user_to_channel')->withConsecutive([
                        $this->equalTo($channelid),
                        $this->equalTo($this->student1),
                        $this->equalTo($mattermostmoduleinstance->id),
                    ], [
                        $this->equalTo($channelid),
                        $this->equalTo($this->teacher1),
                        $this->equalTo($mattermostmoduleinstance->id),
                        $this->isTrue()
                    ]);

                    $apimanager->expects($this->exactly(count($channelmembers)))->method('unenrol_user_from_channel')->with(
                        $this->equalTo($channelid),
                        $this->isNull(),
                        $this->equalTo($mattermostmoduleinstance->id),
                        $this->logicalAnd($this->isType('array'), $this->arrayHasKey('email'))
                    );

                    return $apimanager;
                },
            ],
            'one moodle user is present in Mattermost with wrong member role' => [
                'getapimanager' => function() use ($mattermostmoduleinstance) {
                    $apimanager = $this->createMock(mattermost_api_manager::class);
                    $channelid = get_mattermost_id();
                    $channelmembers = get_mattermost_channel_members(2, array(
                        $this->teacher1->email
                    ));

                    $apimanager->method('get_enriched_channel_members')->with(
                        $this->equalTo($channelid)
                    )->willReturn(get_enriched_mattermost_channel_members($channelmembers));

                    $apimanager->expects($this->once())->method('update_role_in_channel')->with(
                        $this->equalTo($channelid),
                        $this->equalTo($this->teacher1),
                        $this->isTrue(),
                        $this->equalTo($mattermostmoduleinstance->id)
                    );

                    // One enrolled user is being returned from get_mattermost_channel_members so enrol_user_to_channel
                    // will be called only once.
                    $apimanager->expects($this->once())->method('enrol_user_to_channel')->with(
                        $this->equalTo($channelid),
                        $this->equalTo($this->student1),
                        $this->equalTo($mattermostmoduleinstance->id),
                    );

                    $apimanager->expects($this->exactly(2))->method('unenrol_user_from_channel')->with(
                        $this->equalTo($channelid),
                        $this->isNull(),
                        $this->equalTo($mattermostmoduleinstance->id),
                        $this->logicalAnd($this->isType('array'), $this->arrayHasKey('email'))
                    );

                    return $apimanager;
                },
            ],
            'both moodle users are present in Mattermost but one has wrong channel admin role' => [
                'getapimanager' => function() use ($mattermostmoduleinstance) {
                    $apimanager = $this->createMock(mattermost_api_manager::class);
                    $channelid = get_mattermost_id();
                    $channelmembers = get_mattermost_channel_members(0, array(
                        $this->teacher1->email, $this->student1->email
                    ), true);

                    $apimanager->method('get_enriched_channel_members')->with(
                        $this->equalTo($channelid)
                    )->willReturn(get_enriched_mattermost_channel_members($channelmembers));

                    $apimanager->expects($this->once())->method('update_role_in_channel')->with(
                        $this->equalTo($channelid),
                        $this->equalTo($this->student1),
                        $this->isFalse(),
                        $this->equalTo($mattermostmoduleinstance->id)
                    );

                    $apimanager->expects($this->never())->method('enrol_user_to_channel');
                    $apimanager->expects($this->never())->method('unenrol_user_from_channel');

                    return $apimanager;
                },
            ],
        ];
    }

    public function test_synchronize_channel_members() {
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        $testdata = array_values($this->synchronize_channel_members_provider());
        foreach ($testdata as $testcase) {
            $apimanager = $testcase['getapimanager']();
            $mattermosttools = new mattermost_tools($apimanager);
            $mattermosttools->synchronize_channel_members($mattermostmoduleinstance);
            $this->assertDebuggingNotCalled();
        }
    }

    public function test_unenrol_user_everywhere() {
        global $DB;
        $DB = $this->createMock(get_class($DB));
        $apimanager = $this->createMock(mattermost_api_manager::class);

        $DB->expects($this->once())->method('get_record')->with(
            $this->equalTo('user'),
            $this->equalTo(array('id' => $this->student1->id))
        )->willReturn($this->student1);

        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        $DB->expects($this->once())->method('get_records_sql')->with(
            $this->isType('string'),
            $this->equalTo(array(
                'userid' => $this->student1->id,
                'modulename' => 'mattermost'
            ))
        )->willReturn(array($mattermostmoduleinstance));

        $DB->expects($this->once())->method('record_exists')->with(
            $this->equalTo('mattermostxusers'),
            $this->equalTo(array(
                'moodleuserid' => $this->student1->id,
                'mattermostinstanceid' => $mattermostmoduleinstance->id
            ))
        )->willReturn(true);

        $group = new stdClass();
        $group->channelid = get_mattermost_id();
        $DB->expects($this->once())->method('get_records')->with(
            $this->equalTo('mattermostxgroups'),
            $this->equalTo(array('courseid' => $this->course->id))
        )->willReturn(array($group));

        $apimanager->expects($this->exactly(2))->method('unenrol_user_from_channel')->with(
            $this->equalTo($mattermostmoduleinstance->mattermostid),
            $this->equalTo($this->student1),
            $this->equalTo($mattermostmoduleinstance->id)
        );

        $mattermosttools = new mattermost_tools($apimanager);
        $mattermosttools->unenrol_user_everywhere($this->student1->id);
    }
}
