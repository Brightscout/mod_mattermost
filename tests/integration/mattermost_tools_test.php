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
 * mod_mattermost_tools tests.
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Class for testcases of mattermost tools
 */
class mod_mattermost_tools_testcase extends advanced_testcase {
    /**
     * @var mattermost_api_manager
     */
    private $mattermostapimanager;

    /**
     * @var stdClass course record
     */
    private $course;

    /**
     * @var stdClass mattermost activity record
     */
    private $mattermost;

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
     * @var testing_data_generator
     */
    private $generator;

    /**
     * A function to setup the test environment
     */
    public function setUp() : void {
        global $DB;
        parent::setUp();
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        $this->initiate_test_environment();
    }

    /**
     * Function for loading the test configuration
     */
    private function load_mattermost_test_config() {
        global $CFG;
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
    }

    /**
     * Function to tear down everything after all the tests are complete
     */
    public function tearDown() : void {
        ob_start();
        if (!empty($this->mattermost)) {
            course_delete_module($this->mattermost->cmid, true);
        }
        $this->mattermostapimanager->delete_mattermost_user($this->student1, $this->mattermost->id);
        $this->mattermostapimanager->delete_mattermost_user($this->student2, $this->mattermost->id);
        $this->mattermostapimanager->delete_mattermost_user($this->student3, $this->mattermost->id);
        $this->mattermostapimanager->delete_mattermost_user($this->teacher1, $this->mattermost->id);
        $this->mattermostapimanager->delete_mattermost_user($this->teacher2, $this->mattermost->id);
        $this->mattermostapimanager->delete_mattermost_user($this->teacher3, $this->mattermost->id);
        $this->mattermostapimanager->archive_mattermost_channel($this->mattermost->mattermostid);
        ob_get_contents();
        ob_end_clean();
        parent::tearDown();
    }

    /**
     * Function for initiating the test environment and the variables
     */
    private function initiate_test_environment() {
        $domainmail = get_config('mod_mattermost', 'domainmail');
        global $DB;
        $this->resetAfterTest(true);
        $this->load_mattermost_test_config();
        $this->mattermostapimanager = new mattermost_api_manager();
        $this->setAdminUser();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->generator = $this->getDataGenerator();
        $this->course = $this->generator->create_course();
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
        $this->generator->enrol_user($this->student1->id, $this->course->id, $studentrole->id);
        $this->generator->enrol_user($this->student2->id, $this->course->id, $studentrole->id);
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
        $this->generator->enrol_user($this->teacher1->id, $this->course->id, $editingteacherrole->id);
        $this->generator->enrol_user($this->teacher2->id, $this->course->id, $editingteacherrole->id);
        // Set a channelname for tests.
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');

    }

    /**
     * Function for creating a mattermost module/instance
     */
    private function create_instance() {
        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course);
        $this->mattermost = $this->generator->create_module('mattermost',
            array('course' => $this->course->id, 'name' => $channelname));
    }

    /**
     * Test for synchronize channel members function
     */
    public function test_synchronize_channel_members() {
        set_config('background_add_instance', 0, 'mod_mattermost');
        $this->create_instance();
        $mattermostid = $this->mattermost->mattermostid;
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->check_mattermost_channel_members($mattermostmembers);
        // Manually enrol teacher3  and student3 to Mattermost.
        $this->mattermostapimanager->enrol_user_to_channel($mattermostid, $this->student3, $this->mattermost->id);
        $this->mattermostapimanager->enrol_user_to_channel($mattermostid, $this->teacher3, $this->mattermost->id, true);
        // Remove student2 and teacher2 from Mattermost.
        $this->mattermostapimanager->unenrol_user_from_channel($mattermostid, $this->student2, $this->mattermost->id);
        $this->mattermostapimanager->unenrol_user_from_channel($mattermostid, $this->teacher2, $this->mattermost->id);
        // Synchronize.
        mattermost_tools::synchronize_channel_members($this->mattermost);
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->check_mattermost_channel_members($mattermostmembers);
        // Play with channel admin status in Mattermost.
        $this->mattermostapimanager->update_role_in_channel($mattermostid, $this->student1, true, $this->mattermost->id);
        $this->mattermostapimanager->update_role_in_channel($mattermostid, $this->teacher1, false, $this->mattermost->id);
        // Synchronize.
        mattermost_tools::synchronize_channel_members($this->mattermost);
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->check_mattermost_channel_members($mattermostmembers);
    }

    /**
     * Test for synchronize channel members function with background enabled
     */
    public function test_synchronize_channel_members_with_background_task() {
        set_config('background_add_instance', 1, 'mod_mattermost');
        $this->create_instance();
        // Need to trigger adhoc tasks to enrol.
        phpunit_util::run_all_adhoc_tasks();
        $mattermostid = $this->mattermost->mattermostid;
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->check_mattermost_channel_members($mattermostmembers);
        // Manually enrol teacher3  and student3 to Rocket.Chat.
        $this->mattermostapimanager->enrol_user_to_channel($mattermostid, $this->student3, $this->mattermost->id);
        $this->mattermostapimanager->enrol_user_to_channel($mattermostid, $this->teacher3, $this->mattermost->id, true);
        // Remove student2 and teacher2 from Mattermost.
        $this->mattermostapimanager->unenrol_user_from_channel($mattermostid, $this->student2, $this->mattermost->id);
        $this->mattermostapimanager->unenrol_user_from_channel($mattermostid, $this->teacher2, $this->mattermost->id);
        // Synchronize in backgroud.
        mattermost_tools::synchronize_channel_members($this->mattermost, true);
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->assertCount(4, $mattermostmembers);
        $this->assertTrue(array_key_exists($this->student3->email, $mattermostmembers));
        $this->assertTrue(array_key_exists($this->teacher3->email, $mattermostmembers));
        $this->assertTrue($mattermostmembers[$this->teacher3->email]['is_channel_admin']);
        $this->assertFalse(array_key_exists($this->teacher2->email, $mattermostmembers));
        $this->assertFalse(array_key_exists($this->student2->email, $mattermostmembers));
        phpunit_util::run_all_adhoc_tasks();
        $mattermostmembers = $this->mattermostapimanager->get_enriched_channel_members($mattermostid);
        $this->check_mattermost_channel_members($mattermostmembers);
    }

    /**
     * Checks if the given array contains the users which were created in the course during setup.
     *
     * @param array $mattermostmembers
     */
    protected function check_mattermost_channel_members($mattermostmembers): void {
        $this->assertCount(4, $mattermostmembers);
        $this->assertTrue(array_key_exists($this->student1->email, $mattermostmembers));
        $this->assertFalse($mattermostmembers[$this->student1->email]['is_channel_admin']);
        $this->assertTrue(array_key_exists($this->student2->email, $mattermostmembers));
        $this->assertFalse($mattermostmembers[$this->student2->email]['is_channel_admin']);
        $this->assertTrue(array_key_exists($this->teacher1->email, $mattermostmembers));
        $this->assertTrue($mattermostmembers[$this->teacher1->email]['is_channel_admin']);
        $this->assertTrue(array_key_exists($this->teacher2->email, $mattermostmembers));
        $this->assertTrue($mattermostmembers[$this->teacher2->email]['is_channel_admin']);
    }

}
