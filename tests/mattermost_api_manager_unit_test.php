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
use mod_mattermost\client\mattermost_rest_client;

/**
 * Class for mattermost api manager unit testcases
 */
class mod_mattermost_api_manager_unit_testcase extends advanced_testcase
{
    /**
     * @var stdClass user record
     */
    private $user;

    /**
     * @var stdClass course record
     */
    private $course;

    /**
     * @var testing_data_generator
     */
    private $datagenerator;

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
        $this->initiate_test_environment();

        $domainmail = get_config('mod_mattermost', 'domainmail');
        if (!$this->datagenerator) {
            $this->datagenerator = $this->getDataGenerator();
            $this->course = $this->datagenerator->create_course();
            $username = 'moodleusertest' . time();
            $email = $username . '@' . (!empty($domainmail) ? $domainmail : 'moodle.test');
            $this->user = $this->datagenerator->create_user(
                array('username' => $username, 'firstname' => $username, 'lastname' => $username, 'email' => $email)
            );

        }
    }

    /**
     * A function for testing the construct of mattermost api manager
     */
    public function test_construct() {
        $client = $this->createMock(mattermost_rest_client::class);
        $mattermostapimanager = new mattermost_api_manager($client);
        $this->assertNotNull($mattermostapimanager->get_client());
    }

    /**
     * Function for initiating the test environment
     */
    private function initiate_test_environment() {
        $this->resetAfterTest();
        $this->load_mattermost_test_config();
    }

    /**
     * Function for loading the test configuration
     */
    private function load_mattermost_test_config() {
        global $CFG;
        require($CFG->dirroot . '/mod/mattermost/config-test.php');
    }

    /**
     * Data provider for create_mattermost_channel
     * @return array
     */
    public function create_mattermost_channel_provider(): array {
        return [
            'success' => [
                'channelname' => get_channel_name(),
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('create_channel')->with(
                        $this->equalTo(get_channel_name())
                    )->willReturn(get_mattermost_id());
                    return $client;
                },
                'expected' => get_mattermost_id(),
                'debuggingcalled' => false,
            ],
            'create_channel api failed' => [
                'channelname' => get_channel_name(),
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('create_channel')->with(
                        $this->equalTo(get_channel_name())
                    )->willThrowException(new Exception);
                    return $client;
                },
                'expected' => null,
                'debuggingcalled' => true,
            ]
        ];
    }

    /**
     * Test for creating a mattermost channel
     *
     * @dataProvider create_mattermost_channel_provider
     * @param string $channelname
     * @param callable $getclient Function to get the client
     * @param string $expected The expected return value
     * @param bool $debuggingcalled
     */
    public function test_create_mattermost_channel($channelname, $getclient, $expected, $debuggingcalled) {
        $mattermostapimanager = new mattermost_api_manager($getclient());
        $channelid = $mattermostapimanager->create_mattermost_channel($channelname);
        $this->assertEquals($expected, $channelid);
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        }
    }

    /**
     * Data provider for enrol_user_to_channel
     * @return array
     */
    public function enrol_user_to_channel_provider(): array {
        return [
            'success' => [
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('get_or_create_user')->willReturn(get_mattermost_user());
                    $client->expects($this->once())->method('add_user_to_channel')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->callback(function ($arg) {
                            $mattermostuser = get_mattermost_user();
                            return is_array($arg) && array_key_exists('user_id', $arg) && $arg['user_id'] == $mattermostuser['id'];
                        })
                    );
                    return $client;
                },
                'expected' => get_mattermost_user(),
                'debuggingcalled' => false,
            ],
            'get_or_create_user api failed' => [
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('get_or_create_user')->willThrowException(new Exception);
                    $client->expects($this->never())->method('add_user_to_channel');
                    return $client;
                },
                'expected' => array(),
                'debuggingcalled' => true,
            ],
            'add_user_to_channel api failed' => [
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('get_or_create_user')->willReturn(get_mattermost_user());
                    $client->expects($this->once())->method('add_user_to_channel')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->callback(function ($arg) {
                            $mattermostuser = get_mattermost_user();
                            return is_array($arg) && array_key_exists('user_id', $arg) && $arg['user_id'] == $mattermostuser['id'];
                        })
                    )->willThrowException(new Exception);
                    return $client;
                },
                'expected' => get_mattermost_user(),
                'debuggingcalled' => true,
            ],
        ];
    }

    /**
     * Function for testing the enrol_user_to_channel function in api manager
     *
     * @dataProvider enrol_user_to_channel_provider
     * @param callable $getclient Function to get the client
     * @param array $expected The expected return value
     * @param bool $debuggingcalled
     */
    public function test_enrol_user_to_channel($getclient, $expected, $debuggingcalled) {
        $channelid = get_mattermost_id();
        $mattermostapimanager = new mattermost_api_manager($getclient());
        $user = $mattermostapimanager->enrol_user_to_channel($channelid, $this->user, $this::MATTERMOST_INSTANCE_ID, false);
        $this->assertEquals($expected, $user);
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        }
    }

    /**
     * Data provider for delete_mattermost_user
     * @return array
     */
    public function delete_mattermost_user_provider(): array {
        return [
            'success' => [
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->with(
                        $this->equalTo('mattermostxusers'),
                        $this->callback(function ($arg) {
                            if (is_array($arg) && array_key_exists('moodleuserid', $arg) &&
                            array_key_exists('mattermostinstanceid', $arg)) {
                                return $arg['mattermostinstanceid'] == $this::MATTERMOST_INSTANCE_ID;
                            }
                            return false;
                        })
                    )->willReturn($mattermostuser);

                    $client->expects($this->once())->method('delete_mattermost_user')->with(
                        $this->equalTo($mattermostuser->mattermostuserid)
                    );

                    $DB->expects($this->once())->method('delete_records_select');
                    return $client;
                },
                'debuggingcalled' => false,
            ],
            'delete_mattermost_user api failed' => [
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->willReturn($mattermostuser);

                    $client->expects($this->once())->method('delete_mattermost_user')->with(
                        $this->equalTo($mattermostuser->mattermostuserid)
                    )->willThrowException(new Exception);

                    $DB->expects($this->never())->method('delete_records_select');
                    return $client;
                },
                'debuggingcalled' => true,
            ]
        ];
    }

    /**
     * Function for testing the delete_mattermost_user function in api manager
     *
     * @dataProvider delete_mattermost_user_provider
     * @param callable $getclient Function to get the client
     * @param bool $debuggingcalled
     */
    public function test_delete_mattermost_user($getclient, $debuggingcalled) {
        $mattermostapimanager = new mattermost_api_manager($getclient());
        $mattermostapimanager->delete_mattermost_user($this->user, $this::MATTERMOST_INSTANCE_ID);
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        } else {
            $this->assertDebuggingNotCalled();
        }
    }

    /**
     * Data provider for update_role_in_channel
     * @return array
     */
    public function update_role_in_channel_provider(): array {
        return [
            'success' => [
                'updatetochanneladmin' => true,
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->willReturn($mattermostuser);

                    $client->expects($this->once())->method('update_channel_member_roles')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->callback(function($arg) use ($mattermostuser) {
                            if (is_array($arg) && array_key_exists('user_id', $arg) && array_key_exists('role', $arg)) {
                                return $arg['user_id'] == $mattermostuser->mattermostuserid &&
                                $arg['role'] == mattermost_api_manager::MATTERMOST_CHANNEL_ADMIN_ROLE;
                            }
                            return false;
                        })
                    );

                    return $client;
                },
                'debuggingcalled' => false,
            ],
            'update_channel_member_roles api failed' => [
                'updatetochanneladmin' => false,
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->willReturn($mattermostuser);

                    $client->expects($this->once())->method('update_channel_member_roles')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->callback(function($arg) use ($mattermostuser) {
                            if (is_array($arg) && array_key_exists('user_id', $arg) && array_key_exists('role', $arg)) {
                                return $arg['user_id'] == $mattermostuser->mattermostuserid &&
                                $arg['role'] == mattermost_api_manager::MATTERMOST_CHANNEL_MEMBER_ROLE;
                            }
                            return false;
                        })
                    )->willThrowException(new Exception);

                    return $client;
                },
                'debuggingcalled' => true,
            ],
        ];
    }

    /**
     * Function for testing the update_role_in_channel function in api manager
     *
     * @dataProvider update_role_in_channel_provider
     * @param bool $updatetochanneladmin Whether to update the role of user to channel admin
     * @param callable $getclient Function to get the client
     * @param bool $debuggingcalled
     */
    public function test_update_role_in_channel($updatetochanneladmin, $getclient, $debuggingcalled) {
        $channelid = get_mattermost_id();
        $mattermostapimanager = new mattermost_api_manager($getclient());
        $mattermostapimanager->update_role_in_channel($channelid, $this->user,
            $updatetochanneladmin, $this::MATTERMOST_INSTANCE_ID);
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        } else {
            $this->assertDebuggingNotCalled();
        }
    }

    /**
     * Data provider for unenrol_user_from_channel
     * @return array
     */
    public function unenrol_user_from_channel_provider(): array {
        return [
            'success' => [
                'passmoodleuser' => true,
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->willReturn($mattermostuser);

                    $client->expects($this->once())->method('remove_user_from_channel')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->equalTo($mattermostuser->mattermostuserid)
                    );

                    return $client;
                },
                'debuggingcalled' => false,
            ],
            'success with passing mattermost channel member' => [
                'passmoodleuser' => false,
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $DB->expects($this->never())->method('get_record');

                    $client->expects($this->once())->method('remove_user_from_channel')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->equalTo(get_mattermost_id())
                    );

                    return $client;
                },
                'debuggingcalled' => false,
            ],
            'remove_user_from_channel api failed' => [
                'passmoodleuser' => true,
                'getclient' => function() {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $mattermostuser = new stdClass();
                    $mattermostuser->mattermostuserid = get_mattermost_id();
                    $DB->expects($this->once())->method('get_record')->willReturn($mattermostuser);

                    $client->expects($this->once())->method('remove_user_from_channel')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->equalTo($mattermostuser->mattermostuserid)
                    )->willThrowException(new Exception);

                    return $client;
                },
                'debuggingcalled' => true,
            ],
        ];
    }

    /**
     * Function for testing the unenrol_user_from_channel function in api manager
     *
     * @dataProvider unenrol_user_from_channel_provider
     * @param bool $passmoodleuser Whether to pass the moodle user, if false we pass the mattermost user
     * @param callable $getclient Function to get the client
     * @param bool $debuggingcalled
     */
    public function test_unenrol_user_from_channel($passmoodleuser, $getclient, $debuggingcalled) {
        $channelid = get_mattermost_id();
        $mattermostapimanager = new mattermost_api_manager($getclient());
        if ($passmoodleuser) {
            $mattermostapimanager->unenrol_user_from_channel($channelid, $this->user, $this::MATTERMOST_INSTANCE_ID);
        } else {
            $mattermostapimanager->unenrol_user_from_channel($channelid, null,
                $this::MATTERMOST_INSTANCE_ID, get_mattermost_user());
        }
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        } else {
            $this->assertDebuggingNotCalled();
        }
    }

    /**
     * Data provider for get_enriched_channel_members
     * @return array
     */
    public function get_enriched_channel_members_provider(): array {
        return [
            'success' => [
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('get_channel_members')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->equalTo(0),
                        $this->equalTo(60),
                    )->willReturn(get_mattermost_channel_members());
                },
                'debuggingcalled' => false,
            ],
            'get_channel_members api failed' => [
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('get_channel_members')->with(
                        $this->equalTo(get_mattermost_id()),
                        $this->equalTo(0),
                        $this->equalTo(60),
                    )->willThrowException(new Exception());
                },
                'debuggingcalled' => true,
            ],
        ];
    }

    /**
     * Function for testing the get_enriched_channel_members function in api manager
     *
     * @dataProvider get_enriched_channel_members_provider
     * @param callable $getclient Function to get the client
     * @param bool $debuggingcalled
     */
    public function test_get_enriched_channel_members($getclient, $debuggingcalled) {
        $channelid = get_mattermost_id();
        $mattermostapimanager = new mattermost_api_manager($getclient());
        $enrichedmembers = $mattermostapimanager->get_enriched_channel_members($channelid);
        $this->assertIsArray($enrichedmembers);
        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        } else {
            $this->assertDebuggingNotCalled();
        }
    }

    /**
     * Data provider for archive_mattermost_channel
     * @return array
     */
    public function archive_mattermost_channel_provider(): array {
        return [
            'success without course id' => [
                'passcourseid' => false,
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('archive_channel')->with(
                        $this->equalTo(get_mattermost_id())
                    );
                    return $client;
                },
                'debuggingcalled' => false,
            ],
            'archive_channel api failed' => [
                'passcourseid' => false,
                'getclient' => function() {
                    $client = $this->createMock(mattermost_rest_client::class);
                    $client->expects($this->once())->method('archive_channel')->with(
                        $this->equalTo(get_mattermost_id())
                    )->willThrowException(new Exception);
                    return $client;
                },
                'debuggingcalled' => true,
            ],
            'success with course id' => [
                'passcourseid' => true,
                'getclient' => function($courseid) {
                    global $DB;
                    $client = $this->createMock(mattermost_rest_client::class);
                    $DB = $this->createMock(get_class($DB));
                    $group = new stdClass();
                    $group->channelid = get_mattermost_id();
                    $groups = [$group, $group];
                    $DB->expects($this->once())->method('get_records')->with(
                        $this->equalTo('mattermostxgroups'),
                        $this->equalTo(array('courseid' => $courseid)),
                    )->willReturn($groups);

                    $client->expects($this->exactly(count($groups) + 1))->method('archive_channel')->with(
                        $this->equalTo(get_mattermost_id())
                    );
                    return $client;
                },
                'debuggingcalled' => false,
            ],
        ];
    }

    /**
     * Function for testing the archive_mattermost_channel function in api manager
     *
     * @dataProvider archive_mattermost_channel_provider
     * @param bool $passcourseid Whether to pass the course id or not
     * @param callable $getclient Function to get the client
     * @param bool $debuggingcalled
     */
    public function test_archive_mattermost_channel($passcourseid, $getclient, $debuggingcalled) {
        $channelid = get_mattermost_id();
        $mattermostapimanager = new mattermost_api_manager($getclient($this->course->id));
        if ($passcourseid) {
            $mattermostapimanager->archive_mattermost_channel($channelid, $this->course->id);
        } else {
            $mattermostapimanager->archive_mattermost_channel($channelid);
        }

        if ($debuggingcalled) {
            $this->assertdebuggingcalled();
        } else {
            $this->assertDebuggingNotCalled();
        }
    }
}
