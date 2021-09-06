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
 * @package    local_digital_training_account_services
 * @copyright   2020 ESUP-Portail {@link https://www.esup-portail.org/}
 * @author Céline Pervès<cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
use \mod_mattermost\api\manager\mattermost_api_manager;
// global $CFG;

// require_once($CFG->dirroot.'/mod/mattermost/vendor/autoload.php');

class mod_mattermost_api_manager_testcase extends advanced_testcase{
    /**
     * @var mattermost_api_manager
     */
    private $mattermostapimanager;

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
        // set_config('create_user_account_if_not_exists', 1, 'mod_mattermost');
    }

    public function test_construct() {
        $this->initiate_test_environment();
        $this->mattermostapimanager = new mattermost_api_manager();
        $this->assertNotNull($this->mattermostapimanager->get_instance_url());
        $this->assertNotNull($this->mattermostapimanager->get_client());
    }

    private function initiate_test_environment() {
        $this->resetAfterTest(true);
        $this->load_mattermost_test_config();
    }

    private function initiate_environment_and_connection() {
        $this->initiate_test_environment();
        $this->mattermostapimanager = new mattermost_api_manager();
    }

    private function load_mattermost_test_config() {
        global $CFG;
        require($CFG->dirroot.'/mod/mattermost/config-test.php');
    }

    public function test_create_mattermost_channel() {
        $this->initiate_environment_and_connection();
        $channelname = 'moodletestchannel' . time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertNotEmpty($channelid);
    }

    public function test_get_enriched_channel_members() {
        $this->initiate_environment_and_connection();
        list($channeladmin, $mattermostchanneladmin, $user, $mattermostuser, $channelid) = $this->create_channel_with_users();
        $enrichedmembers = $this->mattermostapimanager->get_enriched_channel_members($channelid);
        $this->assertCount(2, $enrichedmembers);
        $this->assertTrue(array_key_exists($mattermostchanneladmin['email'], $enrichedmembers));
        $this->assertTrue(array_key_exists($mattermostuser['email'], $enrichedmembers));
        $this->assertTrue($enrichedmembers[$mattermostchanneladmin['email']]['is_channel_admin']);
        $this->assertFalse($enrichedmembers[$mattermostuser['email']]['is_channel_admin']);
    }

    /**
     * @return array
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
        $channelname = 'moodletestchannel' . time();
        $channelid = $this->mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertNotEmpty($channelid);

        // TODO
        // $this->assertTrue($this->mattermostapimanager->channel_exists($channelid));

        $this->assertNotEmpty($this->mattermostapimanager->enrol_user_to_channel($channelid, $channeladmin, 1, true));
        $this->waitForSecond(); // Some times seems that Rocket.Chat server is too long.
        $this->assertNotEmpty($this->mattermostapimanager->enrol_user_to_channel($channelid, $user, 1));
        return array($channeladmin, $mattermostchanneladmin, $user, $mattermostuser, $channelid);
    }
}
