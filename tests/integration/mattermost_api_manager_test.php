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
use \mod_mattermost\api\manager\mattermost_api_manager;
use \mod_mattermost\tools\mattermost_tools;

/**
 * Class for mattermost api manager testcases
 */
class mod_mattermost_api_manager_testcase extends advanced_testcase{
    /**
     * @var mattermost_api_manager
     */
    private $mattermostapimanager;

    /**
     * A constant mattermost instance id to be used while enrolling/unenrolling users or other functions
     */
    const MATTERMOST_INSTANCE_ID = 1;

    /**
     * A function to setup the test environment and initialize the variables
     */
    public function setUp() : void {
        global $DB;
        parent::setUp();
        set_config('background_enrolment_task', '', 'mod_mattermost');
        set_config('background_add_instance', 0, 'mod_mattermost');
        // Enable mattermost module.
        $modulerecord = $DB->get_record('modules', ['name' => 'mattermost']);
        $modulerecord->visible = 1;
        $DB->update_record('modules', $modulerecord);
        $this->initiate_test_environment();
    }

    /**
     * A function for testing the construct of mattermost api manager
     */
    public function test_construct() {
        $this->initiate_test_environment();
        $this->mattermostapimanager = new mattermost_api_manager();
        $this->assertNotNull($this->mattermostapimanager->get_instance_url());
        $this->assertNotNull($this->mattermostapimanager->get_client());
    }

    /**
     * Function for initiating the test environment
     */
    private function initiate_test_environment() {
        $this->resetAfterTest(true);
        $this->load_mattermost_test_config();
    }

    /**
     * Function for initiating the test environment and connection with Mattermost
     */
    private function initiate_environment_and_connection() {
        $this->initiate_test_environment();
        $this->mattermostapimanager = new mattermost_api_manager();
    }

    /**
     * Function for loading the test configuration
     */
    private function load_mattermost_test_config() {
        global $CFG;
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
    }

    /**
     * Test for creating a mattermost channel
     */
    public function test_create_mattermost_channel() {
        $this->initiate_environment_and_connection();
        $channelname = 'moodletestchannel' . time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertNotEmpty($channelid);
        $this->mattermostapimanager->archive_mattermost_channel($channelid);
    }

    /**
     * Test for creating a mattermost channel with invalid channel name
     */
    public function test_create_channel_invalid_channelname() {
        $this->initiate_environment_and_connection();
        $channelname = 'moodletestchannel/'.time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertDebuggingCalledCount(2);
        $this->assertEmpty($channelid);
        $sanitizedchannelname = mattermost_tools::sanitize_channelname($channelname);
        $channelid = $this->mattermostapimanager->create_mattermost_channel($sanitizedchannelname);
        $this->assertNotEmpty($channelid);
        $this->mattermostapimanager->archive_mattermost_channel($channelid);
    }


    /**
     * Test for creating a mattermost channel with whitespace in channel name
     */
    public function test_create_channel_channelname_with_whitespace() {
        $this->initiate_environment_and_connection();
        $channelname = 'moodletestchannel '.time();
        $sanitizedchannelname = mattermost_tools::sanitize_channelname($channelname);
        $channelid = $this->mattermostapimanager->create_mattermost_channel($sanitizedchannelname);
        $this->assertNotEmpty($channelid);
        $this->mattermostapimanager->archive_mattermost_channel($channelid);
    }

    /**
     * Test for creating channels with same names
     */
    public function test_create_channels_with_same_names() {
        $this->initiate_environment_and_connection();
        $channelname = 'moodletestchannel'.time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertNotEmpty($channelid);

        // Create same second time.
        $channelid2 = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertDebuggingCalledCount(2);
        $this->assertEmpty($channelid2);
        $this->mattermostapimanager->archive_mattermost_channel($channelid);
    }

