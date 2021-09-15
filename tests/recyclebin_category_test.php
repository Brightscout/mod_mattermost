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
 * mod_mattermost recycle bin category testcases.
 *
 * @package   mod_mattermost
 * @copyright 2021 Brightscout <hello@brightscout.com>
 * @author    Abhishek Verma <abhishek.verma@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');


use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Class for recycle bin category testcases
 */
class mod_mattermost_recyclebin_category_testcase extends advanced_testcase{
    /**
     * @var stdClass user record
     */
    private $user;
    /**
     * @var stdClass mattermost activity record
     */
    private $mattermost;
    /**
     * @var stdClass course record
     */
    private $course;

    /**
     * A function to setup the test environment
     */
    protected function setUp() : void {
        global $CFG, $DB;
        parent::setUp();

        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('background_enrolment_task', '', 'mod_mattermost');
        set_config('background_add_instance', 0, 'mod_mattermost');

    }

    /**
     * Function to test course deletion with recyclebin enabled
     */
    public function test_course_deletion_with_recyclebin() {
        global $DB;
        set_config('recyclebin_patch', 1, 'mod_mattermost');

        // We want the category bin to be enabled.
        set_config('categorybinenable', 1, 'tool_recyclebin');
        set_config('categorybinexpiry', 1, 'tool_recyclebin');
        set_config('background_restore', 0, 'mod_mattermost');
        $this->set_up_moodle_datas();
        delete_course($this->course->id, false);
        ob_start(); // Prevent echo output for tests.
        phpunit_util::run_all_adhoc_tasks();
        ob_get_contents();
        ob_end_clean();
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $this->assertTrue($mattermostapimanager->is_channel_archived($this->mattermost->mattermostid));
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertNotEmpty($mattermostxrecyclebin);
        $mattermostrecord = $DB->get_record('mattermost', array('id' => $this->mattermost->id));
        $this->assertEmpty($mattermostrecord);
        $mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);
        phpunit_util::run_all_adhoc_tasks();

        // Empty recycle bin.
        ob_start();
        $task = new \tool_recyclebin\task\cleanup_category_bin();
        $task->execute();
        ob_get_contents();
        ob_end_clean();

        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);

        // Check if remote mattermost private channel exists.
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
    }

    /**
     * Function to test course deletion with recyclebin disabled
     */
    public function test_course_deletion_without_recyclebin() {
        global $DB;
        set_config('recyclebin_patch', 1, 'mod_mattermost');

        // We want the category bin to be enabled.
        set_config('categorybinenable', 0, 'tool_recyclebin');
        $this->set_up_moodle_datas();
        delete_course($this->course->id, false);

        // Now, run the course module deletion adhoc task.
        phpunit_util::run_all_adhoc_tasks();
        $mattermostrecord = $DB->get_record('mattermost', array('id' => $this->mattermost->id));
        $this->assertEmpty($mattermostrecord);
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);

        // Check if remote mattermost private channel exists.
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));

        // Delete mattermost test user.
        $mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);

    }

    /**
     * Function to test course restoration with recyclebin enabled
     */
    public function test_course_restoration_with_recyclebin() {
        global $DB;
        set_config('recyclebin_patch', 1, 'mod_mattermost');

        // We want the category bin to be enabled.
        set_config('categorybinenable', 1, 'tool_recyclebin');
        set_config('categorybinexpiry', 1, 'tool_recyclebin');
        set_config('background_restore', 0, 'mod_mattermost');
        $this->set_up_moodle_datas();
        delete_course($this->course->id, false);

        // Now, run the course module deletion adhoc task.
        phpunit_util::run_all_adhoc_tasks();
        $mattermostapimanager = new mattermost_api_manager();

        // Check if remote mattermost private channel exists and archived.
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $this->assertTrue($mattermostapimanager->is_channel_archived($this->mattermost->mattermostid));
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertNotEmpty($mattermostxrecyclebin);
        $mattermostrecord = $DB->get_record('mattermost', array('id' => $this->mattermost->id));
        $this->assertEmpty($mattermostrecord);

        // Restore from recycle bin.
        ob_start();
        $recyclebin = new \tool_recyclebin\category_bin($this->course->category);
        foreach ($recyclebin->get_items() as $item) {
            $recyclebin->restore_item($item);
        }
        ob_get_contents();
        ob_end_clean();
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);

        // Check if remote mattermost private channel exists.
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $this->assertFalse($mattermostapimanager->is_channel_archived($this->mattermost->mattermostid));
        $this->assertCount(1, $mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid));

        // Delete mattermost test user.
        $mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);
    }

    /**
     * Function to test course deletion with recyclebin enabled and without recyclebin patch applied
     */
    public function test_deletion_without_recyclebin_without_patch() {
        global $DB;
        set_config('recyclebin_patch', 0, 'mod_mattermost');

        // We want the category bin no to be enabled.
        set_config('categorybinenable', 0, 'tool_recyclebin');
        $this->set_up_moodle_datas();
        delete_course($this->course->id, false);

        // Now, run the course module deletion adhoc task.
        phpunit_util::run_all_adhoc_tasks();
        $mattermostrecord = $DB->get_record('mattermost', array('id' => $this->mattermost->id));
        $this->assertEmpty($mattermostrecord);
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);

        // Check if remote mattermost private channel is archived.
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);

    }

    /**
     * Function to test course restoration with recyclebin enabled and without recyclebin patch applied
     */
    public function test_restoration_with_recyclebin_without_patch() {
        global $DB;
        set_config('recyclebin_patch', 0, 'mod_mattermost');

        // We want the category bin to be enabled.
        set_config('categorybinenable', 1, 'tool_recyclebin');
        $this->set_up_moodle_datas();
        delete_course($this->course->id, false);

        // Now, run the course module deletion adhoc task.
        phpunit_util::run_all_adhoc_tasks();
        $mattermostapimanager = new mattermost_api_manager();

        // Check if remote mattermost private channel exists and is archived.
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $this->assertTrue($mattermostapimanager->is_channel_archived($this->mattermost->mattermostid));
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);
        $mattermostrecord = $DB->get_record('mattermost', array('id' => $this->mattermost->id));
        $this->assertEmpty($mattermostrecord);

        // Restore from recycle bin.
        ob_start();
        $recyclebin = new \tool_recyclebin\category_bin($this->course->category);
        foreach ($recyclebin->get_items() as $item) {
            $recyclebin->restore_item($item);
        }
        ob_get_contents();
        ob_end_clean();
        $mattermostxrecyclebin = $DB->get_record('mattermostxrecyclebin', array('mattermostid' => $this->mattermost->mattermostid));
        $this->assertEmpty($mattermostxrecyclebin);

        // Check if remote mattermost private channel exists.
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->is_channel_exists($this->mattermost->mattermostid));
        $this->assertTrue($mattermostapimanager->is_channel_archived($this->mattermost->mattermostid));

        // Delete mattermost test user.
        $mattermostapimanager->delete_mattermost_user($this->user, $this->mattermost->id);
    }

    /**
     * A function to initialize the variables, each time before performing a test
     */
    protected function set_up_moodle_datas() {
        global $DB;
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $username = 'moodleusertest' . time();
        $this->user = $generator->create_user(array('username' => $username, 'firstname' => $username, 'lastname' => $username));
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->user->id, $this->course->id, $student->id);

        // Set a channelname for tests.
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');

        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course);
        $this->mattermost = $generator->create_module('mattermost',
            array('course' => $this->course->id, 'name' => $channelname));
    }
}
