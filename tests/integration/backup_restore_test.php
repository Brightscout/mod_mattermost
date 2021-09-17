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
 * mod_mattermost backup restore test.
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
 * Class for backup and restore testcases
 */
class mod_mattermost_backup_restore_testcase extends advanced_testcase{
    /**
     * @var stdClass course record
     */
    private $course;
    /**
     * @var stdClass mattermost activity record
     */
    private $mattermost;
    /**
     * @var stdClass mattermost activity record after restoration from backup
     */
    private $newmattermost;
    /**
     * @var stdClass newmattermostmodule instance restored from backup
     */
    private $newmattermostmodule;
    /**
     * @var stdClass user record
     */
    private $studentrole;

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
        $this->studentrole = $generator->create_user(array('username' => $studentusername,
            'firstname' => $studentusername, 'lastname' => $studentusername));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->studentrole->id, $this->course->id, $studentrole->id);
        $edititingteacherusername = 'moodleusertest'.(time() + 1);
        $this->editingteacher = $generator->create_user(array('username' => $edititingteacherusername,
            'firstname' => $edititingteacherusername, 'lastname' => $edititingteacherusername));
        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($this->editingteacher->id, $this->course->id, $editingteacherrole->id);

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
        if (!empty($this->mattermost)) {
            course_delete_module($this->mattermost->cmid, true);
        }
        if (!empty($this->newmattermost)) {
            ob_start();
            ob_get_contents();
            ob_end_clean();
        }
        $mattermostapimanager = new mattermost_api_manager();
        $mattermostapimanager->delete_mattermost_user($this->studentrole, $this->mattermost->id);
        parent::tearDown();
    }

    /**
     * Function to test Mattermost channel restore from backup
     */
    public function test_backup_restore() {
        global $DB;
        // Backup course.
        set_config('background_restore', 0, 'mod_mattermost');
        $newcourseid = $this->backup_and_restore($this->course);
        $modules = get_coursemodules_in_course('mattermost', $newcourseid);
        $this->assertCount(1, $modules);
        $this->newmattermostmodule = array_pop($modules);
        $this->newmattermost = $DB->get_record('mattermost', array('id' => $this->newmattermostmodule->instance));

        // Initial Mattermost channel ID and restored channel ID should be same.
        $this->assertEquals($this->mattermost->mattermostid, $this->newmattermost->mattermostid);
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->channel_exists($this->newmattermost->mattermostid));
        $this->assertCount(2, $mattermostapimanager->get_enriched_channel_members($this->newmattermost->mattermostid));
    }

    /**
     * Function to test Mattermost channel restore from backup as a background task
     */
    public function test_backup_restore_with_background_task() {
        global $DB;
        // Backup course.
        set_config('background_restore', 1, 'mod_mattermost');
        $newcourseid = $this->backup_and_restore($this->course);
        $modules = get_coursemodules_in_course('mattermost', $newcourseid);
        $this->assertCount(1, $modules);
        $this->newmattermostmodule = array_pop($modules);
        $this->newmattermost = $DB->get_record('mattermost', array('id' => $this->newmattermostmodule->instance));

        // Initial Mattermost channel ID and restored channel ID should be same.
        $this->assertEquals($this->mattermost->mattermostid, $this->newmattermost->mattermostid);
        $mattermostapimanager = new mattermost_api_manager();
        $this->assertTrue($mattermostapimanager->channel_exists($this->newmattermost->mattermostid));
        $this->assertCount(2, $mattermostapimanager->get_enriched_channel_members($this->newmattermost->mattermostid));
        ob_start();
        phpunit_util::run_all_adhoc_tasks();
        ob_get_contents();
        ob_end_clean();
        $this->assertCount(2, $mattermostapimanager->get_enriched_channel_members($this->newmattermost->mattermostid));
    }

    /**
     * Function to perform Mattermost channel backup and restore for test
     *
     * @param object $course - object of course
     */
    protected function backup_and_restore($course) {
        global $USER, $CFG;
        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Do backup with default settings.
        set_config('backup_general_users', 1, 'backup');
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id,
            backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_GENERAL,
            $USER->id);
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $fp = get_file_packer('application/vnd.moodle.backup');
        $filepath = $CFG->dataroot . '/temp/backup/test-restore-course';
        $file->extract_to_pathname($fp, $filepath);
        $bc->destroy();

        // Do restore to new course with default settings.
        $newcourseid = restore_dbops::create_new_course(
            $course->fullname, $course->shortname . '_2', $course->category);
        $rc = new restore_controller('test-restore-course', $newcourseid,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id,
            backup::TARGET_NEW_COURSE);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