    /**
     * Test for getting enriched channel members in a mattermost channel
     */
    public function test_get_enriched_channel_members() {
        $this->initiate_environment_and_connection();
        list($channeladmin, $user, $channelid) = $this->create_channel_with_users();
        $enrichedmembers = $this->mattermostapimanager->get_enriched_channel_members($channelid);
        $this->assertCount(2, $enrichedmembers);
        $this->assertTrue(array_key_exists($channeladmin->email, $enrichedmembers));
        $this->assertTrue(array_key_exists($user->email, $enrichedmembers));
        $this->assertTrue($enrichedmembers[$channeladmin->email]['is_channel_admin']);
        $this->assertFalse($enrichedmembers[$user->email]['is_channel_admin']);

        $this->mattermostapimanager->archive_mattermost_channel($channelid);
        $this->mattermostapimanager->delete_mattermost_user($channeladmin, self::MATTERMOST_INSTANCE_ID);
        $this->mattermostapimanager->delete_mattermost_user($user, self::MATTERMOST_INSTANCE_ID);
    }

    /**
     * Utility function to create a channel with two users as members
     *
     * @return array channeladmin user, member user, created channel id
     * @throws dml_exception
     */
    protected function create_channel_with_users() {
        $domainmail = get_config('mod_mattermost', 'domainmail');
        $channeladmin = new stdClass();
        $channeladmin->id = 1;
        $channeladmin->username = 'usertestchanneladmin';
        $channeladmin->firstname = 'moodleusertestchanneladmin';
        $channeladmin->lastname = $channeladmin->firstname;
        $channeladmin->email = $channeladmin->username . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $mattermostchanneladmin = $this->mattermostapimanager->get_or_create_user($channeladmin);
        $user = new stdClass();
        $user->id = 2;
        $user->username = 'usertestchannelmember';
        $user->firstname = 'moodleusertestUser';
        $user->lastname = $user->firstname;
        $user->email = $user->username . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
        $mattermostuser = $this->mattermostapimanager->get_or_create_user($user);
        $this->assertNotEmpty($mattermostchanneladmin);
        $this->assertNotEmpty($mattermostuser);
        $this->assertTrue(array_key_exists('id', $mattermostchanneladmin));
        $this->assertTrue(array_key_exists('id', $mattermostuser));
        $channelname = 'moodletestchannel' . time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertNotEmpty($channelid);

        $this->assertNotEmpty($this->mattermostapimanager->enrol_user_to_channel(
            $channelid, $channeladmin, static::MATTERMOST_INSTANCE_ID, true
        ));
        $this->assertNotEmpty($this->mattermostapimanager->enrol_user_to_channel(
            $channelid, $user, static::MATTERMOST_INSTANCE_ID
        ));
        return array($channeladmin, $user, $channelid);
    }

    /**
     * Function for testing enrol and unenrol user function
     */
    public function test_enrol_unenrol_user_to_channel() {
        $this->initiate_environment_and_connection();
        list($channeladmin, $user, $channelid) = $this->create_channel_with_users();

        $members = $this->mattermostapimanager->get_enriched_channel_members($channelid);
        $this->assertTrue(is_array($members));
        $this->assertCount(2, $members);

        $this->mattermostapimanager->unenrol_user_from_channel($channelid, $channeladmin, static::MATTERMOST_INSTANCE_ID);
        $this->mattermostapimanager->unenrol_user_from_channel($channelid, $user, static::MATTERMOST_INSTANCE_ID);

        $members = $this->mattermostapimanager->get_enriched_channel_members($channelid);
        $this->assertTrue(is_array($members));
        $this->assertCount(0, $members);

        $this->mattermostapimanager->archive_mattermost_channel($channelid);
        $this->mattermostapimanager->delete_mattermost_user($channeladmin, self::MATTERMOST_INSTANCE_ID);
        $this->mattermostapimanager->delete_mattermost_user($user, self::MATTERMOST_INSTANCE_ID);
    }
}
