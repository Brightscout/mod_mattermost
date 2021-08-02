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
 *
 * Enable Mattermost connection test
 *
 * @package    mod
 * @subpackage mattermost
 * @copyright   2020 Manoj <manoj@brightscout.com>
 * @author Manoj <manoj@brightscout.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
use \mod_mattermost\api\manager\mattermost_api_manager;
require_login();

require_capability('moodle/site:config', context_system::instance());
admin_externalpage_setup('mattermostconnectiontest');
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/mattermost/test_mattermost_connection.php'));
$PAGE->set_title(get_string('testconnection', 'mod_mattermost'));
$PAGE->set_heading(get_string('testconnection', 'mod_mattermost'));
$PAGE->set_pagelayout('admin');

$site = get_site();

$config = get_config('mod_mattermost');
$instanceurl = $config->instanceurl;
$secret  = $config->secret;

echo $OUTPUT->header();
echo $OUTPUT->container_start('center');

$result = true;
try {
    $mattermostapimanager = new mattermost_api_manager($instanceurl, $secret);
    $result = $mattermostapimanager->test_connection();
} catch (Exception $e) {
    $result = false;
    echo html_writer::tag('h2', get_string('errorintestwhileconnection', 'mod_mattermost'));
    echo html_writer::div(get_string('testerrorcode', 'mod_mattermost', $e->getCode()), 'error');
    echo html_writer::div(get_string('testerrormessage', 'mod_mattermost', $e->getMessage()), 'error');
}
if ($result) {
    echo html_writer::tag('h2', get_string('connectiontestresult', 'mod_mattermost'));
    echo html_writer::div(get_string('connection-success', 'mod_mattermost'), 'alert');
}
echo $OUTPUT->container_end();
echo $OUTPUT->footer();
