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
 * @author    Manoj <manoj@brightscout.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Class for course reset testcases
 */
class mod_mattermost_course_reset_testcase extends advanced_testcase{
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
     * @var stdClass role record
     */
    private $editingteacherrole;

    /**
     * @var stdClass role record
     */
    private $studentrole;

    /**
     * A function to setup the test environment and initialize the variables
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
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $studentusername = 'moodleusertest'.time();
        $this->userstudent = $generator->create_user(array('username' => $studentusername,
            'firstname' => $studentusername, 'lastname' => $studentusername));
        $this->studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $generator->enrol_user($this->userstudent->id, $this->course->id, $this->studentrole->id);
        $edititingteacherusername = 'moodleusertest'.(time() + 1);
        $this->usereditingteacher = $generator->create_user(array('username' => $edititingteacherusername,
            'firstname' => $edititingteacherusername, 'lastname' => $edititingteacherusername));
        $this->editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $generator->enrol_user($this->usereditingteacher->id, $this->course->id, $this->editingteacherrole->id);
        // Set a channelname for tests.
        set_config('channelnametoformat',
            'moodleunittest_{$a->courseshortname}_{$a->moduleid}_'.time(),
            'mod_mattermost');

        $channelname = mattermost_tools::get_mattermost_channel_name_for_instance(0, $this->course);
        $this->mattermost = $generator->create_module('mattermost',
            array('course' => $this->course->id, 'name' => $channelname));
        $this->mattermostapimanager = new mattermost_api_manager();
    }

    /**
     * Function to tear down everything after all the tests are complete
     */
    protected function tearDown() : void {
        ob_start();
        if (!empty($this->mattermost)) {
            course_delete_module($this->mattermost->cmid, true);
        }
        $this->mattermostapimanager->delete_mattermost_user($this->userstudent, $this->mattermost->id);
        $this->mattermostapimanager->archive_mattermost_channel($this->mattermost->mattermostid);
        ob_get_contents();
        ob_end_clean();
        parent::tearDown();
    }

    /**
     * Test for ensuring the synchronization with Mattermost when course is reset.
     */
    public function test_course_reset() {
        // Structure created in setUp.

        $data = new stdClass();
        $data->id = $this->course->id;
        $data->unenrol_users = false;
        $data->reset_mattermost = false;
        reset_course_userdata($data);
        $this->assertCount(2, $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid));

        $data->unenrol_users = array($this->studentrole->id, $this->editingteacherrole->id);
        reset_course_userdata($data);
        $this->assertCount(0, $this->mattermostapimanager->get_enriched_channel_members($this->mattermost->mattermostid));
    }
}
