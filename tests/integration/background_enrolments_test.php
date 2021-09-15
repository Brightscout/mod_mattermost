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
 * mod_mattermost background enrolments tests.
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
 * Class for background enrollments testcases
 */
class mod_mattermost_background_enrolments_testcase extends advanced_testcase{
    /**
     * @var mattermost_api_manager
     */
    private $mattermostapimanager;

    /**
     * @var stdClass course record
     */
    private $course;

    /**
     * @var stdClass user record
     */
    private $user;

    /**
     * @var stdClass user record
     */
    private $user2;

    /**
     * @var testing_data_generator
     */
    private $datagenerator;

    /**
     * @var stdClass mattermost activity record
     */
    private $mattermost;

    /**
     * @var stdClass role record
     */
    private $studentrole;

    /**
     * @var stdClass role record
     */
    private $editingteacherrole;

    /**
     * A function to setup the test environment and initialize the variables
     */
    public function setUp() : void {
        $domainmail = get_config('mod_mattermost', 'domainmail');
        global $DB;
        parent::setUp();
        $this->initiate_test_environment();
        $this->mattermostapimanager = new mattermost_api_manager();
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        $this->datagenerator = $this->getDataGenerator();
        $this->course = $this->datagenerator->create_course();
        $username = 'moodleusertest'.time();
        $email = $username . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $email2 = $username . '2@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $this->user = $this->datagenerator->create_user(
            array('username' => $username, 'firstname' => $username, 'lastname' => $username, 'email' => $email));
        $this->user2 = $this->datagenerator->create_user(
            array('username' => $username.'2', 'firstname' => $username.'2', 'lastname' => $username.'2', 'email' => $email2));
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');
    }

    /**
     * Function to tear down everything after all the tests are complete
     */
    protected function tearDown() : void {
        if (!empty($this->mattermost)) {
            course_delete_module($this->mattermost->cmid, true);
        }
        $this->mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);
        parent::tearDown();
    }


    /**
     * Test for user enrollment/unenrollment with no background task configuration
     */
    public function test_enrol_unenrol_user_no_background() {
        // No enrolment method in background.
        set_config('background_enrolment_task', '', 'mod_mattermost');
        $this->create_mattermost_module();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        $this->datagenerator->enrol_user($this->user->id, $this->course->id, $this->studentrole->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        $enrolmethod = 'manual';
        self::unenrol_user($enrolmethod, $this->course->id, $this->user->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
    }

    /**
     * Test for user enrollment/unenrollment with background configuration 'manual'
     */
    public function test_enrol_unenrol_user_manual_background() {
        set_config('background_enrolment_task', 'enrol_manual', 'mod_mattermost');
        $this->create_mattermost_module();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        $this->datagenerator->enrol_user($this->user->id, $this->course->id, $this->studentrole->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        // Need to trigger adhoc tasks to enrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        $enrolmethod = 'manual';
        self::unenrol_user($enrolmethod, $this->course->id, $this->user->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        // Need to trigger adhoc tasks to unenrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
    }

    /**
     * Test for user enrollment/unenrollment with background configuration 'cohort'
     */
    public function test_enrol_unenrol_user_cohort_background() {
        $this->create_mattermost_module();
        self::enable_cohort_enrolments();
        set_config('background_enrolment_task', 'enrol_cohort', 'mod_mattermost');
        $trace = new null_progress_trace();
        $cohort = $this->datagenerator->create_cohort(array('context' => context_system::instance()));
        $plugin = enrol_get_plugin('cohort');
        // Create a course.
        // Enable this enrol plugin for the course.
        $plugin->add_instance($this->course, array(
                'customint1' => $cohort->id,
                'roleid' => $this->studentrole->id)
        );
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        cohort_add_member($cohort->id, $this->user->id);
        enrol_cohort_sync($trace, $this->course->id);
        $this->datagenerator->enrol_user($this->user2->id, $this->course->id, $this->studentrole->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members); // User2
        // Need to trigger adhoc tasks to enrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(2, $members);
        $enrolmethod = 'cohort';
        self::unenrol_user($enrolmethod, $this->course->id, $this->user->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(2, $members);
        // Need to trigger adhoc tasks to unenrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        self::unenrol_user('manual', $this->course->id, $this->user2->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
    }

    /**
     * Test for user role changes with background configuration 'manual'
     */
    public function test_user_role_changes_override_module_context() {
        $this->create_mattermost_module();
        set_config('background_enrolment_task', 'enrol_manual', 'mod_mattermost');
        $modulecontext = context_module::instance($this->mattermost->cmid);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        $this->datagenerator->enrol_user($this->user->id, $this->course->id, $this->studentrole->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(0, $members);
        // Need to trigger adhoc tasks to enrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        // Assign editingteacher role.
        role_assign($this->editingteacherrole->id, $this->user->id, $modulecontext->id);
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $channeladmins = $this->filter_channel_admins($members);
        $this->assertCount(0, $channeladmins);
        // Trigger adhoc tasks.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $channeladmins = $this->filter_channel_admins($members);
        $this->assertCount(1, $channeladmins);
        $this->assertCount(1, $members);
        // Unassign editingteacher role in module context.
        role_unassign($this->editingteacherrole->id, $this->user->id, $modulecontext->id);
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $channeladmins = $this->filter_channel_admins($members);
        $this->assertCount(0, $channeladmins);
        $this->assertCount(1, $members);
    }

    /**
     * Test that all users get enrolled in the background but the currently logged in user doesn't
     * when the configuration for background_enrolment_task is set to enrol_manual
     */
    public function test_add_instance_enrol_user_manual_background_currentuser() {
        set_config('background_enrolment_task', 'enrol_manual', 'mod_mattermost');
        // Create a new mattermost instance after course enrolments.
        $this->datagenerator->enrol_user($this->user->id, $this->course->id, $this->editingteacherrole->id);
        $this->datagenerator->enrol_user($this->user2->id, $this->course->id, $this->studentrole->id);
        $this->setUser($this->user);
        $this->create_mattermost_module();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(1, $members);
        // Need to trigger adhoc tasks to enrol.
        phpunit_util::run_all_adhoc_tasks();
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(2, $members);
    }

    /**
     * Function for loading the test configuration
     */
    private function load_mattermost_test_config() {
        global $CFG;
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
    }

    /**
     * Function for initiating the test environment
     */
    private function initiate_test_environment() {
        $this->resetAfterTest(true);
        $this->load_mattermost_test_config();
    }

    /**
     * Function for unenrolling a user from a course
     *
     * @param string $enrolmethod
     * @param int $courseid
     * @param int $userid
     * @throws coding_exception
     */
    protected static function unenrol_user($enrolmethod, $courseid, $userid) {
        $enrol = enrol_get_plugin($enrolmethod);
        $enrolinstances = enrol_get_instances($courseid, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == $enrolmethod) {
                $instance = $courseenrolinstance;
                break;
            }
        }
        $enrol->unenrol_user($instance, $userid);
    }

    /**
     * Function for enabling cohort enrollments
     */
    protected static function enable_cohort_enrolments(): void {
        $enabled = enrol_get_plugins(true);
        $enabled['cohort'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Function for filtering the channel admins from mattermost channel members
     *
     * @param array $mattermostchannelmembers
     */
    private function filter_channel_admins($mattermostchannelmembers) {
        return array_filter($mattermostchannelmembers, function ($channelmember) {
            return $channelmember['is_channel_admin'];
        });
    }

    /**
     * Function for creating a mattermost module/instance
     */
    private function create_mattermost_module(): void {
        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course);
        $this->mattermost = $this->datagenerator->create_module('mattermost',
            array('course' => $this->course->id, 'name' => $channelname));
    }
}
