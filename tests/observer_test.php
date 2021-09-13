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
 * mod_mattermost event observers test.
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
 * Class for channel and user delete testcases
 */
class mod_mattermost_observer_testcase extends advanced_testcase{
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
    private $userstudent;

    /**
     * @var stdClass user record
     */
    private $usereditingteacher;

    /**
     * @var mattermost_api_manager
     */
    private $mattermostapimanager;

    /**
     * A function to setup the test environment and initialize the variables
     */
    protected function setUp() : void {
        global $CFG, $DB;
        parent::setUp();
        set_config('recyclebin_patch', 1, 'mod_mattermost');
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
        $this->mattermostapimanager = new mattermost_api_manager();
        // Disable recyclebin.
        set_config('coursebinenable', 0, 'tool_recyclebin');
        set_config('background_enrolment_task', '', 'mod_mattermost');
        set_config('background_add_instance', 0, 'mod_mattermost');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $studentusername = 'moodleuser'.time();
        $this->userstudent = $generator->create_user(array('username' => $studentusername,
            'firstname' => $studentusername, 'lastname' => $studentusername));
        $student = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->userstudent->id, $this->course->id, $student->id);
        $edititingteacherusername = 'moodleusertest'.(time() + 1);
        $this->usereditingteacher = $generator->create_user(array('username' => $edititingteacherusername,
            'firstname' => $edititingteacherusername, 'lastname' => $edititingteacherusername));
        $editingteacher = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($this->usereditingteacher->id, $this->course->id, $editingteacher->id);
        // Set a channelname for tests.
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');

        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course);
        $this->mattermost = $generator->create_module('mattermost',
            array('course' => $this->course->id, 'name' => $channelname));
    }

    /**
     * Function to tear down everything after all the tests are complete
     */
    protected function tearDown() : void {
        ob_start();
        $this->mattermostapimanager->delete_mattermost_user($this->userstudent, $this->mattermost->id);
        ob_get_contents();
        ob_end_clean();
        parent::tearDown();
    }

    /**
     * Function to test if user is deleted
     */
    public function test_user_delete() {
        // Structure created in setUp.
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(2, $members);
        delete_user($this->userstudent);
        $this->assertTrue($this->mattermostapimanager->user_exists($this->userstudent->username));
        $this->assertCount(2, $members);
    }

    /**
     * Function to test if channel is deleted
     */
    public function test_module_delete() {
        // Structure created in setUp.
        $members = $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid);
        $this->assertCount(2, $members);
        course_delete_module($this->mattermost->cmid);
        $this->assertTrue($this->mattermostapimanager->user_exists($this->userstudent->username));
        $this->assertTrue($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
        $this->assertDebuggingCalledCount(0);
    }

    /**
     * Function to test if channel is archived when instance visibility changes
     */
    public function test_module_visibility() {
        // Structure created in setUp.
        list($course, $cm) = get_course_and_cm_from_cmid($this->mattermost->cmid);
        $this->assertFalse($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
        set_coursemodule_visible($this->mattermost->cmid, 0, 1);
        // Need to trigger event manually.
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $this->assertTrue($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
        set_coursemodule_visible($this->mattermost->cmid, 1, 1);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $this->assertFalse($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
        set_coursemodule_visible($this->mattermost->cmid, 0, 0);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $this->assertTrue($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
        set_coursemodule_visible($this->mattermost->cmid, 1, 1);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        set_coursemodule_visible($this->mattermost->cmid, 1, 0);
        \core\event\course_module_updated::create_from_cm($cm)->trigger();
        rebuild_course_cache($cm->course, true);
        $this->assertTrue($this->mattermostapimanager->channel_archived($this->mattermost->mattermostid));
    }
}
