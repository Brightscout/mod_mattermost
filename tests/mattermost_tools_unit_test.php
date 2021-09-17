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
class mod_mattermost_tools_unit_testcase extends advanced_testcase
{
    /**
     * @var stdClass user record
     */
    private $student1;

    /**
     * @var stdClass user record
     */
    private $student2;

    /**
     * @var stdClass user record
     */
    private $student3;

    /**
     * @var stdClass user record
     */
    private $teacher1;

    /**
     * @var stdClass user record
     */
    private $teacher2;

    /**
     * @var stdClass user record
     */
    private $teacher3;

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
     * @var int count of users enrolled in the course
     */
    private $usersenrolled;

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
        // if (!$this->generator) {
            $this->generator = $this->getDataGenerator();
            $this->course = $this->generator->create_course();
            $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
            $this->editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
            $studentusername1 = 'moodleuserteststudent1_'.time();
            $studentusername2 = 'moodleuserteststudent2_'.time();
            $studentusername3 = 'moodleuserteststudent3_'.time();
            $studentemail1 = $studentusername1 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $studentemail2 = $studentusername2 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $studentemail3 = $studentusername3 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $this->student1 = $this->generator->create_user(array('username' => $studentusername1,
                'firstname' => $studentusername1, 'lastname' => $studentusername1, 'email' => $studentemail1));
            $this->student2 = $this->generator->create_user(array('username' => $studentusername2,
                'firstname' => $studentusername2, 'lastname' => $studentusername2, 'email' => $studentemail2));
            $this->student3 = $this->generator->create_user(array('username' => $studentusername3,
                'firstname' => $studentusername3, 'lastname' => $studentusername3, 'email' => $studentemail3));
            $this->generator->enrol_user($this->student1->id, $this->course->id, $this->studentrole->id);
            // $this->generator->enrol_user($this->student2->id, $this->course->id, $this->studentrole->id);
            $teacherusername1 = 'moodleusertestteachert1_'.time();
            $teacherusername2 = 'moodleusertestteachert2_'.time();
            $teacherusername3 = 'moodleusertestteachert3_'.time();
            $teacheremail1 = $teacherusername1 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $teacheremail2 = $teacherusername2 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $teacheremail3 = $teacherusername3 . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $this->teacher1 = $this->generator->create_user(array('username' => $teacherusername1,
                'firstname' => $teacherusername1, 'lastname' => $teacherusername1, 'email' => $teacheremail1));
            $this->teacher2 = $this->generator->create_user(array('username' => $teacherusername2,
                'firstname' => $teacherusername2, 'lastname' => $teacherusername2, 'email' => $teacheremail2));
            $this->teacher3 = $this->generator->create_user(array('username' => $teacherusername3,
                'firstname' => $teacherusername3, 'lastname' => $teacherusername3, 'email' => $teacheremail3));
            $this->generator->enrol_user($this->teacher1->id, $this->course->id, $this->editingteacherrole->id);
            // $this->generator->enrol_user($this->teacher2->id, $this->course->id, $this->editingteacherrole->id);
            $this->usersenrolled = 2;
        // }
    }

    public function test_get_channel_link() {
        $apimanager = $this->createMock(mattermost_api_manager::class);
        $apimanager->method('get_instance_url')->willReturn('localhost');
        $apimanager->method('get_team_slugname')->willReturn('test');
        $mattermosttools = new mattermost_tools($apimanager);
        $link = $mattermosttools->get_channel_link(get_mattermost_id());
        $this->assertEquals('localhost/test/channels/'.get_mattermost_id(), $link);
    }

    public function get_mattermost_module_instance() {
        $mattermostmoduleinstance = new stdClass();
        $mattermostmoduleinstance->id = $this::MATTERMOST_INSTANCE_ID;
        $mattermostmoduleinstance->course = $this->course->id;
        $mattermostmoduleinstance->mattermostid = get_mattermost_id();
        $mattermostmoduleinstance->channeladminroles = strval($this->editingteacherrole->id);
        $mattermostmoduleinstance->userroles = strval($this->studentrole->id);
        $mattermostmoduleinstance->instance = $this::MATTERMOST_INSTANCE_ID;
        return $mattermostmoduleinstance;
    }

    public function test_enrol_all_concerned_users_to_mattermost_channel_for_course() {
        $apimanager = $this->createMock(mattermost_api_manager::class);

        $channelid = get_mattermost_id();
        $apimanager->expects($this->exactly($this->usersenrolled))->method('enrol_user_to_channel')->with(
            $this->equalTo($channelid),
            $this->logicalOr($this->equalTo($this->student1), $this->equalTo($this->teacher1)),
            $this->equalTo($this::MATTERMOST_INSTANCE_ID),
            $this->logicalOr($this->isTrue(), $this->isEmpty())
        );

        $mattermosttools = new mattermost_tools($apimanager);
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        $mattermosttools->enrol_all_concerned_users_to_mattermost_channel_for_course($mattermostmoduleinstance);
        $this->assertDebuggingNotCalled();
    }

    public function role_assign_provider(): array {
        $mattermostmoduleinstance = $this->get_mattermost_module_instance();
        return [
            'role assigned is channel admin type' => [
                'user' => $this->student2,
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
                            'userid' => $this->student2->id,
                        ))
                    )->willReturn(array());

                    $channelid = get_mattermost_id();
                    $apimanager->expects($this->once())->method('enrol_user_to_channel')->with(
                        $this->equalTo($channelid),
                        $this->objectEquals($this->student2),
                        $this->equalTo($mattermostmoduleinstance->id),
                        $this->isTrue()
                    );

                    return $apimanager;
                }
            ],
            'role assigned is student type' => [
                'user' => $this->teacher2,
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
                        'userid' => $this->teacher2->id,
                        ))
                    )->willReturn([]);

                    $channelid = get_mattermost_id();
                    $apimanager->expects($this->once())->method('enrol_user_to_channel')->with(
                        $this->equalTo($channelid),
                        $this->objectEquals($this->teacher2),
                        $this->equalTo($mattermostmoduleinstance->id),
                    );

                    return $apimanager;
                }
            ],
        ];
    }

    public function test_role_assign() {
        $testdata = array_values($this->role_assign_provider());
        foreach ($testdata as $testcase) {
            $apimanager = $testcase['getapimanager']();
            $mattermosttools = new mattermost_tools($apimanager);
            $mattermosttools->role_assign($this->course->id, $testcase['roleid'],
                $testcase['user'], context_course::instance($this->course->id));
        }
    }
}
