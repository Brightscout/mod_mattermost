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
 * Data provider tests.
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\writer;
use mod_mattermost\privacy\provider;
use \core_privacy\local\request\userlist;
use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

require_once($CFG->libdir . '/tests/fixtures/events.php');

class mod_mattermost_privacy_testcase extends provider_testcase {

    private $course1;
    private $course2;
    private $mattermost1;
    private $mattermost2;
    private $mattermostcontext1;
    private $mattermostcontext2;
    private $userstudent;
    private $usereditingteacher;

    public function setUp() : void {
        $domainmail = get_config('mod_mattermost', 'domainmail');
        global $DB, $CFG;
        parent::setUp();
        set_config('background_enrolment_task', '', 'mod_mattermost');
        set_config('background_add_instance', 0, 'mod_mattermost');
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $this->course1 = $generator->create_course();
        $this->course2 = $generator->create_course();
        $studentusername = 'moodleusertest'.time();
        $studentemail = $studentusername . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $this->userstudent = $generator->create_user(array('username' => $studentusername,
        'firstname' => $studentusername, 'lastname' => $studentusername, 'email' => $studentemail));
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->userstudent->id, $this->course1->id, $student->id);
        $generator->enrol_user($this->userstudent->id, $this->course2->id, $student->id);
        $edititingteacherusername = 'moodleusertest'.(time() + 1);
        $edititingteacheremail = $edititingteacherusername . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $this->usereditingteacher = $generator->create_user(array('username' => $edititingteacherusername,
            'firstname' => $edititingteacherusername, 'lastname' => $edititingteacherusername, 'email' => $edititingteacheremail));
        $editingteacher = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($this->usereditingteacher->id, $this->course1->id, $editingteacher->id);
        $generator->enrol_user($this->usereditingteacher->id, $this->course2->id, $editingteacher->id);
        // Set a channelname for tests.
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');
        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course1);
        $this->mattermost1 = $generator->create_module('mattermost',
        array('course' => $this->course1->id, 'name' => $channelname));
        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(1, $this->course1);
        $this->mattermost2 = $generator->create_module('mattermost',
            array('course' => $this->course1->id, 'name' => $channelname));
        $this->mattermostcontext1 = context_module::instance($this->mattermost1->cmid);
        $this->mattermostcontext2 = context_module::instance($this->mattermost2->cmid);
    }

    public function tearDown() : void {
        ob_start();
        if (!empty($this->mattermost1)) {
            course_delete_module($this->mattermost1->cmid, true);
        }
        if (!empty($this->mattermost2)) {
            course_delete_module($this->mattermost2->cmid, true);
        }
        $mattermostmanager = new mattermost_api_manager();
        $mattermostmanager->delete_mattermost_user($this->userstudent, $this->mattermost1->id);
        $mattermostmanager->delete_mattermost_user($this->userstudent, $this->mattermost2->id);
        $mattermostmanager->delete_mattermost_user($this->usereditingteacher, $this->mattermost1->id);
        $mattermostmanager->delete_mattermost_user($this->usereditingteacher, $this->mattermost2->id);
        $mattermostmanager->archive_mattermost_channel($this->mattermost1->mattermostid);
        $mattermostmanager->archive_mattermost_channel($this->mattermost2->mattermostid);
        ob_get_contents();
        ob_end_clean();
        parent::tearDown();
    }

    /**
     * test get_users_in_context function
     */
    public function test_get_users_in_context() {
        // Setup in setUp function.
        $userlist = new userlist($this->mattermostcontext1, 'mod_mattermost');
        provider::get_users_in_context($userlist);
        $users = $userlist->get_users();
        $this->assertCount(2, $users);
        $this->assertTrue(in_array($this->usereditingteacher, $users));
        $this->assertTrue(in_array($this->userstudent, $users));
    }

    /**
     * Tets get_contexts_for_userid function.
     * Function that get the list of contexts that contain user information for the specified user.
     * @throws coding_exception
     */
    public function test_user_contextlist() {
        $contextlist = provider::get_contexts_for_userid($this->userstudent->id);
        $this->assertCount(2, $contextlist->get_contexts());
        $this->assertContains($this->mattermostcontext1, $contextlist->get_contexts());
        $this->assertContains($this->mattermostcontext2, $contextlist->get_contexts());
    }

    /**
     * Test export_all_data_for_user function.
     * funciton that export all data for a component for the specified user.
     * @throws coding_exception
     */
    public function test_export_user_data() {
        $approvedcontextlist = new approved_contextlist(
            $this->userstudent,
            'mod_mattermost',
            [$this->mattermostcontext1->id, $this->mattermostcontext2->id]
        );
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->mattermostcontext1);
        $data = $writer->get_data([get_string('pluginname', 'mod_mattermost'),
            get_string('datatransmittedtomm', 'mod_mattermost')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass', $data);
        $this->assertTrue(property_exists($data, 'transmitted_to_mattermost'));
        $this->assertInstanceOf('stdClass', $data->transmitted_to_mattermost);
        $this->assertEquals($this->userstudent->username, $data->transmitted_to_mattermost->username);
        $this->assertEquals($this->mattermost1->mattermostid, $data->transmitted_to_mattermost->mattermostid);

        writer::reset();
        provider::export_user_data($approvedcontextlist);
        $writer = writer::with_context($this->mattermostcontext2);
        $data = $writer->get_data([get_string('pluginname', 'mod_mattermost'),
            get_string('datatransmittedtomm', 'mod_mattermost')]);
        $this->assertTrue($writer->has_any_data());
        $this->assertInstanceOf('stdClass', $data);
        $this->assertTrue(property_exists($data, 'transmitted_to_mattermost'));
        $this->assertInstanceOf('stdClass', $data->transmitted_to_mattermost);
        $this->assertEquals($this->userstudent->username, $data->transmitted_to_mattermost->username);
        $this->assertEquals($this->mattermost2->mattermostid, $data->transmitted_to_mattermost->mattermostid);
    }
}
